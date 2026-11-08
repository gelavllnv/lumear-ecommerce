<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUMEAR — Logistics Applications</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f7f3f0;
            color: #302626;
        }

        .sidebar {
            width: 250px;
            background: #62191c;
            color: white;
            min-height: 100vh;
            padding: 30px 20px;
            position: fixed;
        }

        .brand {
            font-size: 25px;
            font-weight: 800;
        }

        .subtitle {
            font-size: 11px;
            opacity: .7;
            margin: 8px 0 35px;
        }

        .nav-link {
            display: block;
            color: white;
            text-decoration: none;
            padding: 14px;
            margin-bottom: 8px;
            border-radius: 9px;
            font-size: 13px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,.15);
        }

        main {
            margin-left: 250px;
            padding: 35px;
        }

        h1 { color: #62191c; }

        .subtitle-text {
            color: #847777;
            font-size: 13px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 28px 0;
        }

        .filter {
            padding: 11px 18px;
            border: 1px solid #e0cfc2;
            background: white;
            border-radius: 9px;
            text-decoration: none;
            color: #62191c;
            font-size: 12px;
            font-weight: 700;
        }

        .filter.active {
            background: #62191c;
            color: white;
        }

        .panel {
            background: white;
            border: 1px solid #eaded7;
            border-radius: 15px;
            padding: 24px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid #eee5df;
        }

        th {
            color: #8b7777;
            font-size: 11px;
        }

        .status {
            text-transform: capitalize;
            font-weight: 700;
        }

        .pending { color: #a36a1c; }
        .approved { color: #26754b; }
        .rejected { color: #b33030; }

        .view-button {
            background: #62191c;
            color: white;
            padding: 9px 14px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 12px;
        }

        .pagination { margin-top: 20px; }

        @media(max-width: 850px) {
            .sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }

            main {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">LUMEAR</div>
    <div class="subtitle">LOGISTICS HEAD ADMIN</div>

    <a class="nav-link"
       href="{{ route('logistics.head-admin.dashboard') }}">
        Dashboard
    </a>

    <a class="nav-link active"
       href="{{ route('logistics.head-admin.applications.index') }}">
        Applications
    </a>

    <form method="POST"
          action="{{ route('logistics.head-admin.logout') }}">
        @csrf
        <button type="submit"
                style="padding:12px;cursor:pointer;width:100%;margin-top:20px;">
            Log Out
        </button>
    </form>
</aside>

<main>
    <h1>Logistics Applications</h1>
    <p class="subtitle-text">
        Review and manage Logistics partner registrations.
    </p>

    <div class="filters">
        @foreach (['pending', 'approved', 'rejected', 'all'] as $filter)
            <a
                class="filter {{ $status === $filter ? 'active' : '' }}"
                href="{{ route('logistics.head-admin.applications.index', ['status' => $filter]) }}"
            >
                {{ ucfirst($filter) }}
            </a>
        @endforeach
    </div>

    <div class="panel">
        <table>
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Applicant</th>
                    <th>Municipality</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td>{{ $application->company_name }}</td>
                        <td>
                            {{ $application->first_name }}
                            {{ $application->last_name }}
                        </td>
                        <td>
                            {{ $application->city }},
                            {{ $application->province }}
                        </td>
                        <td>
                            <span class="status {{ $application->status }}">
                                {{ ucfirst($application->status) }}
                            </span>
                        </td>
                        <td>{{ $application->created_at }}</td>
                        <td>
                            <a
                                class="view-button"
                                href="{{ route('logistics.head-admin.applications.show', $application->id) }}"
                            >
                                Review
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            No applications found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="pagination">
            {{ $applications->links() }}
        </div>
    </div>
</main>

</body>
</html>
