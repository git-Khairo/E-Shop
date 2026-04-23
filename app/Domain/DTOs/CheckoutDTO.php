<?php

namespace App\Domain\DTOs;

final class CheckoutDTO
{
    /**
     * @param  CartItemDTO[]  $items
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $items,
        public readonly string $paymentMethodToken, // e.g. Stripe PaymentMethod id, or "mock_success"
        public readonly string $currency = 'USD',
    ) {}
}
