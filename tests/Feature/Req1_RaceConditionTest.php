<?php

namespace Tests\Feature;

use App\Models\categories;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Req1_RaceConditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $category = categories::create(['type' => 'Test Category']);

        Product::create([
            'name'          => 'Test Widget',
            'slug'          => 'test-widget',
            'sku'           => 'TW-001',
            'price'         => 25.00,
            'image'         => 'test-widget.jpg',
            'stock'         => 10,
            'stock_version' => 0,
            'is_active'     => true,
            'categories_id' => $category->id,
            'amount'        => 0,
        ]);
    }

    public function test_BEFORE_race_condition_without_locking(): void
    {
        $product = Product::first();
        $this->assertEquals(10, $product->stock);

        $stockReadByA = (int) DB::table('products')->where('id', $product->id)->value('stock');

        $stockReadByB = (int) DB::table('products')->where('id', $product->id)->value('stock');

        $this->assertEquals(10, $stockReadByA);
        $this->assertEquals(10, $stockReadByB);

        DB::table('products')
            ->where('id', $product->id)
            ->update(['stock' => $stockReadByA - 5]);

        DB::table('products')
            ->where('id', $product->id)
            ->update(['stock' => $stockReadByB - 5]);

        $product->refresh();

        $this->assertEquals(5, $product->stock, 'Lost update: stock should be 0 but is 5');
        $this->assertNotEquals(0, $product->stock, 'Confirms the race condition exists');
    }

    public function test_AFTER_optimistic_locking_prevents_race_condition(): void
    {
        $product = Product::first();
        $this->assertEquals(10, $product->stock);

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

        $affectedB = DB::table('products')
            ->where('id', $product->id)
            ->where('stock_version', $readA->stock_version)
            ->where('stock', '>=', 5)
            ->update([
                'stock'         => DB::raw('stock - 5'),
                'stock_version' => DB::raw('stock_version + 1'),
            ]);
        $this->assertEquals(0, $affectedB, 'Thread B should fail due to version mismatch.');

        $readB2 = DB::table('products')->where('id', $product->id)->first();
        $this->assertEquals(5, $readB2->stock);
        $this->assertEquals(1, $readB2->stock_version);

        $affectedB2 = DB::table('products')
            ->where('id', $product->id)
            ->where('stock_version', $readB2->stock_version)
            ->where('stock', '>=', 5)
            ->update([
                'stock'         => DB::raw('stock - 5'),
                'stock_version' => DB::raw('stock_version + 1'),
            ]);
        $this->assertEquals(1, $affectedB2, 'Thread B retry succeeds with fresh version.');

        $product->refresh();
        $this->assertEquals(0, $product->stock, 'Stock correctly decremented to 0.');
        $this->assertEquals(2, $product->stock_version);
    }

    public function test_AFTER_pessimistic_locking_prevents_race_condition(): void
    {
        $product = Product::first();
        $this->assertEquals(10, $product->stock);

        DB::transaction(function () use ($product) {
            $locked = Product::where('id', $product->id)->lockForUpdate()->first();
            $this->assertEquals(10, $locked->stock);
            $locked->stock -= 5;
            $locked->stock_version += 1;
            $locked->save();
        });

        DB::transaction(function () use ($product) {
            $locked = Product::where('id', $product->id)->lockForUpdate()->first();
            $this->assertEquals(5, $locked->stock, 'Sees committed stock from first transaction.');
            $locked->stock -= 5;
            $locked->stock_version += 1;
            $locked->save();
        });

        $product->refresh();
        $this->assertEquals(0, $product->stock, 'Stock is 0 with pessimistic locking.');
    }

    public function test_AFTER_optimistic_prevents_overselling(): void
    {
        $product = Product::first();
        $product->update(['stock' => 3, 'stock_version' => 0]);

        $affected = DB::table('products')
            ->where('id', $product->id)
            ->where('stock_version', 0)
            ->where('stock', '>=', 5)
            ->update([
                'stock'         => DB::raw('stock - 5'),
                'stock_version' => DB::raw('stock_version + 1'),
            ]);

        $this->assertEquals(0, $affected, 'Cannot oversell.');
        $product->refresh();
        $this->assertEquals(3, $product->stock, 'Stock unchanged after rejected attempt.');
    }
}
