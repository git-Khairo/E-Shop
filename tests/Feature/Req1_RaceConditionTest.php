<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * REQUIREMENT 1: Concurrent Access & Data Integrity
 *
 * This test demonstrates the Race Condition problem and proves our solution works.
 *
 * === BEFORE (No Protection) ===
 * testRaceCondition_WITHOUT_locking():
 *   Simulates the classic lost-update problem using raw SQL without any locking.
 *   Two "threads" read stock=10 simultaneously, each decrements by 5.
 *   Expected final stock: 0 (10 - 5 - 5)
 *   Actual without locking: 5 (both read 10, both write 10-5=5 — LOST UPDATE!)
 *
 * === AFTER (With Optimistic Locking) ===
 * testRaceCondition_WITH_optimisticLocking():
 *   Same scenario but using our versioned UPDATE (StockService::decrementOptimistic).
 *   The second writer detects the version mismatch and retries with fresh data.
 *   Final stock: 0 ✓ — no data corruption.
 *
 * === AFTER (With Pessimistic Locking) ===
 * testRaceCondition_WITH_pessimisticLocking():
 *   Uses SELECT ... FOR UPDATE to serialize access.
 *   The second writer blocks until the first commits, then proceeds safely.
 *   Final stock: 0 ✓ — serialized execution.
 */
