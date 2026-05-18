<?php

namespace App\Console\Commands;

use App\Services\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BenchmarkProductListing extends Command
{
    protected $signature = 'commerce:benchmark {--runs=100}';
    protected $description = 'Benchmark product listing with/without cache.';

    public function handle(ProductService $service): int
    {
        $runs = (int) $this->option('runs');
        $this->info("Running {$runs} iterations per phase...");

        $queriesA = 0;
        DB::listen(function () use (&$queriesA) { $queriesA++; });

        $startA = microtime(true);
        for ($i = 0; $i < $runs; $i++) {
            Cache::flush();
            $service->list([], 20);
        }
        $elapsedA = microtime(true) - $startA;

        Cache::flush();
        $service->list([], 20);

        $queriesB = 0;
        DB::listen(function () use (&$queriesB) { $queriesB++; });

        $startB = microtime(true);
        for ($i = 0; $i < $runs; $i++) {
            $service->list([], 20);
        }
        $elapsedB = microtime(true) - $startB;

        $this->newLine();
        $this->table(['Phase', 'Total time (s)', 'Avg per req (ms)', 'DB queries'], [
            ['A — cold cache', round($elapsedA, 3), round(($elapsedA / $runs) * 1000, 2), $queriesA],
            ['B — warm cache', round($elapsedB, 3), round(($elapsedB / $runs) * 1000, 2), $queriesB],
        ]);

        $speedup = $elapsedB > 0 ? round($elapsedA / $elapsedB, 1) : 0;
        $this->info("Cache speedup: {$speedup}x");

        return self::SUCCESS;
    }
}
