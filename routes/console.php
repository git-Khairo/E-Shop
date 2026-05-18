<?php

use App\Jobs\ProcessDailySalesReport;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ProcessDailySalesReport(), 'reports')
    ->dailyAt('01:00')
    ->withoutOverlapping();
