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

        $strategy = config('commerce.stock_strategy', 'optimistic');

        $order = DB::transaction(function () use ($dto, $strategy) {
            $subtotal = 0.0;
            $lines    = [];

            $items = collect($dto->items)->sortBy('productId')->values();

            foreach ($items as $item) {

                $product = $strategy === 'pessimistic'
                    ? $this->products->lockForUpdate($item->productId)
                    : $this->products->findById($item->productId);

                if (! $product || ! $product->is_active) {
                    throw new InsufficientStockException($item->productId, $item->quantity, 0);
                }

                if ($strategy === 'pessimistic') {

                    if ($product->stock < $item->quantity) {
                        throw new InsufficientStockException(
                            $item->productId, $item->quantity, $product->stock
                        );
                    }
                    $product->stock -= $item->quantity;
                    $product->stock_version += 1;
                    $product->save();
                } else {

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

            $order = $this->orders->create([
                'reference'      => 'ORD-'.strtoupper(Str::random(10)),
                'user_id'        => $dto->userId,
                'status'         => 'pending',
                'payment_status' => 'pending',
                'subtotal'       => $subtotal,
                'total'          => $subtotal,
                'price'          => $subtotal,
                'products'       => $lines,
            ]);

            foreach ($lines as $line) {
                OrderItem::create(['order_id' => $order->id] + $line);
            }

            return $order;
        }, 3);

        foreach ($dto->items as $item) {
            $this->productCache->invalidateCache($item->productId);
        }

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

        SendOrderConfirmationEmail::dispatch($order->id)->onQueue('emails');
        GenerateInvoicePdf::dispatch($order->id)->onQueue('invoices');

        $this->carts->clear($dto->userId);

        return $order->fresh(['items', 'payment']);
    }

    private function compensate(Order $order): void
    {
        DB::transaction(function () use ($order) {
            if ($order->payment_status === 'paid') {
                return;
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
