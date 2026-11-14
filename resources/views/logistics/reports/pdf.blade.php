<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $reference }} | LUMEAR Logistics</title>

    <style>
        @page {
            margin: 112px 36px 65px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #242124;
            font-size: 9px;
            line-height: 1.5;
        }

        .top-band {
            position: fixed;
            top: -112px;
            left: -36px;
            right: -36px;
            height: 92px;
            background: #62191C;
            color: #FFFFFF;
            padding: 21px 36px;
        }

        .brand {
            font-size: 25px;
            font-weight: bold;
            letter-spacing: 3px;
        }

        .brand-sub {
            color: #E0CFC2;
            font-size: 8px;
            letter-spacing: 2px;
            margin-top: 2px;
        }

        .header-right {
            position: absolute;
            right: 36px;
            top: 24px;
            text-align: right;
        }

        .header-right strong {
            display: block;
            font-size: 12px;
        }

        .header-right span {
            color: #E0CFC2;
            font-size: 8px;
        }

        .eyebrow {
            color: #873632;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        h1 {
            font-size: 22px;
            margin: 5px 0;
            color: #62191C;
        }

        .subtitle {
            color: #777078;
            margin-bottom: 18px;
        }

        .rule {
            border-bottom: 2px solid #CAAE9F;
            margin: 12px 0 16px;
        }

        .metadata {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 19px;
        }

        .metadata td {
            width: 25%;
            vertical-align: top;
            padding: 8px 12px;
            background: #F7F1EC;
            border-right: 3px solid white;
        }

        .meta-label {
            color: #7D6E6A;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .meta-value {
            color: #30292A;
            font-weight: bold;
            font-size: 9px;
            margin-top: 4px;
        }

        .section-heading {
            font-size: 12px;
            color: #62191C;
            font-weight: bold;
            margin: 18px 0 10px;
        }

        .summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px 0;
            margin: 0 -5px 18px;
        }

        .summary td {
            width: 25%;
            padding: 12px 13px;
            background: #F8F5F2;
            border-top: 3px solid #62191C;
        }

        .summary .number {
            display: block;
            font-size: 21px;
            font-weight: bold;
            color: #62191C;
        }

        .summary .label {
            display: block;
            font-size: 8px;
            color: #736A69;
            text-transform: uppercase;
        }

        .summary .completed {
            border-color: #3D8168;
        }

        .summary .progress {
            border-color: #C28A35;
        }

        .summary .exception {
            border-color: #B54E4E;
        }

        .records {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .records thead {
            display: table-header-group;
        }

        .records th {
            background: #62191C;
            color: white;
            text-align: left;
            padding: 10px 7px;
            font-size: 7.5px;
            text-transform: uppercase;
        }

        .records td {
            padding: 9px 7px;
            vertical-align: top;
            border-bottom: 1px solid #E9E0DC;
            overflow-wrap: break-word;
        }

        .records tr:nth-child(even) td {
            background: #FAF7F5;
        }

        .records tr {
            page-break-inside: avoid;
        }

        .reference {
            font-weight: bold;
            color: #62191C;
        }

        .status {
            font-size: 8px;
            font-weight: bold;
        }

        .status.completed {
            color: #277354;
        }

        .status.progress {
            color: #996519;
        }

        .status.exception {
            color: #A73C3C;
        }

        .empty {
            padding: 24px;
            text-align: center;
            background: #F8F5F2;
            color: #786C6B;
        }

        .notice {
            background: #F7F1EC;
            border-left: 3px solid #873632;
            padding: 10px 12px;
            margin-top: 20px;
            color: #5F5351;
            font-size: 8px;
        }

        .footer {
            position: fixed;
            bottom: -47px;
            left: 0;
            right: 0;
            border-top: 1px solid #D9C6BA;
            padding-top: 9px;
            font-size: 8px;
            color: #7A6C68;
        }

        .footer-brand {
            color: #62191C;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .footer-right {
            float: right;
        }
    </style>
</head>

<body>
    <div class="top-band">
        <div class="brand">LUMEAR</div>
        <div class="brand-sub">
            LOGISTICS MANAGEMENT SYSTEM
        </div>

        <div class="header-right">
            <strong>OFFICIAL OPERATIONS REPORT</strong>
            <span>Generated system record</span>
        </div>
    </div>

    <div class="footer">
        <span class="footer-brand">LUMEAR LOGISTICS</span>
        &nbsp; | &nbsp; Confidential Operational Document

        <span class="footer-right">
            Reference: {{ $reference }}
        </span>
    </div>

    <div class="eyebrow">
        Logistics Intelligence / Reporting
    </div>

    <h1>General Operations Report</h1>

    <div class="subtitle">
        A consolidated record of logistics activities
        for the selected reporting period.
    </div>

    <div class="rule"></div>

    @php
        $modules = [
            'all' => 'All Logistics Activities',
            'pickup' => 'Pickup Requests',
            'incoming' => 'Incoming Parcels',
            'sorting' => 'Parcel Sorting',
            'assignment' => 'Delivery Assignment',
            'delivery' => 'Delivery Monitoring',
            'rider' => 'Rider Performance',
        ];
    @endphp

    <table class="metadata">
        <tr>
            <td>
                <div class="meta-label">Report Reference</div>
                <div class="meta-value">{{ $reference }}</div>
            </td>

            <td>
                <div class="meta-label">Reporting Period</div>
                <div class="meta-value">
                    {{ \Carbon\Carbon::parse($filters['from'])->format('M d, Y') }}
                    —
                    {{ \Carbon\Carbon::parse($filters['to'])->format('M d, Y') }}
                </div>
            </td>

            <td>
                <div class="meta-label">Report Type</div>
                <div class="meta-value">
                    {{ $modules[$filters['type']] ?? 'General' }}
                </div>
            </td>

            <td>
                <div class="meta-label">Status Filter</div>
                <div class="meta-value">
                    {{ $filters['status'] === 'all'
                        ? 'All Statuses'
                        : $filters['status'] }}
                </div>
            </td>
        </tr>
    </table>

    <table class="metadata">
        <tr>
            <td colspan="2">
                <div class="meta-label">Logistics Provider</div>
                <div class="meta-value">
                    {{ $provider->business_name ?? 'LUMEAR Logistics' }}
                </div>
            </td>

            <td>
                <div class="meta-label">Generated By</div>
                <div class="meta-value">{{ $generatedBy }}</div>
            </td>

            <td>
                <div class="meta-label">Generated On</div>
                <div class="meta-value">{{ $generatedAt }}</div>
            </td>
        </tr>
    </table>

    <div class="section-heading">Executive Summary</div>

    <table class="summary">
        <tr>
            <td>
                <span class="number">{{ number_format($summary['total']) }}</span>
                <span class="label">Total Records</span>
            </td>

            <td class="completed">
                <span class="number">{{ number_format($summary['completed']) }}</span>
                <span class="label">Completed</span>
            </td>

            <td class="progress">
                <span class="number">{{ number_format($summary['progress']) }}</span>
                <span class="label">In Progress</span>
            </td>

            <td class="exception">
                <span class="number">{{ number_format($summary['exceptions']) }}</span>
                <span class="label">Exceptions</span>
            </td>
        </tr>
    </table>

    <div class="section-heading">Detailed Activity Register</div>

    @if($records->isEmpty())
        <div class="empty">
            No records matched the selected report filters.
        </div>
    @else
        <table class="records">
            <thead>
                <tr>
                    <th style="width: 10%">Reference</th>
                    <th style="width: 10%">Date</th>
                    <th style="width: 13%">Module</th>
                    <th style="width: 27%">Activity Description</th>
                    <th style="width: 14%">Assigned To</th>
                    <th style="width: 16%">Location</th>
                    <th style="width: 10%">Status</th>
                </tr>
            </thead>

            <tbody>
                @foreach($records as $record)
                    <tr>
                        <td class="reference">
                            {{ $record['id'] }}
                        </td>

                        <td>
                            {{ \Carbon\Carbon::parse($record['date'])->format('M d, Y') }}
                        </td>

                        <td>
                            {{ $modules[$record['module']] ?? ucfirst($record['module']) }}
                        </td>

                        <td>{{ $record['description'] }}</td>

                        <td>{{ $record['assignedTo'] }}</td>

                        <td>{{ $record['location'] }}</td>

                        <td>
                            @php
                                $statusClass = match($record['status']) {
                                    'Completed' => 'completed',
                                    'Exception' => 'exception',
                                    default => 'progress'
                                };
                            @endphp

                            <span class="status {{ $statusClass }}">
                                {{ $record['status'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="notice">
        <strong>REPORT INTEGRITY NOTICE</strong><br>
        This document was generated from LUMEAR Logistics
        records using the filters stated above. The original
        generated file is retained by the system for integrity
        verification. Altered copies are not authoritative.
    </div>
</body>
</html>
