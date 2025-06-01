<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: serif;
            color: #333333;
            margin: 0;
            padding: 20px;
            /* Added padding to body */
            background-color: #ffffff;
            /* White background for a classic feel */
        }

        .invoice-container {
            width: 100%;
            /* Fill padded body */
            max-width: 700px;
            /* Max width for content readability */
            margin: 0 auto;
            /* Center container if max-width applies, body padding handles outer space */
            background-color: #ffffff;
            padding: 20px;
            /* Inner padding of container */
            border: 1px solid #cccccc;
            /* Subtle border */
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333333;
            /* Darker border for header */
        }

        .header h1 {
            margin: 0 0 10px;
            font-size: 32px;
            font-weight: normal;
            /* Classic fonts are often not bold for titles */
        }

        .header .company-details p {
            margin: 3px 0;
            font-size: 13px;
            line-height: 1.4;
        }

        .invoice-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .invoice-meta .meta-left,
        .invoice-meta .meta-right {
            width: 48%;
        }

        .invoice-meta p {
            margin: 5px 0;
        }

        .invoice-meta strong {
            font-weight: bold;
        }

        .addresses {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .addresses .bill-to {
            width: 48%;
        }

        .addresses h3 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px solid #eeeeee;
            padding-bottom: 4px;
        }

        .addresses p {
            margin: 0 0 4px;
            line-height: 1.5;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #dddddd;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }

        .items-table th {
            background-color: #f7f7f7;
            /* Very light grey for header */
            font-weight: bold;
        }

        .items-table td.description {
            width: 50%;
            /* Adjusted width */
        }

        .items-table td.quantity,
        .items-table td.unit-price,
        .items-table td.total {
            text-align: right;
            width: 16%;
            /* Example: give other columns some explicit relative width */
        }

        .totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }

        .totals table {
            width: 250px;
            /* Adjusted width */
            border-collapse: collapse;
            font-size: 14px;
        }

        .totals td {
            padding: 8px 10px;
        }

        .totals tr.grand-total td {
            font-size: 16px;
            font-weight: bold;
            border-top: 2px solid #333333;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #555555;
            padding-top: 20px;
            border-top: 1px solid #cccccc;
            margin-top: 30px;
        }

        .footer p {
            margin: 5px 0;
        }

        .notes-section {
            margin-bottom: 30px;
            font-size: 13px;
            border-top: 1px dashed #cccccc;
            padding-top: 15px;
        }

        .notes-section h4 {
            margin-top: 0;
            margin-bottom: 8px;
            font-weight: bold;
        }

        /* dompdf specific table display fix */
        table {
            display: table;
        }

        tr {
            display: table-row;
        }

        thead {
            display: table-header-group;
        }

        tbody {
            display: table-row-group;
        }

        tfoot {
            display: table-footer-group;
        }

        th,
        td {
            display: table-cell;
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <div class="header">
            <h1>{{ $invoice->company_name ?? 'Your Company Name' }}</h1>
            <div class="company-details">
                <p>{{ $invoice->company_address_line_1 ?? '123 Classic Ave' }}</p>
                <p>{{ $invoice->company_address_line_2 ?? 'Suite 100, Old Town, OT 54321' }}</p>
                <p>Email: {{ $invoice->company_email ?? 'info@yourcompany.com' }} | Phone:
                    {{ $invoice->company_phone ?? '(555) 987-6543' }}</p>
            </div>
        </div>

        <div class="invoice-meta">
            <div class="meta-left">
                <p><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Date of Issue:</strong>
                    {{ $invoice->invoice_date ? $invoice->invoice_date->format('F j, Y') : 'N/A' }}</p>
                <p><strong>Date Due:</strong> {{ $invoice->due_date ? $invoice->due_date->format('F j, Y') : 'N/A' }}
                </p>
            </div>
            <div class="meta-right" style="text-align: right;">
                <h2>INVOICE</h2>
            </div>
        </div>

        <div class="addresses">
            <div class="bill-to">
                <h3>Bill To:</h3>
                <p><strong>{{ $invoice->lead->plaintiff ?? 'N/A' }}</strong></p>
                @if($invoice->lead->company)
                    <p>{{ $invoice->lead->company }}</p>
                @endif
                <p>{{ $invoice->lead->address_line_1 ?? '' }}</p>
                <p>{{ $invoice->lead->address_line_2 ?? '' }}</p>
                <p>{{ $invoice->lead->city ?? '' }}{{ $invoice->lead->city && ($invoice->lead->state || $invoice->lead->zip_code) ? ',' : '' }}
                    {{ $invoice->lead->state ?? '' }} {{ $invoice->lead->zip_code ?? '' }}</p>
                <p>{{ $invoice->lead->country ?? '' }}</p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="description">Item Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th class="total">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr>
                        <td class="description">{{ $item->description }}</td>
                        <td class="quantity">{{ number_format($item->quantity, 2) }}</td>
                        <td class="unit-price">
                            {{ $invoice->currency_symbol ?? '$' }}{{ number_format($item->unit_price, 2) }}</td>
                        <td class="total">{{ $invoice->currency_symbol ?? '$' }}{{ number_format($item->total_price, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center;">No items recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align: right;">
                        {{ $invoice->currency_symbol ?? '$' }}{{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                @if($invoice->tax_amount > 0)
                    <tr>
                        <td>Tax ({{ number_format($invoice->tax_rate ?? 0, 2) }}%):</td>
                        <td style="text-align: right;">
                            {{ $invoice->currency_symbol ?? '$' }}{{ number_format($invoice->tax_amount, 2) }}</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <td>Total Due:</td>
                    <td style="text-align: right;">
                        {{ $invoice->currency_symbol ?? '$' }}{{ number_format($invoice->total_amount, 2) }}</td>
                </tr>
            </table>
        </div>

        @if($invoice->notes || $invoice->payment_instructions)
            <div class="notes-section">
                @if($invoice->notes)
                    <h4>Notes:</h4>
                    <p>{{ nl2br(e($invoice->notes)) }}</p>
                @endif
                @if($invoice->payment_instructions)
                    <h4 style="{{ $invoice->notes ? 'margin-top: 15px;' : '' }}">Payment Instructions:</h4>
                    <p>{{ nl2br(e($invoice->payment_instructions)) }}</p>
                @endif
            </div>
        @endif

        <div class="footer">
            <p>Thank you for your patronage.</p>
            <p>For inquiries regarding this invoice, please contact {{ $invoice->company_name ?? 'Your Company Name' }}.
            </p>
        </div>
    </div>
</body>

</html>
