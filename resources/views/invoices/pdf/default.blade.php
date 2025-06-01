<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; font-size: 12px; }
        .container { width: 100%; margin: 0 auto; }
        .header, .footer { text-align: center; }
        .header h1 { margin: 0; }
        .content { margin-top: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .total-section { margin-top: 20px; float: right; width: 30%; }
        .total-section td { border: none; padding: 2px 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>INVOICE</h1>
        </div>

        <div class="content">
            <table style="width:100%;">
                <tr>
                    <td style="width:50%; vertical-align: top;">
                        <strong>Billed To:</strong><br>
                        {{ $invoice->lead->plaintiff ?? 'N/A' }}<br>
                        {{-- Add other relevant lead fields here if available, e.g., email, phone --}}
                        {{-- Example: $invoice->lead->email ?? '' --}}
                    </td>
                    <td style="width:50%; vertical-align: top; text-align: right;">
                        <strong>Invoice #:</strong> {{ $invoice->invoice_number }}<br>
                        <strong>Date Issued:</strong> {{ $invoice->invoice_date->format('Y-m-d') }}<br>
                        <strong>Due Date:</strong> {{ $invoice->due_date->format('Y-m-d') }}<br>
                        @if($invoice->status)
                            <strong>Status:</strong> {{ ucfirst($invoice->status) }}
                        @endif
                    </td>
                </tr>
            </table>

            <table class="table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">{{ number_format($item->total_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="total-section">
                <tr>
                    <td><strong>Subtotal:</strong></td>
                    <td class="text-right">{{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Tax ({{ $invoice->tax_rate ?? 0 }}%):</strong></td>
                    <td class="text-right">{{ number_format($invoice->tax_amount, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Total:</strong></td>
                    <td class="text-right"><strong>{{ number_format($invoice->total_amount, 2) }}</strong></td>
                </tr>
            </table>

            <div style="clear:both;"></div>

            @if($invoice->notes)
            <div style="margin-top: 30px;">
                <strong>Notes:</strong><br>
                {{ $invoice->notes }}
            </div>
            @endif
        </div>

        <div class="footer" style="margin-top: 50px;">
            <p>Thank you for your business!</p>
        </div>
    </div>
</body>
</html>
