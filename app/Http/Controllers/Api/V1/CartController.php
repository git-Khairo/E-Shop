<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->carts->forUser($request->user()->id);

        return response()->json([
            'data'  => CartItemResource::collection($items),
            'total' => round($items->sum(fn ($i) => $i->unit_price_snapshot * $i->quantity), 2),
        ]);
    }

    /**
     * Add-to-cart: increments the cart line by {quantity}.
     *
     * Stock safety: The quantity requested is capped by the current product stock
     * at request time, but the authoritative stock reservation happens later
     * during checkout inside a DB transaction with row locking
     * ({@see \App\Services\StockService}). Cart is advisory; checkout is binding.
     */
    public function add(AddToCartRequest $request): JsonResponse
    {
        $product = $this->products->findById($request->integer('product_id'));

        abort_if(! $product || ! $product->is_active, 404, 'Product not found.');

        $requested = $request->integer('quantity');
        $existing  = $this->carts->forUser($request->user()->id)
            ->firstWhere('product_id', $product->id)?->quantity ?? 0;

        if (($existing + $requested) > $product->stock) {
            return response()->json([
                'message' => "Only {$product->stock} in stock. You already have {$existing} in your cart.",
                'stock'   => $product->stock,
            ], 422);
        }

        $this->carts->upsert(
            userId:    $request->user()->id,
            productId: $product->id,
            quantity:  $requested,
            unitPrice: (float) $product->price,
        );

        return response()->json(['message' => 'Added to cart.'], 201);
    }

    /**
     * Set a cart line's quantity to an exact value (PATCH). Idempotent —
     * submitting the same final quantity twice is a no-op, which avoids
     * double-commit bugs from flaky networks or impatient users.
     */
    public function update(UpdateCartItemRequest $request, int $productId): JsonResponse
    {
        $product = $this->products->findById($productId);
        abort_if(! $product || ! $product->is_active, 404, 'Product not found.');

        $target = $request->integer('quantity');

        if ($target > $product->stock) {
            return response()->json([
                'message' => "Only {$product->stock} available.",
                'stock'   => $product->stock,
            ], 422);
        }

        $final = $this->carts->setQuantity(
            userId:    $request->user()->id,
            productId: $product->id,
            quantity:  $target,
            unitPrice: (float) $product->price,
        );

        return response()->json([
            'message'  => $final === 0 ? 'Item removed.' : 'Quantity updated.',
            'quantity' => $final,
        ]);
    }

    public function remove(Request $request, int $productId): JsonResponse
    {
        $this->carts->remove($request->user()->id, $productId);

        return response()->json(['message' => 'Removed.']);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->carts->clear($request->user()->id);

        return response()->json(['message' => 'Cart cleared.']);
    }
}
