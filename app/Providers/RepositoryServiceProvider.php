<?php

namespace App\Providers;

use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\CartRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Services\PaymentService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(OrderRepositoryInterface::class,   OrderRepository::class);
        $this->app->bind(CartRepositoryInterface::class,    CartRepository::class);

        $this->app->singleton(PaymentService::class, function () {
            $driver = config('commerce.payment_driver', 'mock');
            return new PaymentService(
                driver:       $driver,
                stripeSecret: config('services.stripe.secret'),
            );
        });
    }
}
