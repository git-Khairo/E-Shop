<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface CartRepositoryInterface
{

    public function forUser(int $userId): Collection;

    public function upsert(int $userId, int $productId, int $quantity, float $unitPrice): void;

    public function setQuantity(int $userId, int $productId, int $quantity, float $unitPrice): int;

    public function remove(int $userId, int $productId): void;

    public function clear(int $userId): void;
}
