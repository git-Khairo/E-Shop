<?php

namespace App\Console\Commands;

use App\Services\LoadBalancerService;
use Illuminate\Console\Command;

class SimulateLoadBalancer extends Command
{
    protected $signature = 'commerce:simulate-lb
        {--requests=1000 : Number of requests to simulate}
        {--strategy=all : round_robin|weighted_round_robin|least_connections|all}';

    protected $description = 'Simulate load distribution across multiple backend servers.';

    public function handle(LoadBalancerService $lb): int
    {
        $totalRequests = (int) $this->option('requests');
        $strategy = $this->option('strategy');

        $strategies = $strategy === 'all'
            ? ['round_robin', 'weighted_round_robin', 'least_connections']
            : [$strategy];

        $this->info("Simulating {$totalRequests} requests across " . count($lb->getServers()) . " servers");
        $this->newLine();

        $allResults = [];

        foreach ($strategies as $strat) {
            $lb->resetCounters();

            $distribution = [];
            foreach ($lb->getServers() as $s) {
                $distribution[$s['id']] = 0;
            }

            $start = microtime(true);

            for ($i = 0; $i < $totalRequests; $i++) {
                $server = $lb->selectServer($strat);
                $distribution[$server['id']]++;

                if ($strat === 'least_connections') {
                    if (random_int(1, 3) === 1) {
                        $lb->releaseConnection($server['id']);
                    }
                }
            }

            if ($strat === 'least_connections') {
                foreach ($lb->getServers() as $s) {
                    $lb->releaseConnection($s['id']);
                }
            }

            $elapsed = round(microtime(true) - $start, 4);

            $allResults[$strat] = [
                'distribution' => $distribution,
                'elapsed'      => $elapsed,
            ];

            $this->info("Strategy: {$strat}");
            $this->info("Elapsed: {$elapsed}s");

            $rows = [];
            foreach ($distribution as $serverId => $count) {
                $pct = round(($count / $totalRequests) * 100, 1);
                $server = collect($lb->getServers())->firstWhere('id', $serverId);
                $rows[] = [
                    $serverId,
                    $server['host'],
                    "w={$server['weight']}",
                    $count,
                    "{$pct}%",
                    str_repeat('█', (int) round($pct / 2)),
                ];
            }
            $this->table(['Server', 'Host', 'Weight', 'Requests', '%', 'Distribution'], $rows);
            $this->newLine();
        }

        if (count($strategies) > 1) {
            $this->info('=== COMPARISON SUMMARY ===');
            $compRows = [];
            foreach ($allResults as $strat => $data) {
                $values = array_values($data['distribution']);
                $stdDev = $this->standardDeviation($values);
                $compRows[] = [
                    $strat,
                    implode(' / ', $values),
                    round($stdDev, 2),
                    $data['elapsed'] . 's',
                ];
            }
            $this->table(['Strategy', 'Distribution', 'Std Dev (lower=more even)', 'Time'], $compRows);

            $this->newLine();
            $this->info('ANALYSIS:');
            $this->line('  • Round Robin: perfectly even (StdDev ≈ 0), ignores server capacity.');
            $this->line('  • Weighted RR: respects server weights (3:1:2), good for heterogeneous hardware.');
            $this->line('  • Least Conn:  adapts to runtime load, best for variable-latency workloads.');
        }

        return self::SUCCESS;
    }

    private function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n < 2) return 0.0;
        $mean = array_sum($values) / $n;
        $sumSqDiff = 0.0;
        foreach ($values as $v) {
            $sumSqDiff += ($v - $mean) ** 2;
        }
        return sqrt($sumSqDiff / $n);
    }
}
