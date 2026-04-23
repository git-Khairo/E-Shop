<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade the products table to support concurrency-safe stock handling.
 *
 *  - `stock` replaces the legacy `amount` column and is guarded with a NON-NEGATIVE check.
 *  - `stock_version` is a classic Optimistic Concurrency Control (OCC) version number.
 *    Every UPDATE that modifies `stock` MUST include `WHERE stock_version = :v`
 *    and bump it by 1. If two transactions race, only one wins.
 *  - `sku`, `description`, `slug` give us a real product model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'stock')) {
                $table->unsignedInteger('stock')->default(0)->after('image');
            }
            if (! Schema::hasColumn('products', 'stock_version')) {
                $table->unsignedBigInteger('stock_version')->default(0)->after('stock');
            }
            if (! Schema::hasColumn('products', 'sku')) {
                $table->string('sku')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('products', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('stock_version');
            }
        });

        // Backfill `stock` from the legacy `amount` column if it still exists.
        if (Schema::hasColumn('products', 'amount')) {
            \DB::statement('UPDATE products SET stock = amount WHERE stock = 0');
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock', 'stock_version', 'sku', 'slug', 'description', 'is_active']);
        });
    }
};
