<?php

namespace App\Jobs;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * ASYNC PROCESSING demonstration.
 *
 * Sending an email takes 200–800ms in production (SMTP / API latency).
 * We never do this inline on the HTTP thread — it would tie up a PHP worker
 * that could be serving another customer. Instead the controller dispatches
 * this job to the `emails` queue and returns immediately.
 *
 * The job:
 *   - Is idempotent (safe to retry).
 *   - Has bounded retries + backoff so a flaky SMTP doesn't cause an infinite loop.
 *   - Can be processed by N workers in parallel (horizontal scaling).
 */
class SendOrderConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** Exponential backoff: 10s, 30s, 60s, 120s, 300s */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public int $timeout = 30;

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::with(['items', 'user'])->find($this->orderId);
        if (! $order || ! $order->user?->email) {
            return;
        }

        try {
            Mail::to($order->user->email)->send(new OrderConfirmation($order));
        } catch (\Throwable $e) {
            Log::warning('Order email failed (will retry)', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            throw $e; // re-throw so queue records the failure and retries per backoff()
        }
    }
}
