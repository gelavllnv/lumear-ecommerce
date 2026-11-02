<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>LUMEAR — Head Admin Dashboard</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
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
            left: 0;
            top: 0;
        }

        .brand {
            font-size: 25px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 11px;
            opacity: .7;
            margin-bottom: 40px;
        }

        .nav-item {
            display: block;
            padding: 14px;
            border-radius: 9px;
            background: rgba(255,255,255,.12);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .logout {
            margin-top: 30px;
        }

        .logout button {
            background: transparent;
            border: 1px solid rgba(255,255,255,.35);
            border-radius: 9px;
            padding: 12px;
            color: white;
            cursor: pointer;
            width: 100%;
            font: inherit;
        }

        main {
            margin-left: 250px;
            padding: 35px;
        }

        h1 {
            color: #62191c;
            font-size: 27px;
        }

        .description {
            color: #847777;
            font-size: 13px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin: 30px 0;
        }

        .stat {
            background: white;
            border: 1px solid #eaded7;
            padding: 25px;
            border-radius: 15px;
        }

        .stat span {
            font-size: 12px;
            color: #847777;
        }

        .stat strong {
            display: block;
            margin-top: 12px;
            font-size: 32px;
            color: #62191c;
        }

        .panel {
            background: white;
            border: 1px solid #eaded7;
            border-radius: 15px;
            padding: 25px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th, td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid #f0e8e3;
        }

        th {
            color: #8b7777;
            font-size: 11px;
        }

        .status {
            font-weight: 800;
            text-transform: capitalize;
        }

        .pending {
            color: #a36a1c;
        }

        .approved {
            color: #26754b;
        }

        .rejected {
            color: #b33030;
        }

        @media (max-width: 900px) {
            .sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }

            main {
                margin-left: 0;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 500px) {
            .stats {
                grid-template-columns: 1fr;
            }

            main {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">

    <div class="brand">LUMEAR</div>

    <div class="subtitle">
        LOGISTICS HEAD ADMIN
    </div>

    <div class="nav-item">
        Dashboard
    </div>

    <form
        class="logout"
        method="POST"
        action="{{ route('logistics.head-admin.logout') }}"
    >
        @csrf

        <button type="submit">
            Log Out
        </button>
    </form>

</aside>

<main>

    <h1>Logistics Administration</h1>

    <p class="description">
        Welcome, {{ auth()->user()->name }}.
        Monitor Logistics partner registrations and approval statuses.
    </p>

    <section class="stats">

        <div class="stat">
            <span>Total Applications</span>
            <strong>{{ $statistics['total'] }}</strong>
        </div>

        <div class="stat">
            <span>Pending Applications</span>
            <strong>{{ $statistics['pending'] }}</strong>
        </div>

        <div class="stat">
            <span>Approved Applications</span>
            <strong>{{ $statistics['approved'] }}</strong>
        </div>

        <div class="stat">
            <span>Rejected Applications</span>
            <strong>{{ $statistics['rejected'] }}</strong>
        </div>

    </section>

    <section class="panel">

        <h3>Recent Logistics Applications</h3>

        <table>
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Applicant</th>
                    <th>Municipality</th>
                    <th>Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td>
                            <a
                                href="{{ route('logistics.head-admin.applications.show', $application->id) }}"
                                style="
                                    color: #62191c;
                                    font-weight: 700;
                                    text-decoration: underline;
                                "
                            >
                                {{ $application->company_name }}
                            </a>
                        </td>

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
                                {{ $application->status }}
                            </span>
                        </td>

                        <td>
                            {{ $application->created_at }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            No Logistics applications yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </section>

</main>

</body>
</html>
