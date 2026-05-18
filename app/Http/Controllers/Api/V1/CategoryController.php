<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\categories;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {

        $data = Cache::remember('categories:all', 600, function () {
            return categories::query()
                ->withCount('products')
                ->orderBy('type')
                ->get()
                ->map(fn ($c) => [
                    'id'             => $c->id,
                    'name'           => $c->type,
                    'products_count' => (int) ($c->products_count ?? 0),
                ]);
        });

        return response()->json(['data' => $data]);
    }
}
