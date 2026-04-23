<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository implements OrderRepositoryInterface
{
    public function paginateForUser(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return Order::query()
            ->with(['items.product', 'payment'])
            ->where('user_id', $userId)
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForUser(int $userId, int $orderId): ?Order
    {
        return Order::query()
            ->with(['items.product', 'payment'])
            ->where('user_id', $userId)
            ->whereKey($orderId)
            ->first();
    }

    public function create(array $data): Order
    {
        return Order::create($data);
    }
}
