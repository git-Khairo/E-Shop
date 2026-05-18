<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method_token' => ['required', 'string'],

            'items'                  => ['sometimes', 'array', 'min:1'],
            'items.*.product_id'     => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity'       => ['required_with:items', 'integer', 'min:1', 'max:100'],
        ];
    }
}
