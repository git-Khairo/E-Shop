<?php

namespace Tests\Feature;

use App\Services\LoadBalancerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * REQUIREMENT 5: Load Distribution
 *
 * === BEFORE (Single Server) ===
 * All requests hit one server. Under heavy load:
 *   - 1000 req/s → one server handles ALL traffic
 *   - CPU at 100%, response times spike from 50ms to 5000ms
 *   - Any server failure = TOTAL DOWNTIME (single point of failure)
 *
 * === AFTER (Load Balanced across 3 servers) ===
 * Requests distributed across 3 servers:
 *   - 1000 req/s → ~333 req/s per server
 *   - CPU at ~33%, response times stay at ~50ms
 *   - One server fails → other 2 absorb traffic (high availability)
 *
 * Three strategies tested:
 *   1. Round Robin: perfectly even distribution, ignores server capacity
 *   2. Weighted Round Robin: distributes by server weight (capacity)
 *   3. Least Connections: adapts to runtime load (best for variable latency)
 *
 * In production: Nginx/HAProxy does this at the network level.
 * Here we simulate it to demonstrate the concepts and measure behavior.
 */
class Req5_LoadDistributionTest extends TestCase
{
    use RefreshDatabase;

    private LoadBalancerService $lb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lb = new LoadBalancerService();
        $this->lb->resetCounters();
        Cache::flush();
    }

    /**
     * BEFORE: Single server — all requests go to one place.
     */
    public function test_BEFORE_single_server_bottleneck(): void
    {
        // Simulating "before": all 1000 requests hit server-1
        $distribution = ['server-1' => 1000, 'server-2' => 0, 'server-3' => 0];

        // 100% load on one server, 0% on others = terrible
        $this->assertEquals(1000, $distribution['server-1']);
        $this->assertEquals(0, $distribution['server-2']);
        $this->assertEquals(0, $distribution['server-3']);

        $imbalance = max($distribution) - min($distribution);
        $this->assertEquals(1000, $imbalance, 'BEFORE: Maximum imbalance — all load on one server.');
    }

    /**
     * AFTER: Round Robin — perfectly even distribution.
     */
    public function test_AFTER_round_robin_even_distribution(): void
    {
        $distribution = $this->simulateRequests(300, 'round_robin');

        // With 300 requests across 3 servers: each should get exactly 100
        $this->assertEquals(100, $distribution['server-1']);
        $this->assertEquals(100, $distribution['server-2']);
        $this->assertEquals(100, $distribution['server-3']);

        $stdDev = $this->standardDeviation(array_values($distribution));
        $this->assertEquals(0.0, $stdDev, 'Round Robin: zero deviation = perfectly even.');
    }

    /**
     * AFTER: Weighted Round Robin — respects server capacity.
     *
     * Server weights: server-1=3, server-2=1, server-3=2 (total weight=6)
     * Expected distribution: 50%, 16.7%, 33.3%
     */
    public function test_AFTER_weighted_round_robin_respects_capacity(): void
    {
        $distribution = $this->simulateRequests(600, 'weighted_round_robin');

        // server-1 (weight=3): 300 requests (50%)
        // server-2 (weight=1): 100 requests (16.7%)
        // server-3 (weight=2): 200 requests (33.3%)
        $this->assertEquals(300, $distribution['server-1'], 'Server-1 (w=3) gets 50%.');
        $this->assertEquals(100, $distribution['server-2'], 'Server-2 (w=1) gets 16.7%.');
        $this->assertEquals(200, $distribution['server-3'], 'Server-3 (w=2) gets 33.3%.');

        // The heaviest server should get the most
        $this->assertGreaterThan($distribution['server-2'], $distribution['server-1'],
            'Higher weight = more requests.');
    }

    /**
     * AFTER: Least Connections — adapts to runtime load.
     */
    public function test_AFTER_least_connections_adapts_to_load(): void
    {
        // Pre-load server-1 with 5 active connections
        Cache::put('lb:lc:active:server-1', 5, now()->addMinutes(5));

        // Next request should NOT go to server-1 (it's the busiest)
        $selected = $this->lb->selectServer('least_connections');
        $this->assertNotEquals('server-1', $selected['id'],
            'Least Connections avoids the busiest server.');

        // It should pick server-2 or server-3 (both at 0 connections)
        $this->assertContains($selected['id'], ['server-2', 'server-3']);
    }

    /**
     * AFTER: Least Connections — distributes evenly under equal load.
     */
    public function test_AFTER_least_connections_even_under_equal_load(): void
    {
        $distribution = $this->simulateRequests(30, 'least_connections');

        // Under equal load (all servers start at 0 connections), distribution
        // should be roughly even (not perfectly due to timing)
        foreach ($distribution as $server => $count) {
            $this->assertGreaterThan(0, $count,
                "Server {$server} should receive at least some requests.");
        }

        $total = array_sum($distribution);
        $this->assertEquals(30, $total, 'All 30 requests distributed.');
    }

    /**
     * COMPARISON: Distribution quality across strategies.
     */
    public function test_comparison_all_strategies(): void
    {
        $results = [];
        $strategies = ['round_robin', 'weighted_round_robin', 'least_connections'];

        foreach ($strategies as $strategy) {
            Cache::flush();
            $this->lb->resetCounters();
            $results[$strategy] = $this->simulateRequests(300, $strategy);
        }

        // Round Robin should have lowest std deviation (most even)
        $rrDev = $this->standardDeviation(array_values($results['round_robin']));
        $wrrDev = $this->standardDeviation(array_values($results['weighted_round_robin']));

        $this->assertLessThan($wrrDev, $rrDev,
            'Round Robin has lower deviation than Weighted RR (intentionally uneven).');

        // All strategies distribute ALL requests (no request lost)
        foreach ($results as $strategy => $dist) {
            $this->assertEquals(300, array_sum($dist),
                "Strategy '{$strategy}' distributes all 300 requests.");
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function simulateRequests(int $n, string $strategy): array
    {
        $distribution = [];
        foreach ($this->lb->getServers() as $s) {
            $distribution[$s['id']] = 0;
        }

        for ($i = 0; $i < $n; $i++) {
            $server = $this->lb->selectServer($strategy);
            $distribution[$server['id']]++;

            // For least_connections: release after "processing"
            if ($strategy === 'least_connections') {
                $this->lb->releaseConnection($server['id']);
            }
        }

        return $distribution;
    }

    private function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n < 2) return 0.0;
        $mean = array_sum($values) / $n;
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }
        return round(sqrt($sumSq / $n), 2);
    }
}
