<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\DTOs\CartItemDTO;
use App\Domain\DTOs\CheckoutDTO;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PaymentFailedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $service,
        private readonly OrderRepositoryInterface $orders,
        private readonly CartRepositoryInterface $carts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->orders->paginateForUser($request->user()->id);

        return response()->json([
            'data' => OrderResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orders->findForUser($request->user()->id, $orderId);

        abort_if(! $order, 404);

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        $items = $request->input('items');
        if (empty($items)) {
            $items = $this->carts->forUser($userId)->map(fn ($ci) => [
                'product_id' => $ci->product_id,
                'quantity'   => $ci->quantity,
            ])->values()->all();
        }

        if (empty($items)) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        $dto = new CheckoutDTO(
            userId:             $userId,
            items:              array_map(fn ($i) => CartItemDTO::fromArray($i), $items),
            paymentMethodToken: $request->string('payment_method_token')->toString(),
        );

        try {
            $order = $this->service->checkout($dto);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message'    => 'One or more items are out of stock.',
                'product_id' => $e->productId,
                'available'  => $e->available,
                'requested'  => $e->requested,
            ], 409);
        } catch (PaymentFailedException $e) {
            return response()->json([
                'message' => 'Payment failed: '.$e->getMessage(),
            ], 402);
        }

        return response()->json(['data' => new OrderResource($order)], 201);
    }
}
