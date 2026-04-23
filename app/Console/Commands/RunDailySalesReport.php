<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDailySalesReport;
use Illuminate\Console\Command;

class RunDailySalesReport extends Command
{
    protected $signature = 'commerce:daily-report {--date= : YYYY-MM-DD, defaults to yesterday}';
    protected $description = 'Dispatch the daily sales report batch job.';

    public function handle(): int
    {
        ProcessDailySalesReport::dispatch($this->option('date'))->onQueue('reports');
        $this->info('Daily sales report job dispatched to the `reports` queue.');
        return self::SUCCESS;
    }
}
