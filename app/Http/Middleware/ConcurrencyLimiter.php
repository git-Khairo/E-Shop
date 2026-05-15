<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * REQUIREMENT 2: Resource Management & Capacity Control
 *
 * This middleware implements a counting semaphore pattern using atomic cache
 * operations. It limits the number of SIMULTANEOUS requests that can execute
 * a given route (e.g., checkout) at the same time.
 *
 * WHY: Without this, a flash sale could spawn 1000 simultaneous checkout
 * processes, each holding DB connections, memory, and CPU. The server would
 * crash (OOM) or the DB would deadlock. A semaphore caps the parallelism
 * to a safe level (e.g., 10 concurrent checkouts) and rejects overflow
 * requests with HTTP 429 — graceful degradation instead of catastrophic failure.
 *
 * BEFORE (no limiter):
 *   1000 concurrent checkouts → 1000 PHP workers → DB connection pool exhausted → crash
 *
 * AFTER (with semaphore):
 *   1000 concurrent checkouts → 10 proceed, 990 get HTTP 429 "Too Busy" → server stays healthy
 *
 * Implementation: Redis/Cache atomic increment as a counting semaphore.
 * Each request increments the counter on entry, decrements on exit.
 * If counter > max_slots → reject immediately.
 *
 * Synchronization point: Cache::increment() / Cache::decrement() are atomic
 * operations in Redis — no race condition between check and increment.
 */
class ConcurrencyLimiter
{
    /**
     * @param  string  $key       Cache key for this limiter group
     * @param  int     $maxSlots  Max simultaneous requests allowed
     */
    public function handle(Request $request, Closure $next, string $key = 'checkout', int $maxSlots = 10): Response
    {
        $cacheKey = "semaphore:{$key}";
        $acquired = false;

        try {
            // Atomic increment — returns the NEW value after incrementing.
            // If this is the 11th concurrent request and max is 10, we reject.
            $current = Cache::increment($cacheKey);

            // First request ever? increment returns 1, but the key might not have a TTL.
            // Set a safety TTL so leaked semaphores auto-expire (defensive programming).
            if ($current === 1) {
                Cache::put($cacheKey, 1, now()->addMinutes(5));
            }

            if ($current > $maxSlots) {
                // REJECT: too many concurrent requests — decrement and return 429
                Cache::decrement($cacheKey);

                Log::warning('ConcurrencyLimiter: request rejected', [
                    'key'          => $key,
                    'current'      => $current,
                    'max_slots'    => $maxSlots,
                    'user_id'      => $request->user()?->id,
                    'ip'           => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Server is busy processing other checkouts. Please retry in a few seconds.',
                    'retry_after' => 2,
                ], 429);
            }

            $acquired = true;

            // Record metrics for the monitoring dashboard
            $this->recordMetric($key, $current, $maxSlots);

            // Process the request
            $response = $next($request);

            return $response;

        } finally {
            // CRITICAL: always release the semaphore slot, even on exceptions.
            // This is analogous to a try-finally with mutex.unlock() in Java.
            if ($acquired) {
                Cache::decrement($cacheKey);
            }
        }
    }

    /**
     * Store a snapshot of concurrency usage for the monitoring dashboard.
     */
    private function recordMetric(string $key, int $current, int $max): void
    {
        Cache::put("monitor:semaphore:{$key}:current", $current, now()->addMinutes(5));
        Cache::put("monitor:semaphore:{$key}:max", $max, now()->addMinutes(5));
        Cache::increment("monitor:semaphore:{$key}:total_requests");
    }
}
