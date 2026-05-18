<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessDailySalesReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(public readonly ?string $date = null) {}

    public function handle(): void
    {
        $day = $this->date
            ? Carbon::parse($this->date)->startOfDay()
            : now()->subDay()->startOfDay();

        $csvPath = "reports/sales-{$day->toDateString()}.csv";
        $stream  = fopen('php://temp', 'w+');

        fputcsv($stream, ['order_id', 'reference', 'user_id', 'total', 'created_at']);

        $totalRows   = 0;
        $grandTotal  = 0.0;

        Order::query()
            ->where('created_at', '>=', $day)
            ->where('created_at', '<',  $day->copy()->addDay())
            ->where('payment_status', 'paid')
            ->orderBy('id')
            ->chunkById(500, function ($orders) use (&$totalRows, &$grandTotal, $stream) {
                foreach ($orders as $order) {
                    fputcsv($stream, [
                        $order->id,
                        $order->reference,
                        $order->user_id,
                        $order->total,
                        $order->created_at?->toIso8601String(),
                    ]);
                    $totalRows  += 1;
                    $grandTotal += (float) $order->total;
                }
            });

        fputcsv($stream, []);
        fputcsv($stream, ['TOTAL ROWS', $totalRows]);
        fputcsv($stream, ['GRAND TOTAL', $grandTotal]);

        rewind($stream);
        Storage::disk('local')->put($csvPath, stream_get_contents($stream));
        fclose($stream);

        \Log::info('Daily sales report generated', [
            'date'    => $day->toDateString(),
            'rows'    => $totalRows,
            'total'   => $grandTotal,
            'path'    => $csvPath,
        ]);
    }
}
