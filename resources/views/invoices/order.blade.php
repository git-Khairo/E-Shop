<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 6px; }
        th { background: #eee; text-align: left; }
        .right { text-align: right; }
        .total { font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>
    <h1>INVOICE</h1>
    <p><strong>Reference:</strong> {{ $order->reference }}<br>
       <strong>Date:</strong> {{ $order->created_at->format('Y-m-d H:i') }}<br>
       <strong>Customer:</strong> {{ $order->user->username ?? '—' }} ({{ $order->user->email ?? '' }})</p>

    <table>
        <thead>
            <tr><th>Item</th><th>Qty</th><th class="right">Unit</th><th class="right">Line total</th></tr>
        </thead>
        <tbody>
            @foreach($order->items as $i)
            <tr>
                <td>{{ $i->product_name_snapshot }}</td>
                <td>{{ $i->quantity }}</td>
                <td class="right">${{ number_format($i->unit_price, 2) }}</td>
                <td class="right">${{ number_format($i->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="3" class="right">TOTAL</td>
                <td class="right">${{ number_format($order->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
