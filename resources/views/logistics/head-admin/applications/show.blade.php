<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUMEAR — Review Application</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f7f3f0;
            font-family: 'Segoe UI', sans-serif;
            color: #302626;
        }

        .container {
            max-width: 1050px;
            margin: auto;
            padding: 35px 20px;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #62191c;
            text-decoration: none;
            font-weight: 700;
        }

        h1 {
            color: #62191c;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #847777;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border: 1px solid #eaded7;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .card h2 {
            color: #62191c;
            font-size: 18px;
            margin-top: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
        }

        .field-label {
            color: #8b7777;
            font-size: 11px;
            margin-bottom: 7px;
        }

        .field-value {
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .status {
            font-weight: 800;
            text-transform: capitalize;
        }

        .pending { color: #a36a1c; }
        .approved { color: #26754b; }
        .rejected { color: #b33030; }

        .document-link {
            display: inline-block;
            background: #f5eeea;
            border: 1px solid #e0cfc2;
            color: #62191c;
            text-decoration: none;
            border-radius: 8px;
            padding: 13px 18px;
            margin: 6px 10px 6px 0;
            font-weight: 700;
            font-size: 13px;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .approve-button,
        .reject-button {
            width: 100%;
            padding: 14px;
            border: 0;
            border-radius: 9px;
            color: white;
            cursor: pointer;
            font-weight: 700;
        }

        .approve-button { background: #26754b; }
        .reject-button { background: #a52b32; }

        textarea {
            width: 100%;
            min-height: 115px;
            padding: 13px;
            margin: 12px 0;
            border: 1px solid #e0cfc2;
            border-radius: 9px;
            font: inherit;
            resize: vertical;
        }

        .message {
            border-radius: 9px;
            padding: 14px;
            margin-bottom: 20px;
        }

        .success {
            background: #e8f5ed;
            color: #26754b;
        }

        .error {
            background: #fff0f0;
            color: #a52b32;
        }

        @media(max-width: 650px) {
            .grid, .actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a class="back"
       href="{{ route('logistics.head-admin.applications.index') }}">
        ← Back to Applications
    </a>

    <h1>{{ $application->company_name }}</h1>

    <p class="subtitle">
        Logistics Application #{{ $application->id }}
    </p>

    @if (session('success'))
        <div class="message success" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="message error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card">
        <h2>Application Status</h2>

        <span class="status {{ $application->status }}">
            {{ ucfirst($application->status) }}
        </span>

        @if ($application->status === 'rejected')
            <p>
                <strong>Rejection reason:</strong>
                {{ $application->rejection_reason ?: 'Not provided' }}
            </p>
        @endif
    </div>

    <div class="card">
        <h2>Business Information</h2>

        <div class="grid">
            <div>
                <div class="field-label">Business Name</div>
                <div class="field-value">
                    {{ $application->company_name }}
                </div>
            </div>

            <div>
                <div class="field-label">SEC Number</div>
                <div class="field-value">
                    {{ $application->sec_number }}
                </div>
            </div>

            <div>
                <div class="field-label">TIN</div>
                <div class="field-value">
                    {{ $application->tin }}
                </div>
            </div>

            <div>
                <div class="field-label">Municipality PSGC Code</div>
                <div class="field-value">
                    {{ $application->municipality_psgc_code }}
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Applicant Information</h2>

        <div class="grid">
            <div>
                <div class="field-label">Full Name</div>
                <div class="field-value">
                    {{ $application->first_name }}
                    {{ $application->middle_initial }}
                    {{ $application->last_name }}
                </div>
            </div>

            <div>
                <div class="field-label">Sex</div>
                <div class="field-value">
                    {{ $application->sex }}
                </div>
            </div>

            <div>
                <div class="field-label">Birthday</div>
                <div class="field-value">
                    {{ $application->birthday }}
                </div>
            </div>

            <div>
                <div class="field-label">Email</div>
                <div class="field-value">
                    {{ $application->email }}
                </div>
            </div>

            <div>
                <div class="field-label">Mobile</div>
                <div class="field-value">
                    {{ $application->mobile }}
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Business Address</h2>

        <div class="grid">
            <div>
                <div class="field-label">Region</div>
                <div class="field-value">
                    {{ $application->region }}
                </div>
            </div>

            <div>
                <div class="field-label">Province</div>
                <div class="field-value">
                    {{ $application->province }}
                </div>
            </div>

            <div>
                <div class="field-label">Municipality</div>
                <div class="field-value">
                    {{ $application->city }}
                </div>
            </div>

            <div>
                <div class="field-label">Barangay</div>
                <div class="field-value">
                    {{ $application->barangay }}
                </div>
            </div>

            <div>
                <div class="field-label">House Number</div>
                <div class="field-value">
                    {{ $application->house_number }}
                </div>
            </div>

            <div>
                <div class="field-label">Street</div>
                <div class="field-value">
                    {{ $application->street }}
                </div>
            </div>

            <div>
                <div class="field-label">Postal Code</div>
                <div class="field-value">
                    {{ $application->postal_code }}
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Submitted Documents</h2>

        <p>
            Review both documents before making a decision.
        </p>

        <a
            class="document-link"
            target="_blank"
            rel="noopener noreferrer"
            href="{{ route('logistics.head-admin.applications.document', [$application->id, 'valid-id']) }}"
        >
            View Valid ID
        </a>

        <a
            class="document-link"
            target="_blank"
            rel="noopener noreferrer"
            href="{{ route('logistics.head-admin.applications.document', [$application->id, 'business-permit']) }}"
        >
            View Business Permit
        </a>
    </div>

    @if ($application->status === 'pending')

        <div class="actions">

            <div class="card">
                <h2>Approve Application</h2>

                <p>
                    Approval grants the provider access to
                    the Logistics workspace.
                </p>

                <form
                    method="POST"
                    action="{{ route('logistics.head-admin.applications.approve', $application->id) }}"
                    onsubmit="return confirm('Approve this Logistics application?');"
                >
                    @csrf

                    <button
                        type="submit"
                        class="approve-button"
                    >
                        Approve Application
                    </button>
                </form>
            </div>

            <div class="card">
                <h2>Reject Application</h2>

                <p>
                    Provide a reason for rejecting this application.
                </p>

                <form
                    method="POST"
                    action="{{ route('logistics.head-admin.applications.reject', $application->id) }}"
                    onsubmit="return confirm('Reject this Logistics application?');"
                >
                    @csrf

                    <textarea
                        name="rejection_reason"
                        placeholder="Explain why this application is being rejected..."
                        minlength="10"
                        maxlength="1000"
                        required
                    >{{ old('rejection_reason') }}</textarea>

                    <button
                        type="submit"
                        class="reject-button"
                    >
                        Reject Application
                    </button>
                </form>
            </div>

        </div>

    @else

        <div class="card">
            <h2>Application Reviewed</h2>

            <p>
                This application has already been
                {{ $application->status }}.
                No further decision is available.
            </p>
        </div>

    @endif

</div>

</body>
</html>
