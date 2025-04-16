<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Leads</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .print-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .print-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .print-header p {
            font-size: 14px;
            color: #666;
            margin-top: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            text-align: left;
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: left;
            vertical-align: middle;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .print-controls {
            text-align: center;
            margin-bottom: 20px;
        }

        .print-button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }

        .print-button:hover {
            background-color: #45a049;
        }

        .back-button {
            padding: 8px 16px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .back-button:hover {
            background-color: #5a6268;
        }

        @media print {
            .print-controls {
                display: none;
            }

            @page {
                size: landscape;
                margin: 0.5cm;
            }

            table {
                /* Dynamic font size based on column count */
                font-size:
                    {{ count($columns) > 15 ? '6pt' : (count($columns) > 10 ? '7pt' : '8pt') }}
                ;
                table-layout: fixed;
            }

            th,
            td {
                padding: 4px !important;
                word-wrap: break-word;
                white-space: normal;
                vertical-align: middle;
            }

            th {
                background-color: #f2f2f2;
                font-weight: bold;
                text-align: left;
                vertical-align: middle;
            }
        }
    </style>
</head>

<body>
    <div class="print-controls">
        <button class="print-button" onclick="window.print();">Print Now</button>
        <button class="back-button" onclick="window.history.back();">Back to Leads</button>
    </div>

    <div class="print-header">
        <h1>Selected Leads Report</h1>
        <p>Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                @if(in_array('id', $columns))
                <th>ID</th>@endif
                @if(in_array('ref', $columns))
                <th>REF</th>@endif
                @if(in_array('plaintiff', $columns))
                <th>Plaintiff</th>@endif
                @if(in_array('defendant_first_name', $columns) || in_array('defendant_last_name', $columns))
                <th>Defendant</th>@endif
                @if(in_array('address', $columns))
                <th>Address</th>@endif
                @if(in_array('county', $columns))
                <th>County</th>@endif
                @if(in_array('city', $columns))
                <th>City</th>@endif
                @if(in_array('state', $columns))
                <th>State</th>@endif
                @if(in_array('zip', $columns))
                <th>Zip</th>@endif
                @if(in_array('case_number', $columns))
                <th>Case #</th>@endif
                @if(in_array('setout_date', $columns))
                <th>Setout Date</th>@endif
                @if(in_array('setout_time', $columns))
                <th>Setout Time</th>@endif
                @if(in_array('status', $columns))
                <th>Status</th>@endif
                @if(in_array('setout_status', $columns))
                <th>Setout Status</th>@endif
                @if(in_array('writ_status', $columns))
                <th>Writ Status</th>@endif
                @if(in_array('lbx', $columns))
                <th>LBX</th>@endif
                @if(in_array('vis_setout', $columns))
                <th>Vis-LO</th>@endif
                @if(in_array('vis_to', $columns))
                <th>Vis-TO</th>@endif
                @if(in_array('time_on', $columns))
                <th>Time Start</th>@endif
                @if(in_array('time_en', $columns))
                <th>Time End</th>@endif
                @if(in_array('setout_st', $columns))
                <th>Setout Start</th>@endif
                @if(in_array('setout_en', $columns))
                <th>Setout End</th>@endif
                @if(in_array('locs', $columns))
                <th>LOCS</th>@endif
                @if(in_array('amount_owed', $columns))
                <th>Amount Owed</th>@endif
                @if(in_array('amount_cleared', $columns))
                <th>Amount Cleared</th>@endif
                @if(in_array('notes', $columns))
                <th>Notes</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($records as $lead)
                <tr>
                    @if(in_array('id', $columns))
                    <td>{{ $lead->id }}</td>@endif
                    @if(in_array('ref', $columns))
                    <td>{{ $lead->teams->pluck('name')->join(', ') }}</td>@endif
                    @if(in_array('plaintiff', $columns))
                    <td>{{ $lead->plaintiff }}</td>@endif
                    @if(in_array('defendant_first_name', $columns) || in_array('defendant_last_name', $columns))
                    <td>{{ trim($lead->defendant_first_name . ' ' . $lead->defendant_last_name) }}</td>@endif
                    @if(in_array('address', $columns))
                    <td>{{ $lead->address }}</td>@endif
                    @if(in_array('county', $columns))
                    <td>{{ $lead->county }}</td>@endif
                    @if(in_array('city', $columns))
                    <td>{{ $lead->city }}</td>@endif
                    @if(in_array('state', $columns))
                    <td>{{ $lead->state }}</td>@endif
                    @if(in_array('zip', $columns))
                    <td>{{ $lead->zip }}</td>@endif
                    @if(in_array('case_number', $columns))
                    <td>{{ $lead->case_number }}</td>@endif
                    @if(in_array('setout_date', $columns))
                    <td>{{ $lead->setout_date ? date('m/d/Y', strtotime($lead->setout_date)) : '' }}</td>@endif
                    @if(in_array('setout_time', $columns))
                    <td>{{ $lead->setout_time }}</td>@endif
                    @if(in_array('status', $columns))
                    <td>{{ $lead->status?->name }}</td>@endif
                    @if(in_array('setout_status', $columns))
                    <td>{{ $lead->setoutStatus?->name }}</td>@endif
                    @if(in_array('writ_status', $columns))
                    <td>{{ $lead->writStatus?->name }}</td>@endif
                    @if(in_array('lbx', $columns))
                    <td>{{ $lead->lbx }}</td>@endif
                    @if(in_array('vis_setout', $columns))
                    <td>{{ $lead->vis_setout }}</td>@endif
                    @if(in_array('vis_to', $columns))
                    <td>{{ $lead->vis_to }}</td>@endif
                    @if(in_array('time_on', $columns))
                    <td>{{ $lead->time_on }}</td>@endif
                    @if(in_array('time_en', $columns))
                    <td>{{ $lead->time_en }}</td>@endif
                    @if(in_array('setout_st', $columns))
                    <td>{{ $lead->setout_st }}</td>@endif
                    @if(in_array('setout_en', $columns))
                    <td>{{ $lead->setout_en }}</td>@endif
                    @if(in_array('locs', $columns))
                    <td>{{ $lead->locs }}</td>@endif
                    @if(in_array('amount_owed', $columns))
                    <td>${{ number_format($lead->amount_owed ?? 0, 2) }}</td>@endif
                    @if(in_array('amount_cleared', $columns))
                    <td>${{ number_format($lead->leadAmounts->sum('amount_cleared') ?? 0, 2) }}</td>@endif
                    @if(in_array('notes', $columns))
                    <td>{{ $lead->notes }}</td>@endif
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
