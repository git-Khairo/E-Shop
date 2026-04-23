<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, sans-serif;">
    <h2>Thanks for your order, {{ $order->user->username ?? 'customer' }}!</h2>
    <p>Your order reference is <strong>{{ $order->reference }}</strong>.</p>

    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;">
        <thead>
            <tr><th>Product</th><th>Qty</th><th>Unit</th><th>Total</th></tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p><strong>Total paid: ${{ number_format($order->total, 2) }}</strong></p>
</body>
</html>
