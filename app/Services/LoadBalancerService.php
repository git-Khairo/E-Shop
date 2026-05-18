<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LoadBalancerService
{
    private array $servers = [
        ['id' => 'server-1', 'host' => '10.0.0.1:9000', 'weight' => 3, 'healthy' => true],
        ['id' => 'server-2', 'host' => '10.0.0.2:9000', 'weight' => 1, 'healthy' => true],
        ['id' => 'server-3', 'host' => '10.0.0.3:9000', 'weight' => 2, 'healthy' => true],
    ];

    public function __construct()
    {
        $configured = config('commerce.load_balancer.servers');
        if ($configured) {
            $this->servers = $configured;
        }
    }

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

        Cache::increment("monitor:lb:requests:{$selected['id']}");
        Cache::increment("monitor:lb:total_requests");

        Log::debug('LoadBalancer: selected server', [
            'strategy' => $strategy,
            'server'   => $selected['id'],
        ]);

        return $selected;
    }

    private function roundRobin(array $servers): array
    {
        $counter = $this->nextCounter('lb:rr:counter');
        $index = $this->indexFromCounter($counter, count($servers));

        return $servers[$index];
    }

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

        $counter = $this->nextCounter('lb:lc:tiebreaker');
        $selected = $candidates[$this->indexFromCounter($counter, count($candidates))];

        Cache::increment("lb:lc:active:{$selected['id']}");

        return $selected;
    }

    public function releaseConnection(string $serverId): void
    {
        $key = "lb:lc:active:{$serverId}";
        $val = Cache::decrement($key);
        if ($val < 0) {
            Cache::put($key, 0, now()->addHour());
        }
    }

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
