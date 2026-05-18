<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * REQUIREMENT 5: Load Distribution
 *
 * Simulates distributing incoming requests across multiple backend servers.
 * In a real production system, this would be handled by Nginx/HAProxy/AWS ALB.
 * Here we simulate it at the application level to demonstrate the concepts.
 *
 * Three strategies implemented:
 *
 * 1. ROUND ROBIN: Requests are distributed evenly in a cyclic order.
 *    Server1 → Server2 → Server3 → Server1 → ...
 *    Pros: Simple, perfectly even distribution.
 *    Cons: Ignores server load — a slow server gets the same share.
 *
 * 2. WEIGHTED ROUND ROBIN: Servers with more capacity get more requests.
 *    Server1(w=3) → Server1 → Server1 → Server2(w=1) → Server3(w=2) → Server3 → ...
 *    Pros: Accounts for heterogeneous hardware.
 *    Cons: Weights are static — doesn't adapt to runtime conditions.
 *
 * 3. LEAST CONNECTIONS: Each request goes to the server with the fewest
 *    active connections at that moment.
 *    Pros: Adapts to real-time load — slow endpoints don't pile up.
 *    Cons: Requires tracking active connections (overhead).
 *
 * Synchronization point: The round-robin counter uses Cache::increment()
 * (atomic in Redis) to avoid two concurrent requests picking the same server.
 * Least-connections uses atomic increment/decrement to track active connections.
 */
class LoadBalancerService
{
    /**
     * Simulated backend server pool.
     * In production: these would be IP:port pairs of actual servers.
     */
    private array $servers = [
        ['id' => 'server-1', 'host' => '10.0.0.1:9000', 'weight' => 3, 'healthy' => true],
        ['id' => 'server-2', 'host' => '10.0.0.2:9000', 'weight' => 1, 'healthy' => true],
        ['id' => 'server-3', 'host' => '10.0.0.3:9000', 'weight' => 2, 'healthy' => true],
    ];

    public function __construct()
    {
        // Allow config override
        $configured = config('commerce.load_balancer.servers');
        if ($configured) {
            $this->servers = $configured;
        }
    }

    /**
     * Select a server using the specified strategy.
     */
    public function selectServer(string $strategy = 'round_robin'): array
    {
        $healthy = array_values(array_filter($this->servers, fn($s) => $s['healthy']));

        if (empty($healthy)) {
            throw new \RuntimeException('No healthy servers available.');
        }

        $selected = match ($strategy) {
            'round_robin'          => $this->roundRobin($healthy),
            'weighted_round_robin' => $this->weightedRoundRobin($healthy),
            'least_connections'    => $this->leastConnections($healthy),
            default                => $this->roundRobin($healthy),
        };

        // Track selection for monitoring
        Cache::increment("monitor:lb:requests:{$selected['id']}");
        Cache::increment("monitor:lb:total_requests");

        Log::debug('LoadBalancer: selected server', [
            'strategy' => $strategy,
            'server'   => $selected['id'],
        ]);

        return $selected;
    }

    /**
     * ROUND ROBIN — atomic counter modulo server count.
     *
     * Cache::increment is atomic in Redis, so concurrent requests
     * will each get a unique counter value → no two requests
     * pick the same server simultaneously (unless count wraps, which is fine).
     */
    private function roundRobin(array $servers): array
    {
        $counter = $this->nextCounter('lb:rr:counter');
        $index = $this->indexFromCounter($counter, count($servers));

        return $servers[$index];
    }

    /**
     * WEIGHTED ROUND ROBIN — expand servers by weight, then round-robin.
     *
     * Example with weights [3, 1, 2]:
     *   Expanded pool: [S1, S1, S1, S2, S3, S3]
     *   Counter mod 6 → distributes 3:1:2
     */
    private function weightedRoundRobin(array $servers): array
    {
        $pool = [];
        foreach ($servers as $server) {
            for ($i = 0; $i < $server['weight']; $i++) {
                $pool[] = $server;
            }
        }

        $counter = $this->nextCounter('lb:wrr:counter');
        $index = $this->indexFromCounter($counter, count($pool));

        return $pool[$index];
    }

    /**
     * LEAST CONNECTIONS — pick the server with the fewest active requests.
     *
     * Each server has a cache key tracking its active connection count.
     * We atomically increment on selection and the caller must call
     * releaseConnection() when done.
     *
     * Synchronization: reading all counters + selecting the minimum is NOT
     * perfectly atomic (TOCTOU window), but under typical load the error
     * is negligible. For perfect accuracy you'd need a distributed lock
     * or a single-threaded dispatcher — overkill for this use case.
     */
    private function leastConnections(array $servers): array
    {
        $minConns = PHP_INT_MAX;
        $candidates = [];

        foreach ($servers as $server) {
            $conns = (int) Cache::get("lb:lc:active:{$server['id']}", 0);
            if ($conns < $minConns) {
                $minConns = $conns;
                $candidates = [$server];
            } elseif ($conns === $minConns) {
                $candidates[] = $server;
            }
        }

        // Round-robin among tied servers so equal load is spread evenly
        $counter = $this->nextCounter('lb:lc:tiebreaker');
        $selected = $candidates[$this->indexFromCounter($counter, count($candidates))];

        Cache::increment("lb:lc:active:{$selected['id']}");

        return $selected;
    }

    /**
     * Release an active connection (for least-connections tracking).
     */
    public function releaseConnection(string $serverId): void
    {
        $key = "lb:lc:active:{$serverId}";
        $val = Cache::decrement($key);
        // Guard against going negative (defensive)
        if ($val < 0) {
            Cache::put($key, 0, now()->addHour());
        }
    }

    /**
     * Get distribution stats for monitoring.
     */
    public function getStats(): array
    {
        $stats = [];
        foreach ($this->servers as $server) {
            $stats[] = [
                'id'                 => $server['id'],
                'host'               => $server['host'],
                'weight'             => $server['weight'],
                'healthy'            => $server['healthy'],
                'total_requests'     => (int) Cache::get("monitor:lb:requests:{$server['id']}", 0),
                'active_connections' => (int) Cache::get("lb:lc:active:{$server['id']}", 0),
            ];
        }
        return $stats;
    }

    /**
     * Atomic counter compatible with all cache drivers (database returns false on missing keys).
     */
    private function nextCounter(string $key): int
    {
        $value = Cache::increment($key);

        if ($value !== false) {
            return (int) $value;
        }

        Cache::add($key, 0, now()->addDay());
        $value = Cache::increment($key);

        return $value !== false ? (int) $value : 1;
    }

    private function indexFromCounter(int $counter, int $count): int
    {
        return ((max(1, $counter) - 1) % $count + $count) % $count;
    }

    /**
     * Reset all counters (for testing).
     */
    public function resetCounters(): void
    {
        Cache::forget('lb:rr:counter');
        Cache::forget('lb:wrr:counter');
        Cache::forget('lb:lc:tiebreaker');
        Cache::forget('monitor:lb:total_requests');
        foreach ($this->servers as $server) {
            Cache::forget("monitor:lb:requests:{$server['id']}");
            Cache::forget("lb:lc:active:{$server['id']}");
        }
    }

    public function getServers(): array
    {
        return $this->servers;
    }
}
