<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PATCH /api/v1/cart/{productId}.
 *
 * The hard max of 100 protects against abuse and keeps inventory reservation
 * pressure predictable; the actual stock cap is enforced in the controller,
 * which reads the live product row (so stale tabs can't overshoot inventory).
 */
class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
