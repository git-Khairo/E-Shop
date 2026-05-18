<?php

namespace Tests\Feature;

use App\Services\LoadBalancerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

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

    public function test_BEFORE_single_server_bottleneck(): void
    {

        $distribution = ['server-1' => 1000, 'server-2' => 0, 'server-3' => 0];

        $this->assertEquals(1000, $distribution['server-1']);
        $this->assertEquals(0, $distribution['server-2']);
        $this->assertEquals(0, $distribution['server-3']);

        $imbalance = max($distribution) - min($distribution);
        $this->assertEquals(1000, $imbalance, 'BEFORE: Maximum imbalance — all load on one server.');
    }

    public function test_AFTER_round_robin_even_distribution(): void
    {
        $distribution = $this->simulateRequests(300, 'round_robin');

        $this->assertEquals(100, $distribution['server-1']);
        $this->assertEquals(100, $distribution['server-2']);
        $this->assertEquals(100, $distribution['server-3']);

        $stdDev = $this->standardDeviation(array_values($distribution));
        $this->assertEquals(0.0, $stdDev, 'Round Robin: zero deviation = perfectly even.');
    }

    public function test_AFTER_weighted_round_robin_respects_capacity(): void
    {
        $distribution = $this->simulateRequests(600, 'weighted_round_robin');

        $this->assertEquals(300, $distribution['server-1'], 'Server-1 (w=3) gets 50%.');
        $this->assertEquals(100, $distribution['server-2'], 'Server-2 (w=1) gets 16.7%.');
        $this->assertEquals(200, $distribution['server-3'], 'Server-3 (w=2) gets 33.3%.');

        $this->assertGreaterThan($distribution['server-2'], $distribution['server-1'],
            'Higher weight = more requests.');
    }

    public function test_AFTER_least_connections_adapts_to_load(): void
    {

        Cache::put('lb:lc:active:server-1', 5, now()->addMinutes(5));

        $selected = $this->lb->selectServer('least_connections');
        $this->assertNotEquals('server-1', $selected['id'],
            'Least Connections avoids the busiest server.');

        $this->assertContains($selected['id'], ['server-2', 'server-3']);
    }

    public function test_AFTER_least_connections_even_under_equal_load(): void
    {
        $distribution = $this->simulateRequests(30, 'least_connections');

        foreach ($distribution as $server => $count) {
            $this->assertGreaterThan(0, $count,
                "Server {$server} should receive at least some requests.");
        }

        $total = array_sum($distribution);
        $this->assertEquals(30, $total, 'All 30 requests distributed.');
    }

    public function test_comparison_all_strategies(): void
    {
        $results = [];
        $strategies = ['round_robin', 'weighted_round_robin', 'least_connections'];

        foreach ($strategies as $strategy) {
            Cache::flush();
            $this->lb->resetCounters();
            $results[$strategy] = $this->simulateRequests(300, $strategy);
        }

        $rrDev = $this->standardDeviation(array_values($results['round_robin']));
        $wrrDev = $this->standardDeviation(array_values($results['weighted_round_robin']));

        $this->assertLessThan($wrrDev, $rrDev,
            'Round Robin has lower deviation than Weighted RR (intentionally uneven).');

        foreach ($results as $strategy => $dist) {
            $this->assertEquals(300, array_sum($dist),
                "Strategy '{$strategy}' distributes all 300 requests.");
        }
    }

    private function simulateRequests(int $n, string $strategy): array
    {
        $distribution = [];
        foreach ($this->lb->getServers() as $s) {
            $distribution[$s['id']] = 0;
        }

        for ($i = 0; $i < $n; $i++) {
            $server = $this->lb->selectServer($strategy);
            $distribution[$server['id']]++;

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
