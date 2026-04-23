<?php

namespace App\Domain\DTOs;

/**
 * Immutable, framework-agnostic representation of a single cart line.
 * We pass DTOs between layers (Controller -> Service -> Repository) instead
 * of Eloquent models or raw arrays so the domain layer has zero knowledge
 * of HTTP or the ORM.
 */
final class CartItemDTO
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            quantity:  (int) $data['quantity'],
        );
    }
}
