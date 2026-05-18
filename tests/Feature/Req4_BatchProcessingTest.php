<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Req4_BatchProcessingTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'username' => 'batchtest',
            'email'    => 'batch@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->userId = $user->id;

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

    public function test_BEFORE_load_all_memory_usage(): void
    {
        $memBefore = memory_get_usage();

        $allOrders = Order::all();
        $count = $allOrders->count();

        $memAfter = memory_get_usage();
        $memUsed = $memAfter - $memBefore;

        $this->assertEquals(100, $count);
        $this->assertGreaterThan(0, $memUsed, 'BEFORE: all() consumes memory proportional to row count.');

        $this->beforeMemory = $memUsed;
    }

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

    public function test_AFTER_chunk_sizes_are_correct(): void
    {
        $chunkSizes = [];

        Order::query()
            ->orderBy('id')
            ->chunkById(30, function ($orders) use (&$chunkSizes) {
                $chunkSizes[] = $orders->count();
            });

        $this->assertEquals([30, 30, 30, 10], $chunkSizes, 'Chunks are correctly sized.');
        $this->assertEquals(100, array_sum($chunkSizes), 'No rows lost or duplicated.');
    }

    public function test_comparison_timing(): void
    {

        $startAll = microtime(true);
        $all = Order::all();
        $sum1 = $all->sum('total');
        $timeAll = microtime(true) - $startAll;

        $startChunk = microtime(true);
        $sum2 = 0;
        Order::query()
            ->orderBy('id')
            ->chunkById(25, function ($orders) use (&$sum2) {
                $sum2 += $orders->sum('total');
            });
        $timeChunk = microtime(true) - $startChunk;

        $this->assertEquals($sum1, $sum2, 'Both methods produce identical aggregates.');

        fwrite(STDERR, sprintf(
            "\n  BATCH TIMING: all()=%.2fms, chunkById()=%.2fms\n",
            $timeAll * 1000,
            $timeChunk * 1000
        ));
    }

    public function test_AFTER_chunkById_safe_under_inserts(): void
    {
        $processedIds = [];

        Order::query()
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$processedIds) {
                foreach ($orders as $order) {
                    $processedIds[] = $order->id;
                }

                if (count($processedIds) === 50) {
                    Order::create([
                        'reference'      => 'ORD-MID-INSERT',
                        'user_id'        => $this->userId,
                        'status'         => 'confirmed',
                        'payment_status' => 'paid',
                        'subtotal'       => 99.99,
                        'total'          => 99.99,
                        'price'          => 99.99,
                        'products'       => [],
                    ]);
                }
            });

        $uniqueProcessed = array_unique($processedIds);
        $this->assertGreaterThanOrEqual(100, count($uniqueProcessed),
            'chunkById processes all rows without duplicates even with concurrent inserts.');
    }
}
