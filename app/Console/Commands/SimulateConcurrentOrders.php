<?php

namespace App\Console\Commands;

use App\Domain\DTOs\CartItemDTO;
use App\Domain\DTOs\CheckoutDTO;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PaymentFailedException;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * LOAD SIMULATION — proves the concurrency logic actually prevents overselling.
 *
 * Usage:
 *   php artisan commerce:simulate-orders --product=1 --stock=50 --buyers=200 --qty=1
 *
 * The command will:
 *   1. Reset the product stock to --stock
 *   2. Fork --buyers pcntl processes (or run sequentially if pcntl not available),
 *      each attempting to buy --qty units concurrently.
 *   3. Print a report: successful orders, stock-out rejections, final stock.
 *
 * Invariant: successful_orders * qty + final_stock === initial_stock.
 * If this invariant ever fails -> there is a concurrency bug.
 */
class SimulateConcurrentOrders extends Command
{
    protected $signature = 'commerce:simulate-orders
        {--product=1 : Product ID to hammer}
        {--stock=50 : Initial stock to set}
        {--buyers=100 : Concurrent buyers to spawn}
        {--qty=1 : Units each buyer attempts to purchase}
        {--user= : User id to place orders as (default: first user)}';

    protected $description = 'Simulate N concurrent checkouts against a single product.';

    public function handle(OrderService $service): int
    {
        $productId = (int) $this->option('product');
        $stock     = (int) $this->option('stock');
        $buyers    = (int) $this->option('buyers');
        $qty       = (int) $this->option('qty');
        $userId    = (int) ($this->option('user') ?: optional(User::first())->id);

        if (! $userId) {
            $this->error('No user in DB. Seed a user first.');
            return self::FAILURE;
        }

        $product = Product::find($productId);
        if (! $product) {
            $this->error("Product #{$productId} not found.");
            return self::FAILURE;
        }

        $product->update([
            'stock'         => $stock,
            'stock_version' => 0,
            'is_active'     => true,
        ]);

        $this->info("Starting simulation: {$buyers} concurrent buyers x {$qty} units, initial stock = {$stock}");
        $this->info("Strategy: ".config('commerce.stock_strategy', 'optimistic'));

        $start = microtime(true);

        if (! function_exists('pcntl_fork')) {
            $this->warn('pcntl not available — falling back to SEQUENTIAL run (no real concurrency).');
            $results = $this->runSequential($service, $userId, $productId, $qty, $buyers);
        } else {
            $results = $this->runForked($service, $userId, $productId, $qty, $buyers);
        }

        $elapsed = round(microtime(true) - $start, 3);

        $product->refresh();
        $success = $results['success'];
        $oos     = $results['oos'];
        $other   = $results['other'];

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Initial stock', $stock],
            ['Final stock',  $product->stock],
            ['Successful orders', $success],
            ['Out-of-stock rejections', $oos],
            ['Other errors', $other],
            ['Elapsed (s)', $elapsed],
            ['Throughput (orders/s)', $success > 0 ? round($success / max($elapsed, 0.001), 1) : 0],
        ]);

        $expected = $stock - ($success * $qty);
        if ($expected === $product->stock) {
            $this->info("INVARIANT HELD: successful * qty + final_stock === initial_stock ({$product->stock} == {$expected})");
        } else {
            $this->error("INVARIANT BROKEN! expected final stock {$expected}, got {$product->stock}. RACE CONDITION DETECTED.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function runSequential(OrderService $service, int $userId, int $productId, int $qty, int $n): array
    {
        $success = $oos = $other = 0;
        for ($i = 0; $i < $n; $i++) {
            [$s, $o, $x] = $this->placeOne($service, $userId, $productId, $qty);
            $success += $s; $oos += $o; $other += $x;
        }
        return ['success' => $success, 'oos' => $oos, 'other' => $other];
    }

    private function runForked(OrderService $service, int $userId, int $productId, int $qty, int $n): array
    {
        $children = [];
        $pipes    = [];

        for ($i = 0; $i < $n; $i++) {
            $sockets = [];
            socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);
            $pid = pcntl_fork();

            if ($pid === 0) {
                // CHILD
                socket_close($sockets[0]);
                [$s, $o, $x] = $this->placeOne($service, $userId, $productId, $qty);
                socket_write($sockets[1], "{$s},{$o},{$x}", 32);
                socket_close($sockets[1]);
                exit(0);
            }

            // PARENT
            socket_close($sockets[1]);
            $children[] = $pid;
            $pipes[]    = $sockets[0];
        }

        $success = $oos = $other = 0;
        foreach ($pipes as $sock) {
            $raw = trim(socket_read($sock, 32, PHP_BINARY_READ));
            [$s, $o, $x] = array_map('intval', array_pad(explode(',', $raw), 3, 0));
            $success += $s; $oos += $o; $other += $x;
            socket_close($sock);
        }
        foreach ($children as $pid) pcntl_waitpid($pid, $status);

        return ['success' => $success, 'oos' => $oos, 'other' => $other];
    }

    private function placeOne(OrderService $service, int $userId, int $productId, int $qty): array
    {
        try {
            $service->checkout(new CheckoutDTO(
                userId: $userId,
                items:  [new CartItemDTO($productId, $qty)],
                paymentMethodToken: 'mock_success',
            ));
            return [1, 0, 0];
        } catch (InsufficientStockException) {
            return [0, 1, 0];
        } catch (PaymentFailedException) {
            return [0, 0, 1];
        } catch (\Throwable $e) {
            return [0, 0, 1];
        }
    }
}
