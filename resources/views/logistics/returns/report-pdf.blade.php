<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>
        {{ $reference }} | LUMEAR Return Report
    </title>

    <style>
        @page {
            margin: 95px 28px 55px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #2b2526;
            font-size: 9px;
            line-height: 1.4;
        }

        .header {
            position: fixed;
            top: -95px;
            left: -28px;
            right: -28px;
            height: 74px;
            padding: 17px 28px;
            background: #62191C;
            color: #ffffff;
        }

        .brand {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .subtitle {
            color: #e6d4cc;
            font-size: 8px;
            letter-spacing: 1px;
        }

        .header-right {
            position: absolute;
            top: 20px;
            right: 28px;
            text-align: right;
            font-size: 8px;
        }

        h1 {
            color: #62191C;
            font-size: 20px;
            margin: 0 0 5px;
        }

        .description {
            color: #6e6163;
            margin: 0 0 14px;
        }

        .metadata,
        .summary,
        .records {
            width: 100%;
            border-collapse: collapse;
        }

        .metadata {
            margin-bottom: 16px;
        }

        .metadata td {
            width: 25%;
            padding: 9px;
            background: #f6efeb;
            border-right: 3px solid #ffffff;
        }

        .label {
            display: block;
            color: #76696a;
            font-size: 7px;
            text-transform: uppercase;
        }

        .value {
            display: block;
            margin-top: 3px;
            font-weight: bold;
            color: #352e2f;
        }

        .summary {
            margin-bottom: 18px;
        }

        .summary td {
            width: 14.28%;
            padding: 10px;
            border-right: 4px solid #ffffff;
            background: #f8f5f3;
            border-top: 3px solid #62191C;
        }

        .summary-number {
            display: block;
            color: #62191C;
            font-size: 18px;
            font-weight: bold;
        }

        .summary-label {
            display: block;
            color: #76696a;
            font-size: 7px;
            text-transform: uppercase;
        }

        h2 {
            color: #62191C;
            font-size: 12px;
            margin: 14px 0 8px;
        }

        .records {
            table-layout: fixed;
        }

        .records thead {
            display: table-header-group;
        }

        .records th {
            padding: 8px 5px;
            background: #62191C;
            color: #ffffff;
            font-size: 7px;
            text-align: left;
            text-transform: uppercase;
        }

        .records td {
            padding: 7px 5px;
            vertical-align: top;
            border-bottom: 1px solid #e8ded9;
            overflow-wrap: break-word;
        }

        .records tr:nth-child(even) td {
            background: #faf7f5;
        }

        .records tr {
            page-break-inside: avoid;
        }

        .footer {
            position: fixed;
            bottom: -38px;
            left: 0;
            right: 0;
            border-top: 1px solid #d9c9c2;
            padding-top: 7px;
            color: #77696a;
            font-size: 8px;
        }

        .footer-right {
            float: right;
        }

        .empty {
            padding: 20px;
            text-align: center;
            color: #76696a;
            background: #f8f5f3;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="brand">LUMEAR</div>

        <div class="subtitle">
            LOGISTICS / REVERSE LOGISTICS
        </div>

        <div class="header-right">
            <strong>Return Operations Report</strong>

            <br>

            {{ $reference }}
        </div>
    </div>

    <h1>Return Operations Report</h1>

    <p class="description">
        Physical return activity from buyer pickup,
        through hub receiving, until seller handoff.
        Refund approval and payment are excluded.
    </p>

    <table class="metadata">
        <tr>
            <td>
                <span class="label">Logistics Provider</span>

                <span class="value">
                    {{ $provider->name ?? 'LUMEAR Logistics' }}
                </span>
            </td>

            <td>
                <span class="label">Date From</span>

                <span class="value">{{ $from }}</span>
            </td>

            <td>
                <span class="label">Date To</span>

                <span class="value">{{ $to }}</span>
            </td>

            <td>
                <span class="label">Generated At</span>

                <span class="value">{{ $generatedAt }}</span>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <span class="summary-number">
                    {{ $summary['total'] }}
                </span>

                <span class="summary-label">Total</span>
            </td>

            <td>
                <span class="summary-number">
                    {{ $summary['approved'] }}
                </span>

                <span class="summary-label">Approved</span>
            </td>

            <td>
                <span class="summary-number">
                    {{ $summary['scheduled'] }}
                </span>

                <span class="summary-label">Scheduled</span>
            </td>

            <td>
                <span class="summary-number">
                    {{ $summary['picked_up'] }}
                </span>

                <span class="summary-label">Picked Up</span>
            </td>

            <td>
                <span class="summary-number">
                    {{ $summary['hub_received'] }}
                </span>

                <span class="summary-label">Hub Received</span>
            </td>

            <td>
                <span class="summary-number">
                    {{ $summary['completed'] }}
                </span>

                <span class="summary-label">Seller Handoff</span>
            </td>

            <td>
                <span class="summary-number">
                    {{ $summary['failed'] }}
                </span>

                <span class="summary-label">Failed</span>
            </td>
        </tr>
    </table>

    <h2>Return Records</h2>

    <table class="records">
        <thead>
            <tr>
                <th style="width: 11%">Reference</th>
                <th style="width: 10%">Status</th>
                <th style="width: 18%">Reason</th>
                <th style="width: 11%">Approved</th>
                <th style="width: 10%">Schedule</th>
                <th style="width: 10%">Picked Up</th>
                <th style="width: 10%">Hub Received</th>
                <th style="width: 10%">Seller Handoff</th>
                <th style="width: 10%">Rider</th>
            </tr>
        </thead>

        <tbody>
            @forelse($returns as $return)
                <tr>
                    <td>{{ $return->return_reference }}</td>

                    <td>
                        {{ ucwords(
                            str_replace('_', ' ', $return->status)
                        ) }}
                    </td>

                    <td>{{ $return->return_reason }}</td>

                    <td>{{ $return->approved_at }}</td>

                    <td>{{ $return->scheduled_date ?? '—' }}</td>

                    <td>{{ $return->picked_up_at ?? '—' }}</td>

                    <td>{{ $return->hub_received_at ?? '—' }}</td>

                    <td>{{ $return->seller_handed_at ?? '—' }}</td>

                    <td>{{ $return->rider_name ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="empty">
                        No approved physical returns were found
                        for this date range.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <strong>LUMEAR Logistics</strong>
        · Return operations record

        <span class="footer-right">
            {{ $reference }}
        </span>
    </div>
</body>
</html>