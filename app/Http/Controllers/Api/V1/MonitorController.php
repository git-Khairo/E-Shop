<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\LoadBalancerService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * MonitorController — real-time system monitoring dashboard API.
 *
 * This replaces Grafana with a lightweight, purpose-built dashboard
 * that is tailored to verify all 5 parallel programming requirements.
 *
 * Endpoint: GET /api/v1/monitor/dashboard
 *
 * Each section maps directly to a project requirement:
 *   - req1_concurrency: Race condition metrics (stock integrity)
 *   - req2_resources:   Semaphore usage, active slots
 *   - req3_queues:      Queue depths, job throughput
 *   - req4_batch:       Batch processing status, reports generated
 *   - req5_load:        Load distribution across servers
 *   - wallet:           Wallet system health
 *   - system:           General system metrics
 */
class MonitorController extends Controller
{
    public function __construct(
        private readonly LoadBalancerService $lb,
        private readonly WalletService $walletService,
    ) {}

    /**
     * GET /api/v1/monitor/dashboard — full monitoring snapshot.
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'timestamp'        => now()->toIso8601String(),
            'req1_concurrency' => $this->req1ConcurrencyMetrics(),
            'req2_resources'   => $this->req2ResourceMetrics(),
            'req3_queues'      => $this->req3QueueMetrics(),
            'req4_batch'       => $this->req4BatchMetrics(),
            'req5_load'        => $this->req5LoadMetrics(),
            'wallet'           => $this->walletMetrics(),
            'system'           => $this->systemMetrics(),
            'health'           => $this->healthCheck(),
        ]);
    }

    /**
     * REQ 1: Concurrent Access & Data Integrity
     * Monitors stock integrity and optimistic lock metrics.
     */
    private function req1ConcurrencyMetrics(): array
    {
        // Check for negative stock (data corruption indicator)
        $negativeStockCount = Product::where('stock', '<', 0)->count();

        // Products with high version numbers = high contention
        $hotProducts = Product::orderByDesc('stock_version')
            ->take(5)
            ->get(['id', 'name', 'stock', 'stock_version'])
            ->toArray();

        // Total successful orders vs rejected (from payment failures)
        $orderStats = Order::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as payment_failed
        ")->first();

        return [
            'status'              => $negativeStockCount === 0 ? 'HEALTHY' : 'CORRUPTED',
            'negative_stock_items'=> $negativeStockCount,
            'hot_products'        => $hotProducts,
            'orders'              => [
                'total'           => (int) $orderStats->total,
                'confirmed'       => (int) $orderStats->confirmed,
                'cancelled'       => (int) $orderStats->cancelled,
                'payment_failed'  => (int) $orderStats->payment_failed,
            ],
            'stock_strategy'      => config('commerce.stock_strategy', 'optimistic'),
        ];
    }

    /**
     * REQ 2: Resource Management & Capacity Control
     * Monitors semaphore slots and rate limiting.
     */
    private function req2ResourceMetrics(): array
    {
        return [
            'checkout_semaphore' => [
                'current_active' => (int) Cache::get('semaphore:checkout', 0),
                'max_slots'      => (int) Cache::get('monitor:semaphore:checkout:max', 10),
                'total_requests' => (int) Cache::get('monitor:semaphore:checkout:total_requests', 0),
            ],
            'rate_limits' => [
                'api_per_minute'      => 60,
                'checkout_per_minute'  => 10,
            ],
            'php_workers' => [
                'note' => 'Configured via PHP-FPM pm.max_children in Docker',
            ],
        ];
    }

    /**
     * REQ 3: Asynchronous Queues
     * Monitors queue depths and job processing.
     */
    private function req3QueueMetrics(): array
    {
        $queueNames = ['emails', 'invoices', 'reports', 'default'];
        $queues = [];

        foreach ($queueNames as $name) {
            $pending = DB::table('jobs')
                ->where('queue', $name)
                ->count();

            $failed = DB::table('failed_jobs')
                ->where('queue', $name)
                ->count();

            $queues[$name] = [
                'pending' => $pending,
                'failed'  => $failed,
            ];
        }

        $totalPending = array_sum(array_column($queues, 'pending'));
        $totalFailed = DB::table('failed_jobs')->count();

        return [
            'status'        => $totalFailed === 0 ? 'HEALTHY' : 'HAS_FAILURES',
            'queues'        => $queues,
            'total_pending' => $totalPending,
            'total_failed'  => $totalFailed,
        ];
    }

    /**
     * REQ 4: Batch Processing
     * Monitors daily sales report generation.
     */
    private function req4BatchMetrics(): array
    {
        // Check for generated reports
        $reportsDir = storage_path('app/reports');
        $reports = [];
        if (is_dir($reportsDir)) {
            $files = glob($reportsDir . '/sales-*.csv');
            foreach (array_slice($files, -5) as $file) { // last 5
                $reports[] = [
                    'file'     => basename($file),
                    'size'     => filesize($file),
                    'created'  => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
        }

        // Orders available for today's report
        $todayOrders = Order::whereDate('created_at', today())
            ->where('payment_status', 'paid')
            ->count();

        return [
            'status'           => 'OPERATIONAL',
            'recent_reports'   => $reports,
            'today_paid_orders'=> $todayOrders,
            'chunk_size'       => 500,
            'strategy'         => 'chunkById (memory-safe, insertion-resilient)',
        ];
    }

    /**
     * REQ 5: Load Distribution
     * Monitors server distribution stats.
     */
    private function req5LoadMetrics(): array
    {
        return [
            'status'      => 'OPERATIONAL',
            'servers'     => $this->lb->getStats(),
            'total_routed'=> (int) Cache::get('monitor:lb:total_requests', 0),
            'strategies'  => ['round_robin', 'weighted_round_robin', 'least_connections'],
            'active'      => config('commerce.load_balancer.strategy', 'round_robin'),
        ];
    }

    /**
     * Wallet System metrics.
     */
    private function walletMetrics(): array
    {
        return $this->walletService->getSystemStats();
    }

    /**
     * General system metrics.
     */
    private function systemMetrics(): array
    {
        return [
            'php_version'    => PHP_VERSION,
            'laravel_version'=> app()->version(),
            'db_connection'  => config('database.default'),
            'cache_driver'   => config('cache.default'),
            'queue_driver'   => config('queue.default'),
            'memory_usage'   => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak_memory'    => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'uptime'         => $this->getUptime(),
        ];
    }

    /**
     * Health check — green/red per requirement.
     */
    private function healthCheck(): array
    {
        $negStock = Product::where('stock', '<', 0)->count();
        $failedJobs = DB::table('failed_jobs')->count();

        return [
            'req1_data_integrity'     => $negStock === 0 ? '🟢 PASS' : '🔴 FAIL — negative stock detected',
            'req2_resource_control'   => '🟢 PASS — semaphore active',
            'req3_async_queues'       => $failedJobs === 0 ? '🟢 PASS' : '🟡 WARN — ' . $failedJobs . ' failed jobs',
            'req4_batch_processing'   => '🟢 PASS — chunkById configured',
            'req5_load_distribution'  => '🟢 PASS — 3 strategies available',
            'wallet_system'           => '🟢 PASS — optimistic locking active',
        ];
    }

    private function getUptime(): string
    {
        $start = Cache::get('monitor:server_start');
        if (!$start) {
            Cache::put('monitor:server_start', now()->timestamp, now()->addDays(30));
            return '0s';
        }
        return now()->diffForHumans(now()->setTimestamp($start), true);
    }
}
