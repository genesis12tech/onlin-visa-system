<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; margin: 40px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 32px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #f3f4f6; text-align: left; padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        .total-row td { font-weight: bold; border-top: 2px solid #111; border-bottom: none; }
        .meta { color: #6b7280; font-size: 11px; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h1>Payment Receipt</h1>
    <p class="subtitle">Invoice #{{ $invoice->invoice_number }}</p>

    <table>
        <tr>
            <td class="meta">Invoice Date</td>
            <td>{{ $invoice->issued_at->format('d M Y') }}</td>
            <td class="meta">Application Ref</td>
            <td>{{ $payment->visaApplication->tracking_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="meta">Payment Status</td>
            <td>{{ ucfirst($payment->status->value) }}</td>
            <td class="meta">Payment Date</td>
            <td>{{ $payment->succeeded_at?->format('d M Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <table style="margin-top: 32px;">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payment->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">{{ number_format($item->unit_amount / 100, 2) }} {{ $payment->currency }}</td>
                <td class="text-right">{{ number_format($item->total_amount / 100, 2) }} {{ $payment->currency }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3">Total</td>
                <td class="text-right">{{ number_format($payment->amount_total / 100, 2) }} {{ $payment->currency }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
