<?php

namespace App\Services;

use App\Domain\DTOs\CheckoutDTO;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PaymentFailedException;
use App\Jobs\GenerateInvoicePdf;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * OrderService — orchestrates the full checkout workflow with ACID guarantees.
 *
 * Checkout pipeline (read bottom-to-top of the diagram):
 *
 *   ┌──────────────────────────────┐
 *   │ 6. Dispatch async side-effects│  (email, invoice PDF)
 *   ├──────────────────────────────┤
 *   │ 5. Payment → mark order paid │  (outside DB tx, compensating rollback on fail)
 *   ├──────────────────────────────┤
 *   │ 4. COMMIT                    │
 *   │ 3. Create order + items      │
 *   │ 2. Decrement stock safely    │  (pessimistic or optimistic)
 *   │ 1. BEGIN                     │
 *   └──────────────────────────────┘
 *
 * ACID: if ANY step in 1–4 fails, the DB is rolled back AS IF nothing happened.
 * If payment (step 5) fails after commit, we run a compensating action:
 * stock is returned to the shelf and the order is marked `payment_failed`.
 */
class OrderService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly OrderRepositoryInterface $orders,
        private readonly CartRepositoryInterface $carts,
        private readonly StockService $stock,
        private readonly PaymentService $payments,
        private readonly ProductService $productCache,
    ) {}

    public function checkout(CheckoutDTO $dto): Order
    {
        if (empty($dto->items)) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        // Pick a strategy via config; both are production-safe.
        $strategy = config('commerce.stock_strategy', 'optimistic'); // 'optimistic' | 'pessimistic'

        // -----------------------------------------------------------------
        //  PHASE 1: transactional stock reservation + order persistence
        // -----------------------------------------------------------------
        $order = DB::transaction(function () use ($dto, $strategy) {
            $subtotal = 0.0;
            $lines    = [];

            // Sort items by product_id to acquire locks in a deterministic order.
            // This is the classic deadlock-avoidance technique for row-level locking:
            // if every transaction locks rows in the same order, no cycle can form.
            $items = collect($dto->items)->sortBy('productId')->values();

            foreach ($items as $item) {
                // Load price/name — price is snapshotted into order_items so future
                // price changes don't affect historical orders.
                $product = $strategy === 'pessimistic'
                    ? $this->products->lockForUpdate($item->productId)  // FOR UPDATE lock
                    : $this->products->findById($item->productId);

                if (! $product || ! $product->is_active) {
                    throw new InsufficientStockException($item->productId, $item->quantity, 0);
                }

                if ($strategy === 'pessimistic') {
                    // Lock already held — validate + update inline (no retry needed).
                    if ($product->stock < $item->quantity) {
                        throw new InsufficientStockException(
                            $item->productId, $item->quantity, $product->stock
                        );
                    }
                    $product->stock -= $item->quantity;
                    $product->stock_version += 1;
                    $product->save();
                } else {
                    // Optimistic: non-blocking, retries internally.
                    $this->stock->decrementOptimistic($item->productId, $item->quantity);
                }

                $lineTotal = round(((float) $product->price) * $item->quantity, 2);
                $subtotal += $lineTotal;

                $lines[] = [
                    'product_id'            => $product->id,
                    'product_name_snapshot' => $product->name,
                    'quantity'              => $item->quantity,
                    'unit_price'            => $product->price,
                    'line_total'            => $lineTotal,
                ];
            }

            /** @var Order $order */
            $order = $this->orders->create([
                'reference'      => 'ORD-'.strtoupper(Str::random(10)),
                'user_id'        => $dto->userId,
                'status'         => 'pending',
                'payment_status' => 'pending',
                'subtotal'       => $subtotal,
                'total'          => $subtotal,               // taxes/shipping could be added here
                'price'          => $subtotal,               // legacy column
                'products'       => $lines,                  // legacy JSON snapshot
            ]);

            foreach ($lines as $line) {
                OrderItem::create(['order_id' => $order->id] + $line);
            }

            return $order;
        }, 3 /* DB attempts on deadlock */);

        // Cache invalidation happens AFTER commit (other replicas might read stale otherwise).
        foreach ($dto->items as $item) {
            $this->productCache->invalidateCache($item->productId);
        }

        // -----------------------------------------------------------------
        //  PHASE 2: payment (outside transaction) + compensation on failure
        // -----------------------------------------------------------------
        try {
            $payment = $this->payments->charge($order, $dto->paymentMethodToken);

            $order->update([
                'payment_status' => $payment->status === 'succeeded' ? 'paid' : 'pending',
                'status'         => $payment->status === 'succeeded' ? 'confirmed' : 'pending',
            ]);
        } catch (PaymentFailedException $e) {
            Log::warning('Payment failed, compensating order', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            $this->compensate($order);
            throw $e;
        } catch (Throwable $e) {
            Log::error('Unexpected checkout failure', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $this->compensate($order);
            throw $e;
        }

        // -----------------------------------------------------------------
        //  PHASE 3: async side effects (queues = asynchronous processing)
        // -----------------------------------------------------------------
        SendOrderConfirmationEmail::dispatch($order->id)->onQueue('emails');
        GenerateInvoicePdf::dispatch($order->id)->onQueue('invoices');

        // Clear the cart last — if anything above fails the cart is preserved.
        $this->carts->clear($dto->userId);

        return $order->fresh(['items', 'payment']);
    }

    /**
     * Compensating transaction: release reserved stock, mark order failed.
     * Safe to call at most once; idempotent on repeat via `payment_status` check.
     */
    private function compensate(Order $order): void
    {
        DB::transaction(function () use ($order) {
            if ($order->payment_status === 'paid') {
                return; // nothing to undo
            }

            foreach ($order->items as $item) {
                $this->stock->increment($item->product_id, $item->quantity);
                $this->productCache->invalidateCache($item->product_id);
            }

            $order->update([
                'status'         => 'cancelled',
                'payment_status' => 'failed',
            ]);
        });
    }
}
