<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>LUMEAR — Logistics Head Admin</title>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f5eeea;
            color: #261b1b;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: white;
            border: 1px solid #e0cfc2;
            border-radius: 20px;
            padding: 38px;
            box-shadow: 0 20px 60px rgba(98,25,28,.08);
        }

        .eyebrow {
            color: #873632;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        h1 {
            font-family: 'DM Serif Display', serif;
            font-weight: 400;
            font-size: 34px;
            margin: 12px 0;
            color: #62191c;
        }

        p {
            color: #756666;
            font-size: 13px;
            line-height: 1.8;
            margin-bottom: 28px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 9px;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #decfc7;
            border-radius: 9px;
            font: inherit;
            font-size: 13px;
            outline: none;
            margin-bottom: 20px;
        }

        input:focus {
            border-color: #873632;
            box-shadow: 0 0 0 3px rgba(135,54,50,.1);
        }

        button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 9px;
            background: #62191c;
            color: white;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        button:hover {
            background: #873632;
        }

        .error {
            background: #fff0f0;
            border: 1px solid #e7b8b8;
            color: #9b242b;
            border-radius: 9px;
            padding: 12px;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .footer {
            margin-top: 25px;
            text-align: center;
            color: #9e7161;
            font-size: 11px;
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="eyebrow">
        LUMEAR · LOGISTICS ADMINISTRATION
    </div>

    <h1>Head Admin Portal</h1>

    <p>
        Sign in to review Logistics partner applications,
        manage approvals, and supervise registered
        Logistics providers.
    </p>

    @if ($errors->any())
        <div class="error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('logistics.head-admin.login.submit') }}"
    >
        @csrf

        <label for="email">Email Address</label>

        <input
            id="email"
            type="email"
            name="email"
            value="{{ old('email') }}"
            placeholder="Enter admin email"
            autocomplete="email"
            required
        >

        <label for="password">Password</label>

        <input
            id="password"
            type="password"
            name="password"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Sign In to Head Admin
        </button>
    </form>

    <div class="footer">
        Authorized LUMEAR Logistics administrators only.
    </div>

</div>

</body>
</html>
