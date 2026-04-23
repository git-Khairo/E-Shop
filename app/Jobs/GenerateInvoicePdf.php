<?php

namespace App\Jobs;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Generating a PDF is CPU-bound and slow (200–2000ms). It would be insane
 * to do it on the HTTP thread. We push it to the `invoices` queue and run
 * a small pool of dedicated workers.
 *
 * Resource management: run only `--queue=invoices --max-jobs=500` workers,
 * which keeps PDF rendering from starving the email queue.
 */
class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::with(['items', 'user'])->find($this->orderId);
        if (! $order) {
            return;
        }

        $pdf = Pdf::loadView('invoices.order', ['order' => $order]);

        $path = "invoices/{$order->reference}.pdf";
        Storage::disk('local')->put($path, $pdf->output());
    }
}
