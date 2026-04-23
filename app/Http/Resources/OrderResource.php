<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'reference'      => $this->reference,
            'status'         => $this->status,
            'payment_status' => $this->payment_status,
            'subtotal'       => (float) $this->subtotal,
            'total'          => (float) $this->total,
            'created_at'     => $this->created_at?->toIso8601String(),
            'items'          => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'name'       => $i->product_name_snapshot,
                'quantity'   => $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'line_total' => (float) $i->line_total,
            ])),
            'payment'        => $this->whenLoaded('payment', fn () => $this->payment ? [
                'status'   => $this->payment->status,
                'provider' => $this->payment->provider,
                'amount'   => (float) $this->payment->amount,
            ] : null),
        ];
    }
}
