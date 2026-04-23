<?php

use App\Jobs\ProcessDailySalesReport;
use Illuminate\Support\Facades\Schedule;

/**
 * Schedule: run the daily sales report every night at 01:00.
 * This is where BATCH PROCESSING plugs into the scheduler.
 */
Schedule::job(new ProcessDailySalesReport(), 'reports')
    ->dailyAt('01:00')
    ->withoutOverlapping();