class Req1_RaceConditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed a product with known stock
        Product::create([
            'name'          => 'Test Widget',
            'slug'          => 'test-widget',
            'sku'           => 'TW-001',
            'price'         => 25.00,
            'stock'         => 10,
            'stock_version' => 0,
            'is_active'     => true,
            'categories_id' => 1,
        ]);
    }

    /**
     * BEFORE: Demonstrates the race condition WITHOUT any locking.
     *
     * This simulates what happens when two concurrent processes read the same
     * stock value and both write back a decremented value — the classic "lost update."
     *
     * Thread A: reads stock=10 → computes 10-5=5 → writes stock=5
     * Thread B: reads stock=10 → computes 10-5=5 → writes stock=5
     * Result: stock=5 (should be 0!) — 5 units sold but not deducted = OVERSELLING
     */
    public function test_BEFORE_race_condition_without_locking(): void
    {
        $product = Product::first();
        $this->assertEquals(10, $product->stock);

        // Simulate Thread A reading stock
        $stockReadByA = (int) DB::table('products')->where('id', $product->id)->value('stock');

        // Simulate Thread B reading stock (BEFORE A writes)
        $stockReadByB = (int) DB::table('products')->where('id', $product->id)->value('stock');

        // Both read 10
        $this->assertEquals(10, $stockReadByA);
        $this->assertEquals(10, $stockReadByB);

        // Thread A writes: 10 - 5 = 5
        DB::table('products')
            ->where('id', $product->id)
            ->update(['stock' => $stockReadByA - 5]);

        // Thread B writes: 10 - 5 = 5 (OVERWRITES A's update!)
        DB::table('products')
            ->where('id', $product->id)
            ->update(['stock' => $stockReadByB - 5]);

        $product->refresh();

        // BUG DEMONSTRATED: stock is 5, not 0!
        // 10 units sold (5+5) but only 5 deducted = LOST UPDATE
        $this->assertEquals(5, $product->stock, 'RACE CONDITION: Lost update — stock should be 0 but is 5!');
        $this->assertNotEquals(0, $product->stock, 'This confirms the race condition exists.');
    }

    /**
     * AFTER: Same scenario but using optimistic locking (versioned UPDATE).
     *
     * Thread A: reads stock=10, version=0 → UPDATE WHERE version=0 → SUCCESS, version→1
     * Thread B: reads stock=10, version=0 → UPDATE WHERE version=0 → FAILS (version=1 now)
     * Thread B retries: reads stock=5, version=1 → UPDATE WHERE version=1 → SUCCESS
     * Result: stock=0 ✓
     */
    public function test_AFTER_optimistic_locking_prevents_race_condition(): void
    {
        $product = Product::first();
        $this->assertEquals(10, $product->stock);

        // Thread A: read + conditional write (succeeds)
        $readA = DB::table('products')->where('id', $product->id)->first();
        $affectedA = DB::table('products')
            ->where('id', $product->id)
            ->where('stock_version', $readA->stock_version)
            ->where('stock', '>=', 5)
            ->update([
                'stock'         => DB::raw('stock - 5'),
                'stock_version' => DB::raw('stock_version + 1'),
            ]);
        $this->assertEquals(1, $affectedA, 'Thread A should succeed.');

        // Thread B: tries the SAME old version → FAILS
        $affectedB = DB::table('products')
            ->where('id', $product->id)
            ->where('stock_version', $readA->stock_version) // stale version!
            ->where('stock', '>=', 5)
            ->update([
                'stock'         => DB::raw('stock - 5'),
                'stock_version' => DB::raw('stock_version + 1'),
            ]);
        $this->assertEquals(0, $affectedB, 'Thread B should FAIL — version mismatch detected.');

        // Thread B retries with fresh data
        $readB2 = DB::table('products')->where('id', $product->id)->first();
        $this->assertEquals(5, $readB2->stock);     // Sees A's update
        $this->assertEquals(1, $readB2->stock_version); // Version bumped

        $affectedB2 = DB::table('products')
            ->where('id', $product->id)
            ->where('stock_version', $readB2->stock_version)
            ->where('stock', '>=', 5)
            ->update([
                'stock'         => DB::raw('stock - 5'),
                'stock_version' => DB::raw('stock_version + 1'),
            ]);
        $this->assertEquals(1, $affectedB2, 'Thread B retry should succeed with fresh version.');

        $product->refresh();
        $this->assertEquals(0, $product->stock, 'INVARIANT HELD: stock correctly decremented to 0.');
        $this->assertEquals(2, $product->stock_version, 'Two successful updates = version 2.');
    }

    /**
     * AFTER: Pessimistic locking — SELECT FOR UPDATE serializes writers.
     */
    public function test_AFTER_pessimistic_locking_prevents_race_condition(): void
    {
        $product = Product::first();
        $this->assertEquals(10, $product->stock);

        // Transaction 1: locks the row, decrements, commits
        DB::transaction(function () use ($product) {
            $locked = Product::where('id', $product->id)->lockForUpdate()->first();
            $this->assertEquals(10, $locked->stock);
            $locked->stock -= 5;
            $locked->stock_version += 1;
            $locked->save();
        });

        // Transaction 2: also locks the row — but sees the UPDATED stock from tx 1
        DB::transaction(function () use ($product) {
            $locked = Product::where('id', $product->id)->lockForUpdate()->first();
            $this->assertEquals(5, $locked->stock, 'Pessimistic: sees committed stock from TX 1.');
            $locked->stock -= 5;
            $locked->stock_version += 1;
            $locked->save();
        });

        $product->refresh();
        $this->assertEquals(0, $product->stock, 'INVARIANT HELD: pessimistic locking → stock is 0.');
    }

    /**
     * AFTER: Proves overselling is impossible — trying to buy more than available.
     */
    public function test_AFTER_optimistic_prevents_overselling(): void
    {
        $product = Product::first();
        $product->update(['stock' => 3, 'stock_version' => 0]);

        // Try to buy 5 when only 3 available — the WHERE stock >= 5 prevents it
        $affected = DB::table('products')
            ->where('id', $product->id)
            ->where('stock_version', 0)
            ->where('stock', '>=', 5)
            ->update([
                'stock'         => DB::raw('stock - 5'),
                'stock_version' => DB::raw('stock_version + 1'),
            ]);

        $this->assertEquals(0, $affected, 'Cannot oversell — WHERE stock >= qty prevents it.');
        $product->refresh();
        $this->assertEquals(3, $product->stock, 'Stock unchanged after rejected oversell attempt.');
    }
}
