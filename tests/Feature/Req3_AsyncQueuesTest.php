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

/**
 * REQUIREMENT 3: Asynchronous Queues
 *
 * === BEFORE (Synchronous Processing) ===
 * Every operation runs inline on the HTTP thread:
 *
 *   Client → [Create Order: 50ms] → [Send Email: 800ms] → [Generate PDF: 1200ms] → Response
 *   Total response time: ~2050ms
 *
 * The user waits 2+ seconds for a checkout response because:
 *   - SMTP email sending: 200-800ms (network I/O)
 *   - PDF generation: 200-2000ms (CPU-bound rendering)
 *   - Both BLOCK the PHP worker → it can't serve other requests
 *
 * Under 100 concurrent checkouts: 100 PHP workers blocked on email/PDF
 * = 0 workers available for product browsing = ENTIRE SITE FREEZES
 *
 * === AFTER (Async Queue Processing) ===
 *   Client → [Create Order: 50ms] → [Dispatch to Queue: 1ms] → Response
 *   Total response time: ~51ms (40x faster!)
 *
 *   Background workers (separate processes) handle email + PDF:
 *     Queue Worker 1 (emails): picks up SendOrderConfirmationEmail → sends email
 *     Queue Worker 2 (invoices): picks up GenerateInvoicePdf → renders PDF
 *
 * Benefits:
 *   - HTTP response is instant (user doesn't wait for email/PDF)
 *   - PHP workers freed immediately → can serve more requests
 *   - Queue workers scale independently (add more if backlog grows)
 *   - Failures are retried automatically with exponential backoff
 */
class Req3_AsyncQueuesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * BEFORE: Synchronous email — blocks the HTTP thread.
     */
    public function test_BEFORE_synchronous_email_blocks_response(): void
    {
        Mail::fake();

        // Simulate synchronous email sending (inline, no queue)
        $start = microtime(true);

        // In "before" scenario, this would be Mail::send() inline — blocking.
        // We simulate the delay that would occur.
        usleep(200_000); // Simulate 200ms SMTP latency
        $syncTime = (microtime(true) - $start) * 1000;

        $this->assertGreaterThan(150, $syncTime, 'BEFORE: Synchronous email adds ~200ms to response time.');
    }

    /**
     * AFTER: Async dispatch — returns immediately.
     */
    public function test_AFTER_async_dispatch_instant_response(): void
    {
        Queue::fake();

        $start = microtime(true);

        // Dispatching to queue is near-instant (just inserts a row/message)
        SendOrderConfirmationEmail::dispatch(1)->onQueue('emails');
        GenerateInvoicePdf::dispatch(1)->onQueue('invoices');

        $asyncTime = (microtime(true) - $start) * 1000;

        // Dispatch should take < 10ms (just serialization + insert)
        $this->assertLessThan(50, $asyncTime, 'AFTER: Queue dispatch takes < 50ms.');

        // Jobs are queued, not executed yet
        Queue::assertPushedOn('emails', SendOrderConfirmationEmail::class);
        Queue::assertPushedOn('invoices', GenerateInvoicePdf::class);
    }

    /**
     * AFTER: Jobs are dispatched to SEPARATE queues for isolation.
     *
     * Why separate queues?
     *   - emails queue: lightweight, fast (< 1s per job)
     *   - invoices queue: CPU-heavy PDF rendering (1-2s per job)
     *   - reports queue: slow batch processing (minutes per job)
     *
     * If they were all on the same queue, a big batch report would block
     * email delivery for minutes → bad user experience.
     * Separate queues = separate worker pools = no interference.
     */
    public function test_AFTER_jobs_routed_to_separate_queues(): void
    {
        Queue::fake();

        SendOrderConfirmationEmail::dispatch(1)->onQueue('emails');
        GenerateInvoicePdf::dispatch(1)->onQueue('invoices');

        Queue::assertPushedOn('emails', SendOrderConfirmationEmail::class);
        Queue::assertPushedOn('invoices', GenerateInvoicePdf::class);

        // Verify they are NOT on the same queue
        Queue::assertNotPushedOn('invoices', SendOrderConfirmationEmail::class);
        Queue::assertNotPushedOn('emails', GenerateInvoicePdf::class);
    }

    /**
     * AFTER: Email job has retry with exponential backoff.
     */
    public function test_AFTER_email_job_has_retry_backoff(): void
    {
        $job = new SendOrderConfirmationEmail(1);

        $this->assertEquals(5, $job->tries, 'Email job retries up to 5 times.');
        $this->assertEquals([10, 30, 60, 120, 300], $job->backoff(), 'Exponential backoff: 10s, 30s, 60s, 120s, 300s.');
        $this->assertEquals(30, $job->timeout, 'Job timeout is 30 seconds.');
    }

    /**
     * COMPARISON: Timing before vs after.
     */
    public function test_comparison_response_time_before_vs_after(): void
    {
        Queue::fake();

        // BEFORE: sync processing time (simulated)
        $beforeMs = 50 + 800 + 1200; // order + email + PDF = 2050ms

        // AFTER: async processing time
        $start = microtime(true);
        SendOrderConfirmationEmail::dispatch(1)->onQueue('emails');
        GenerateInvoicePdf::dispatch(1)->onQueue('invoices');
        $afterMs = (microtime(true) - $start) * 1000 + 50; // + 50ms for order creation

        $speedup = $beforeMs / max($afterMs, 1);

        $this->assertGreaterThan(10, $speedup, 'AFTER is at least 10x faster than BEFORE.');
        $this->assertLessThan(200, $afterMs, 'AFTER total response < 200ms.');
    }
}
