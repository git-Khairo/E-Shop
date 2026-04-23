<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id'  => $this->product_id,
            'quantity'    => (int) $this->quantity,
            'unit_price'  => (float) $this->unit_price_snapshot,
            'line_total'  => round((float) $this->unit_price_snapshot * (int) $this->quantity, 2),
            'product'     => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
        ];
    }
}
