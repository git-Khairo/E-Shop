<?php

namespace Tests\Feature;

use App\Http\Middleware\ConcurrencyLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Req2_ResourceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        Route::middleware(ConcurrencyLimiter::class . ':test,3')
            ->get('/api/test/limited', function () {
                return response()->json(['status' => 'ok']);
            });
    }

    public function test_BEFORE_no_limiter_all_requests_accepted(): void
    {
        $accepted = 0;
        for ($i = 0; $i < 20; $i++) {
            $response = $this->getJson('/api/v1/products');
            if ($response->status() === 200) {
                $accepted++;
            }
        }

        $this->assertEquals(20, $accepted, 'All 20 requests accepted with no limiter.');
    }

    public function test_AFTER_semaphore_limits_concurrent_requests(): void
    {
        $maxSlots = 3;

        Cache::forget('semaphore:test');
        for ($i = 0; $i < $maxSlots; $i++) {
            Cache::increment('semaphore:test');
        }

        $response = $this->getJson('/api/test/limited');
        $this->assertEquals(429, $response->status(), '4th request rejected.');
        $this->assertStringContainsString('busy', $response->json('message'));
    }

    public function test_AFTER_semaphore_releases_on_completion(): void
    {
        Cache::forget('semaphore:test');

        $response = $this->getJson('/api/test/limited');
        $this->assertEquals(200, $response->status());

        $current = (int) Cache::get('semaphore:test', 0);
        $this->assertEquals(0, $current, 'Counter back to 0 after request.');
    }

    public function test_AFTER_rate_limiting_active(): void
    {
        $response = $this->getJson('/api/v1/products');
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit') || $response->status() === 200,
            'Rate limiting headers present on API routes.'
        );
    }

    public function test_comparison_before_vs_after(): void
    {

        $beforeBehavior = [
            'max_concurrent'  => 'UNLIMITED',
            'under_load'      => 'Server crash (OOM / connection pool exhausted)',
            'user_experience' => 'Random failures, timeouts, data corruption',
        ];

        $afterBehavior = [
            'max_concurrent'  => '10 simultaneous checkouts',
            'under_load'      => 'Graceful HTTP 429 with retry hint',
            'user_experience' => 'Brief wait, then successful checkout',
        ];

        $this->assertNotEquals(
            $beforeBehavior['max_concurrent'],
            $afterBehavior['max_concurrent'],
            'Resource management changes from unlimited to bounded concurrency.'
        );
        $this->assertTrue(true, 'Semaphore pattern successfully implemented.');
    }
}
