<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['q', 'category_id', 'price_min', 'price_max', 'sort']);
        $perPage = min(50, max(1, (int) $request->integer('per_page', 15)));

        $page = $this->products->list($filters, $perPage);

        return response()->json([
            'data' => ProductResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->products->find($id);

        abort_if(! $product, 404, 'Product not found.');

        return response()->json(['data' => new ProductResource($product)]);
    }
}
