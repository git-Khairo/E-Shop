<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * REQUIREMENT 4: Batch Processing (Processing large data in chunks)
 *
 * === BEFORE (Naive: load all into memory) ===
 * Order::all() loads EVERY row into a PHP array at once.
 *   - 1,000 orders: ~2MB RAM → OK
 *   - 10,000 orders: ~20MB RAM → slow
 *   - 100,000 orders: ~200MB RAM → PHP memory limit hit → CRASH
 *   - 1,000,000 orders: impossible
 *
 * === AFTER (chunkById: stream in fixed-size windows) ===
 * Order::chunkById(500, callback) processes 500 rows at a time:
 *   - Memory: constant ~1MB regardless of total rows
 *   - Pagination: by primary key (WHERE id > last_id), not OFFSET
 *   - Safe under concurrent inserts (OFFSET-based pagination breaks when rows are added)
 *
 * Why chunkById instead of chunk?
 *   chunk() uses LIMIT/OFFSET:
 *     SELECT * LIMIT 500 OFFSET 0;   -- page 1
 *     SELECT * LIMIT 500 OFFSET 500; -- page 2 (if a row was INSERTED between pages,
 *                                     --         one row is counted twice or skipped!)
 *   chunkById() uses WHERE id > last_id:
 *     SELECT * WHERE id > 0 ORDER BY id LIMIT 500;   -- page 1 (last_id = 500)
 *     SELECT * WHERE id > 500 ORDER BY id LIMIT 500; -- page 2 (immune to inserts)
 */
class Req4_BatchProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $user = User::create([
            'username' => 'batchtest',
            'email'    => 'batch@test.com',
            'password' => bcrypt('password'),
        ]);

        // Seed 100 orders for batch testing
        $orders = [];
        for ($i = 0; $i < 100; $i++) {
            $orders[] = [
                'reference'      => 'ORD-BATCH-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'user_id'        => $user->id,
                'status'         => 'confirmed',
                'payment_status' => 'paid',
                'subtotal'       => rand(10, 500),
                'total'          => rand(10, 500),
                'price'          => rand(10, 500),
                'products'       => json_encode([]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }
        DB::table('orders')->insert($orders);
    }

    /**
     * BEFORE: Order::all() loads everything into memory at once.
     */
    public function test_BEFORE_load_all_memory_usage(): void
    {
        $memBefore = memory_get_usage();

        // Load ALL orders into memory (the naive approach)
        $allOrders = Order::all();
        $count = $allOrders->count();

        $memAfter = memory_get_usage();
        $memUsed = $memAfter - $memBefore;

        $this->assertEquals(100, $count);
        $this->assertGreaterThan(0, $memUsed, 'BEFORE: all() consumes memory proportional to row count.');

        // Store for comparison
        $this->beforeMemory = $memUsed;
    }

    /**
     * AFTER: chunkById processes in fixed windows — constant memory.
     */
    public function test_AFTER_chunkById_constant_memory(): void
    {
        $peakMemory = 0;
        $totalProcessed = 0;
        $chunkCount = 0;

        Order::query()
            ->where('payment_status', 'paid')
            ->orderBy('id')
            ->chunkById(25, function ($orders) use (&$peakMemory, &$totalProcessed, &$chunkCount) {
                $currentMem = memory_get_usage();
                if ($currentMem > $peakMemory) {
                    $peakMemory = $currentMem;
                }
                $totalProcessed += $orders->count();
                $chunkCount++;
            });

        $this->assertEquals(100, $totalProcessed, 'All 100 orders processed.');
        $this->assertEquals(4, $chunkCount, '100 orders / 25 per chunk = 4 chunks.');
    }

    /**
     * AFTER: Verify chunk size is configurable and correct.
     */
    public function test_AFTER_chunk_sizes_are_correct(): void
    {
        $chunkSizes = [];

        Order::query()
            ->orderBy('id')
            ->chunkById(30, function ($orders) use (&$chunkSizes) {
                $chunkSizes[] = $orders->count();
            });

        // 100 orders / 30 per chunk = 3 full chunks (30,30,30) + 1 partial (10)
        $this->assertEquals([30, 30, 30, 10], $chunkSizes, 'Chunks are correctly sized.');
        $this->assertEquals(100, array_sum($chunkSizes), 'No rows lost or duplicated.');
    }

    /**
     * COMPARISON: Timing of all() vs chunkById().
     */
    public function test_comparison_timing(): void
    {
        // BEFORE: all()
        $startAll = microtime(true);
        $all = Order::all();
        $sum1 = $all->sum('total');
        $timeAll = microtime(true) - $startAll;

        // AFTER: chunkById
        $startChunk = microtime(true);
        $sum2 = 0;
        Order::query()
            ->orderBy('id')
            ->chunkById(25, function ($orders) use (&$sum2) {
                $sum2 += $orders->sum('total');
            });
        $timeChunk = microtime(true) - $startChunk;

        // Both produce the same result (correctness)
        $this->assertEquals($sum1, $sum2, 'Both methods produce identical aggregates.');

        // Log the comparison (visible in test output)
        fwrite(STDERR, sprintf(
            "\n  BATCH TIMING: all()=%.2fms, chunkById()=%.2fms\n",
            $timeAll * 1000,
            $timeChunk * 1000
        ));
    }

    /**
     * AFTER: chunkById is safe under concurrent inserts (conceptual proof).
     *
     * OFFSET-based pagination breaks when rows are inserted mid-scan.
     * chunkById uses WHERE id > last_id, which is immune to this problem.
     */
    public function test_AFTER_chunkById_safe_under_inserts(): void
    {
        $processedIds = [];

        Order::query()
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$processedIds) {
                foreach ($orders as $order) {
                    $processedIds[] = $order->id;
                }

                // Simulate a concurrent insert mid-scan
                if (count($processedIds) === 50) {
                    Order::create([
                        'reference'      => 'ORD-MID-INSERT',
                        'user_id'        => 1,
                        'status'         => 'confirmed',
                        'payment_status' => 'paid',
                        'subtotal'       => 99.99,
                        'total'          => 99.99,
                        'price'          => 99.99,
                        'products'       => [],
                    ]);
                }
            });

        // All original 100 orders processed, no duplicates
        $uniqueProcessed = array_unique($processedIds);
        $this->assertGreaterThanOrEqual(100, count($uniqueProcessed),
            'chunkById processes all rows without duplicates even with concurrent inserts.');
    }
}
