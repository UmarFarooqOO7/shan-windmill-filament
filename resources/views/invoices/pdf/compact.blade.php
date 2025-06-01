<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 10px;
            /* Smaller base font size for compactness */
            color: #444;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }

        .invoice-container {
            width: 100%;
            /* Changed from 800px to 100% to be responsive to page width */
            max-width: 780px;
            /* Optional: constrain max width if needed, but 100% is safer for PDF */
            margin: 20px auto;
            /* Reduced margin */
            background-color: #fff;
            padding: 20px;
            /* Reduced padding */
            border-radius: 0;
            /* Often better for PDF to not have rounded corners on the main box */
            box-shadow: none;
            /* Shadows don't render well or add unnecessary complexity in PDF */
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .header .company-details h1 {
            margin: 0 0 5px;
            font-size: 18px;
            /* Smaller heading */
            color: #333;
        }

        .header .company-details p {
            margin: 2px 0;
            font-size: 9px;
            /* Smaller paragraph text */
            line-height: 1.3;
        }

        .header .invoice-info {
            text-align: right;
        }

        .header .invoice-info h2 {
            margin: 0 0 5px;
            font-size: 22px;
            /* Smaller main "INVOICE" title */
            color: #333;
        }

        .header .invoice-info p {
            margin: 2px 0;
            font-size: 9px;
        }

        .addresses {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 9px;
        }

        .addresses .bill-to {
            width: 49%;
        }

        .addresses h3 {
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 11px;
            font-weight: bold;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 3px;
        }

        .addresses p {
            margin: 0 0 3px;
            line-height: 1.4;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #e0e0e0;
            padding: 6px 8px;
            /* Reduced padding */
            text-align: left;
            font-size: 9px;
        }

        .items-table th {
            background-color: #f9f9f9;
            font-weight: bold;
        }

        .items-table td.description {
            width: auto;
            /* Allow more flexible width */
        }

        .items-table td.quantity,
        .items-table td.unit-price,
        .items-table td.total {
            text-align: right;
            white-space: nowrap;
            /* Prevent wrapping in number columns */
        }

        .totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
        }

        .totals table {
            width: 220px;
            /* Smaller totals table */
            border-collapse: collapse;
            font-size: 9px;
        }

        .totals td {
            padding: 5px 8px;
        }

        .totals tr.grand-total td {
            font-size: 11px;
            font-weight: bold;
            border-top: 1px solid #ccc;
        }

        .footer {
            text-align: center;
            font-size: 8px;
            /* Even smaller footer text */
            color: #666;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            margin-top: 15px;
        }

        .footer p {
            margin: 3px 0;
        }

        .notes-section {
            margin-bottom: 15px;
            font-size: 9px;
            padding-top: 10px;
        }

        .notes-section h4 {
            margin-top: 0;
            margin-bottom: 4px;
            font-size: 10px;
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
            <div class="company-details">
                <h1>{{ $invoice->company_name ?? 'Company Name' }}</h1>
                <p>{{ $invoice->company_address_line_1 ?? 'Address Line 1' }}</p>
                <p>{{ $invoice->company_address_line_2 ?? 'Address Line 2' }}</p>
                <p>E: {{ $invoice->company_email ?? 'email@example.com' }} | P:
                    {{ $invoice->company_phone ?? '000-000-0000' }}</p>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p><strong>#:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Issued:</strong> {{ $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : 'N/A' }}
                </p>
                <p><strong>Due:</strong> {{ $invoice->due_date ? $invoice->due_date->format('Y-m-d') : 'N/A' }}</p>
            </div>
        </div>

        <div class="addresses">
            <div class="bill-to">
                <h3>Bill To:</h3>
                <p><strong>{{ $invoice->lead->plaintiff ?? 'N/A' }}</strong></p>
                @if($invoice->lead->company)
                <p>{{ $invoice->lead->company }}</p>@endif
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
                    <th class="description">Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th class="total">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr>
                        <td class="description">{{ $item->description }}</td>
                        <td class="quantity">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                        <td class="unit-price">
                            {{ $invoice->currency_symbol ?? '$' }}{{ rtrim(rtrim(number_format($item->unit_price, 2), '0'), '.') }}
                        </td>
                        <td class="total">
                            {{ $invoice->currency_symbol ?? '$' }}{{ rtrim(rtrim(number_format($item->total_price, 2), '0'), '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center;">No items.</td>
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
                        <td>Tax ({{ rtrim(rtrim(number_format($invoice->tax_rate ?? 0, 2), '0'), '.') }}%):</td>
                        <td style="text-align: right;">
                            {{ $invoice->currency_symbol ?? '$' }}{{ number_format($invoice->tax_amount, 2) }}</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <td>Total:</td>
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
                    <h4 style="{{ $invoice->notes ? 'margin-top: 10px;' : '' }}">Payment Instructions:</h4>
                    <p>{{ nl2br(e($invoice->payment_instructions)) }}</p>
                @endif
            </div>
        @endif

        <div class="footer">
            <p>Thank you! Questions? Contact {{ $invoice->company_email ?? 'email@example.com' }}.</p>
        </div>
    </div>
</body>

</html>
