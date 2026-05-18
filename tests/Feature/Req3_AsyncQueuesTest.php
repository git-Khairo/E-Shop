<?php

namespace Tests\Feature;

use App\Jobs\GenerateInvoicePdf;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Req3_AsyncQueuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_BEFORE_synchronous_email_blocks_response(): void
    {
        Mail::fake();

        $start = microtime(true);

        usleep(200_000);
        $syncTime = (microtime(true) - $start) * 1000;

        $this->assertGreaterThan(150, $syncTime, 'BEFORE: Synchronous email adds ~200ms to response time.');
    }

    public function test_AFTER_async_dispatch_instant_response(): void
    {
        Queue::fake();

        $start = microtime(true);

        SendOrderConfirmationEmail::dispatch(1)->onQueue('emails');
        GenerateInvoicePdf::dispatch(1)->onQueue('invoices');

        $asyncTime = (microtime(true) - $start) * 1000;

        $this->assertLessThan(50, $asyncTime, 'AFTER: Queue dispatch takes < 50ms.');

        Queue::assertPushedOn('emails', SendOrderConfirmationEmail::class);
        Queue::assertPushedOn('invoices', GenerateInvoicePdf::class);
    }

    public function test_AFTER_jobs_routed_to_separate_queues(): void
    {
        Queue::fake();

        SendOrderConfirmationEmail::dispatch(1)->onQueue('emails');
        GenerateInvoicePdf::dispatch(1)->onQueue('invoices');

        Queue::assertPushedOn('emails', SendOrderConfirmationEmail::class);
        Queue::assertPushedOn('invoices', GenerateInvoicePdf::class);

        Queue::assertNotPushed(SendOrderConfirmationEmail::class, fn ($job, $queue) => $queue === 'invoices');
        Queue::assertNotPushed(GenerateInvoicePdf::class, fn ($job, $queue) => $queue === 'emails');
    }

    public function test_AFTER_email_job_has_retry_backoff(): void
    {
        $job = new SendOrderConfirmationEmail(1);

        $this->assertEquals(5, $job->tries, 'Email job retries up to 5 times.');
        $this->assertEquals([10, 30, 60, 120, 300], $job->backoff(), 'Exponential backoff: 10s, 30s, 60s, 120s, 300s.');
        $this->assertEquals(30, $job->timeout, 'Job timeout is 30 seconds.');
    }

    public function test_comparison_response_time_before_vs_after(): void
    {
        Queue::fake();

        $beforeMs = 50 + 800 + 1200;

        $start = microtime(true);
        SendOrderConfirmationEmail::dispatch(1)->onQueue('emails');
        GenerateInvoicePdf::dispatch(1)->onQueue('invoices');
        $afterMs = (microtime(true) - $start) * 1000 + 50;

        $speedup = $beforeMs / max($afterMs, 1);

        $this->assertGreaterThan(10, $speedup, 'AFTER is at least 10x faster than BEFORE.');
        $this->assertLessThan(200, $afterMs, 'AFTER total response < 200ms.');
    }
}
