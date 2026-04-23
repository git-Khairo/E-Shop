<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'sku'         => $this->sku,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'price'       => (float) $this->price,
            'image'       => $this->image,
            'stock'       => (int) $this->stock,
            'in_stock'    => (int) $this->stock > 0,
            'category_id' => $this->categories_id,
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
