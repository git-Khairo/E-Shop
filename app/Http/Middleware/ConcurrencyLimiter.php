<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ConcurrencyLimiter
{
    public function handle(Request $request, Closure $next, string $key = 'checkout', int $maxSlots = 10): Response
    {
        $cacheKey = "semaphore:{$key}";
        $acquired = false;

        try {
            $current = Cache::increment($cacheKey);

            if ($current === 1) {
                Cache::put($cacheKey, 1, now()->addMinutes(5));
            }

            if ($current > $maxSlots) {
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

            $this->recordMetric($key, $current, $maxSlots);
            $response = $next($request);

            return $response;

        } finally {

            if ($acquired) {
                Cache::decrement($cacheKey);
            }
        }
    }

    private function recordMetric(string $key, int $current, int $max): void
    {
        Cache::put("monitor:semaphore:{$key}:current", $current, now()->addMinutes(5));
        Cache::put("monitor:semaphore:{$key}:max", $max, now()->addMinutes(5));
        Cache::increment("monitor:semaphore:{$key}:total_requests");
    }
}
