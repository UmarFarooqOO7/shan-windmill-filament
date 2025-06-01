<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            /* Apply border-box to all elements */
        }

        body {
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 0;
            /* Remove default body margin */
            padding: 0;
            /* Remove default body padding */
            background-color: #f9f9f9;
            /* This won't show in PDF but good for browser debug */
            -webkit-font-smoothing: antialiased;
            /* Better font rendering */
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
            padding-bottom: 30px;
            border-bottom: 2px solid #007bff;
            /* Accent color */
            margin-bottom: 30px;
        }

        .header .company-details h1 {
            margin: 0;
            font-size: 28px;
            color: #007bff;
            /* Accent color */
        }

        .header .company-details p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #555;
        }

        .header .invoice-info {
            text-align: right;
        }

        .header .invoice-info h2 {
            margin: 0;
            font-size: 36px;
            color: #333;
        }

        .header .invoice-info p {
            margin: 5px 0 0;
            font-size: 14px;
        }

        .addresses {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .addresses .bill-to,
        .addresses .ship-to {
            /* Assuming ship-to might be relevant for some leads */
            width: 48%;
        }

        .addresses h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
            color: #007bff;
            /* Accent color */
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .addresses p {
            margin: 0 0 5px;
            font-size: 14px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }

        .items-table th {
            background-color: #f0f8ff;
            /* Light blue accent */
            color: #007bff;
            font-weight: bold;
        }

        .items-table td.description {
            width: 45%;
            /* Adjusted width */
        }

        .items-table td.quantity,
        .items-table td.unit-price,
        .items-table td.total {
            text-align: right;
            width: 15%;
            /* Example: give other columns some explicit relative width */
        }

        .totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }

        .totals table {
            width: 280px;
            /* Adjusted width */
            border-collapse: collapse;
        }

        .totals td {
            padding: 8px 12px;
            font-size: 14px;
        }

        .totals tr.grand-total td {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            /* Accent color */
            border-top: 2px solid #007bff;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .footer p {
            margin: 5px 0;
        }

        .notes {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 3px solid #007bff;
            font-size: 13px;
        }

        .notes h4 {
            margin-top: 0;
            color: #007bff;
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
                {{-- Add your company logo here if available --}}
                {{-- <img src="path/to/your/logo.png" alt="Company Logo" style="max-height: 70px; margin-bottom: 10px;">
                --}}
                <h1>{{ $invoice->company_name ?? 'Your Company Name' }}</h1>
                <p>{{ $invoice->company_address_line_1 ?? '123 Business Rd' }}</p>
                <p>{{ $invoice->company_address_line_2 ?? 'Business City, BC 12345' }}</p>
                <p>Email: {{ $invoice->company_email ?? 'contact@yourcompany.com' }}</p>
                <p>Phone: {{ $invoice->company_phone ?? '(555) 123-4567' }}</p>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Date Issued:</strong>
                    {{ $invoice->invoice_date ? $invoice->invoice_date->format('M d, Y') : 'N/A' }}</p>
                <p><strong>Date Due:</strong> {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A' }}
                </p>
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
                {{-- <p>Email: {{ $invoice->lead->email ?? 'N/A' }}</p> --}}
                @if($invoice->lead->phone)
                    <p>Phone: {{ $invoice->lead->phone }}</p>
                @endif
            </div>
            {{-- Optional Ship To section if needed
            <div class="ship-to">
                <h3>Ship To:</h3>
                <p>...</p>
            </div>
            --}}
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="description">Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th class="total">Total</th>
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
                        </td> {{-- Use item->total_price --}}
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center;">No items on this invoice.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align: right;">
                        {{ $invoice->currency_symbol ?? '$' }}{{ number_format($invoice->subtotal, 2) }}</td> {{-- Use
                    invoice->subtotal --}}
                </tr>
                @if(isset($invoice->tax_rate) && $invoice->tax_rate > 0)
                    <tr>
                        <td>Tax ({{ number_format($invoice->tax_rate, 2) }}%):</td>
                        <td style="text-align: right;">
                            {{ $invoice->currency_symbol ?? '$' }}{{ number_format($invoice->tax_amount, 2) }}</td> {{-- Use
                        invoice->tax_amount --}}
                    </tr>
                @endif
                <tr class="grand-total">
                    <td>Total Amount Due:</td>
                    <td style="text-align: right;">
                        {{ $invoice->currency_symbol ?? '$' }}{{ number_format($invoice->total_amount, 2) }}</td> {{--
                    Use invoice->total_amount --}}
                </tr>
            </table>
        </div>

        @if($invoice->notes)
            <div class="notes">
                <h4>Notes:</h4>
                <p>{{ nl2br(e($invoice->notes)) }}</p>
            </div>
        @endif

        @if($invoice->payment_instructions)
            <div class="payment-instructions">
                <h4>Payment Instructions:</h4>
                <p>{{ nl2br(e($invoice->payment_instructions)) }}</p>
            </div>
        @endif

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>If you have any questions concerning this invoice, please contact
                {{ $invoice->company_name ?? 'Your Company Name' }} at {{ $invoice->company_email ??
                'contact@yourcompany.com' }}.</p>
        </div>
    </div>
</body>

</html>
