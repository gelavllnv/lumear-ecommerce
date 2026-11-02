<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LUMEAR | Logistics Registration</title>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --maroon: #62191c;
            --rose: #873632;
            --cream: #f3e7dd;
            --paper: #fffaf6;
            --text: #342421;
            --muted: #705a50;
            --border: #d5bcad;
            --green: #237449;
            --red: #ac3338;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--cream);
            color: var(--text);
            font: 15px 'DM Sans', sans-serif;
        }

        input, select, button { font: inherit; }
        button { cursor: pointer; }
        [hidden] { display: none !important; }
        a { text-decoration: none; color: inherit; }

        .layout {
            display: grid;
            grid-template-columns: minmax(280px, 32%) 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: linear-gradient(155deg, #311012, var(--maroon) 68%, #873632);
            color: white;
            padding: 40px clamp(25px, 4vw, 60px);
            display: flex;
            flex-direction: column;
        }

        .logo {
            font: 600 34px Fraunces, serif;
        }

        .side-content { margin: auto 0; }

        .eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #e7bba8;
        }

        .sidebar h1 {
            font: 400 clamp(40px, 4.2vw, 66px)/1.12 Fraunces, serif;
        }

        .sidebar h1 em { color: #e7bba8; }

        .sidebar p {
            line-height: 1.85;
            color: #f0d5c9;
        }

        .side-note {
            border: 1px solid #ffffff40;
            background: #ffffff10;
            padding: 17px;
            border-radius: 12px;
            line-height: 1.8;
        }

        .side-footer {
            border-top: 1px solid #ffffff30;
            padding-top: 18px;
            color: #e7c4b8;
            font-size: 12px;
        }

        .main {
            min-width: 0;
            padding: 28px clamp(16px, 4vw, 64px) 55px;
        }

        .topbar {
            text-align: right;
            color: var(--muted);
            font-size: 13px;
        }

        .topbar a {
            display: inline-block;
            border: 1px solid var(--maroon);
            border-radius: 30px;
            padding: 10px 18px;
            margin-left: 10px;
            color: var(--maroon);
            font-weight: 700;
        }

        .container {
            max-width: 820px;
            margin: 38px auto;
        }

        .heading h2 {
            font: 400 clamp(32px, 3.5vw, 44px) Fraunces, serif;
            margin: 10px 0;
        }

        .heading p, .description {
            color: var(--muted);
            line-height: 1.75;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin: 30px 0 10px;
            color: var(--maroon);
            font-weight: 700;
            font-size: 13px;
        }

        .track {
            height: 8px;
            border-radius: 9px;
            background: #ddc5b6;
            overflow: hidden;
        }

        .fill {
            height: 100%;
            width: 14.2857%;
            background: var(--maroon);
            transition: width .25s;
        }

        .steps {
            display: flex;
            justify-content: space-between;
            gap: 5px;
            margin: 16px 0 25px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #816a5e;
            font-size: 11px;
        }

        .step b {
            display: grid;
            place-items: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #dfcabe;
        }

        .step.active {
            color: var(--maroon);
            font-weight: 700;
        }

        .step.active b {
            background: var(--maroon);
            color: white;
        }

        .step.done b {
            background: #caa393;
            color: #411c1e;
        }

        .card {
            background: var(--paper);
            border: 1px solid var(--border);
            border-radius: 17px;
            padding: clamp(20px, 3vw, 36px);
            box-shadow: 0 12px 34px #36131514;
        }

        .kicker {
            color: var(--rose);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .card h3 {
            font: 400 31px Fraunces, serif;
            margin: 9px 0;
        }

        .description { margin: 0 0 24px; }

        .fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .field.full { grid-column: 1 / -1; }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .req { color: var(--red); }

        .input {
            width: 100%;
            height: 51px;
            border: 1.5px solid #c8afa0;
            border-radius: 9px;
            padding: 0 13px;
            background: white;
            color: var(--text);
            outline: none;
        }

        .input:focus, .otp-digit:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px #87363226;
        }

        .input:disabled, .input[readonly] {
            background: #eee0d6;
            color: #725b50;
        }

        .input.invalid {
            border-color: var(--red);
            background: #fff8f8;
        }

        .input.valid {
            border-color: var(--green);
        }

        .hint {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
            margin: 7px 0 0;
        }

        .status {
            font-size: 12px;
            line-height: 1.6;
            margin-top: 7px;
            overflow-wrap: anywhere;
        }

        .status.good {
            color: var(--green);
            font-weight: 700;
        }

        .status.bad {
            color: var(--red);
            font-weight: 700;
        }

        .file {
            width: 100%;
            padding: 17px;
            background: #fcf1e9;
            border: 1.5px dashed #b99683;
            border-radius: 10px;
        }

        .file::file-selector-button {
            background: #e6cdbf;
            border: 0;
            border-radius: 6px;
            color: var(--maroon);
            font-weight: 700;
            padding: 10px;
            margin-right: 10px;
            cursor: pointer;
        }

        .notice {
            background: #f3e1d4;
            border: 1px solid #e0c4b3;
            border-radius: 9px;
            padding: 15px;
            margin-top: 23px;
            color: #6b4539;
            font-size: 13px;
            line-height: 1.75;
        }

        .pw { position: relative; }
        .pw .input { padding-right: 73px; }

        .toggle {
            position: absolute;
            right: 9px;
            top: 10px;
            padding: 7px;
            border: 0;
            background: transparent;
            color: var(--maroon);
            font-weight: 700;
            font-size: 12px;
        }

        .otp-box {
            text-align: center;
            padding: 30px 15px;
            border-radius: 13px;
            border: 1px solid #d8b8a6;
            background: #f4e5da;
        }

        .otp-icon {
            width: 66px;
            height: 66px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--maroon);
            color: white;
            font-size: 29px;
            margin: 0 auto 17px;
        }

        .otp-box h4 {
            font: 500 26px Fraunces, serif;
            color: var(--maroon);
            margin: 8px 0;
        }

        .otp-box p {
            font-size: 13px;
            line-height: 1.7;
            color: var(--muted);
        }

        #otpEmail {
            display: block;
            color: var(--maroon);
            overflow-wrap: anywhere;
        }

        .otp-digits {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 24px 0;
        }

        .otp-digit {
            width: 48px;
            height: 56px;
            border: 2px solid #c9a797;
            border-radius: 8px;
            text-align: center;
            font-size: 23px;
            font-weight: 700;
            color: var(--maroon);
            background: white;
            outline: none;
        }

        .otp-resend {
            border: 0;
            background: transparent;
            color: var(--maroon);
            text-decoration: underline;
            font-weight: 700;
        }

        .otp-resend:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .review {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 11px;
        }

        .review-item {
            background: #f7eae1;
            padding: 13px;
            border-radius: 8px;
            overflow-wrap: anywhere;
        }

        .review-item small {
            display: block;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .check {
            display: flex;
            gap: 10px;
            line-height: 1.7;
            margin-top: 22px;
            font-size: 13px;
        }

        .check input {
            accent-color: var(--maroon);
            width: 17px;
            height: 17px;
            flex-shrink: 0;
            margin-top: 3px;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            border-top: 1px solid var(--border);
            margin-top: 25px;
            padding-top: 22px;
        }

        .btn {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--maroon);
            padding: 13px 22px;
            font-weight: 700;
        }

        .btn.primary {
            margin-left: auto;
            background: var(--maroon);
            border-color: var(--maroon);
            color: white;
        }

        .btn.primary:hover { background: var(--rose); }

        .btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .error {
            display: none;
            margin-top: 18px;
            padding: 13px;
            background: #ffeded;
            border: 1px solid #e4b1b1;
            border-radius: 8px;
            color: #9e292f;
            font-size: 13px;
            line-height: 1.6;
        }

        .error.show { display: block; }

        @media (max-width: 1100px) {
            .step span { display: none; }
        }

        @media (max-width: 950px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar { padding: 20px 25px; }
            .side-content, .side-footer { display: none; }
        }

        @media (max-width: 600px) {
            .main { padding: 20px 14px 40px; }
            .fields, .review { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
            .otp-digits { gap: 5px; }
            .otp-digit {
                width: 39px;
                height: 49px;
                font-size: 20px;
            }
            .card { padding: 20px; }
            .btn { padding: 12px 16px; }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <a class="logo" href="{{ url('/') }}">LUMEAR.</a>

        <div class="side-content">
            <div class="eyebrow">Logistics Partnership</div>

            <h1>
                Deliver more.<br>
                <em>Grow together.</em>
            </h1>

            <p>
                Register your sorting center and become part
                of the LUMEAR delivery network.
            </p>

            <div class="side-note">
                <strong>One handler per city or municipality.</strong>
                <p>
                    Each city or municipality may have only one
                    approved Logistics provider. Applications
                    are reviewed before activation.
                </p>
            </div>
        </div>

        <div class="side-footer">
            © {{ date('Y') }} LUMEAR · Logistics Partner Portal
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            Already registered?
            <a href="{{ route('logistics.login') }}">Sign in</a>
        </div>

        <div class="container">

            <div class="heading">
                <div class="eyebrow" style="color:var(--maroon)">
                    LOGISTICS ACCOUNT APPLICATION
                </div>

                <h2>Become a logistics partner.</h2>

                <p>
                    Complete seven steps to submit your
                    application for review.
                </p>
            </div>

            <div class="progress-info">
                <span id="progressTitle">Step 1 of 7 · Company</span>
                <span id="progressPercent">14%</span>
            </div>

            <div class="track">
                <div class="fill" id="progressFill"></div>
            </div>

            <div class="steps" id="steps">
                <div class="step active"><b>1</b><span>Company</span></div>
                <div class="step"><b>2</b><span>Representative</span></div>
                <div class="step"><b>3</b><span>Location</span></div>
                <div class="step"><b>4</b><span>Documents</span></div>
                <div class="step"><b>5</b><span>Security</span></div>
                <div class="step"><b>6</b><span>Email OTP</span></div>
                <div class="step"><b>7</b><span>Review</span></div>
            </div>

            <form
                id="logisticsForm"
                action="{{ route('logistics.register.submit') }}"
                method="POST"
                enctype="multipart/form-data"
                novalidate
            >
                @csrf

                <div class="card">

                    <!-- STEP 1: COMPANY -->
                    <section class="panel" data-step="1">
                        <div class="kicker">STEP 01 / 07</div>
                        <h3>Company information</h3>

                        <p class="description">
                            Enter your registered logistics business details.
                        </p>

                        <div class="fields">

                            <div class="field full">
                                <label for="company_name">
                                    Company / Business Name
                                    <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    id="company_name"
                                    name="company_name"
                                    maxlength="150"
                                    required
                                    placeholder="e.g. Bilis Hatid Express Inc."
                                >
                            </div>

                            <div class="field">
                                <label for="sec_number">
                                    SEC Registration Number
                                    <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    id="sec_number"
                                    name="sec_number"
                                    maxlength="11"
                                    required
                                    placeholder="AB123456789"
                                >

                                <p class="hint">
                                    Two letters followed by nine digits.
                                </p>

                                <div class="status" id="sec_numberStatus"></div>
                            </div>

                            <div class="field">
                                <label for="tin">
                                    Tax Identification Number (TIN)
                                    <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    id="tin"
                                    name="tin"
                                    maxlength="15"
                                    required
                                    placeholder="000-000-000-000"
                                >

                                <div class="status" id="tinStatus"></div>
                            </div>

                        </div>

                        <div class="notice">
                            Company details are checked against existing
                            Logistics applications when you submit.
                        </div>
                    </section>

                    <!-- STEP 2: REPRESENTATIVE -->
                    <section class="panel" data-step="2" hidden>
                        <div class="kicker">STEP 02 / 07</div>
                        <h3>Authorized representative</h3>

                        <p class="description">
                            Enter the details of the person responsible
                            for this Logistics account.
                        </p>

                        <div class="fields">

                            <div class="field">
                                <label for="first_name">
                                    First Name <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    id="first_name"
                                    name="first_name"
                                    minlength="2"
                                    maxlength="50"
                                    required
                                    placeholder="First name"
                                >
                            </div>

                            <div class="field">
                                <label for="last_name">
                                    Last Name <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    id="last_name"
                                    name="last_name"
                                    minlength="2"
                                    maxlength="50"
                                    required
                                    placeholder="Last name"
                                >
                            </div>

                            <div class="field">
                                <label for="middle_initial">
                                    Middle Initial
                                </label>

                                <input
                                    class="input"
                                    id="middle_initial"
                                    name="middle_initial"
                                    maxlength="1"
                                    placeholder="Optional"
                                >
                            </div>

                            <div class="field">
                                <label for="sex">
                                    Sex <span class="req">*</span>
                                </label>

                                <select class="input" id="sex" name="sex" required>
                                    <option value="">Select sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="birthday">
                                    Birthday <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    type="date"
                                    id="birthday"
                                    name="birthday"
                                    required
                                >
                            </div>

                            <div class="field">
                                <label for="age">Age</label>

                                <input
                                    class="input"
                                    id="age"
                                    readonly
                                    placeholder="Automatically calculated"
                                >
                            </div>

                            <div class="field">
                                <label for="email">
                                    Email Address <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    type="email"
                                    id="email"
                                    name="email"
                                    maxlength="150"
                                    required
                                    placeholder="example@email.com"
                                >
                            </div>

                            <div class="field">
                                <label for="mobile">
                                    Mobile Number <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    type="tel"
                                    id="mobile"
                                    name="mobile"
                                    maxlength="13"
                                    inputmode="tel"
                                    required
                                    placeholder="09XXXXXXXXX"
                                >

                                <p class="hint">
                                    09XXXXXXXXX or +639XXXXXXXXX.
                                </p>

                                <div class="status" id="mobileStatus"></div>
                            </div>

                        </div>
                    </section>

                    <!-- STEP 3: LOCATION -->
                    <section class="panel" data-step="3" hidden>
                        <div class="kicker">STEP 03 / 07</div>
                        <h3>Sorting center location</h3>

                        <p class="description">
                            Select the city or municipality assigned
                            to your Logistics business.
                        </p>

                        <input
                            type="hidden"
                            id="municipality_psgc_code"
                            name="municipality_psgc_code"
                        >

                        <div class="fields">

                            <div class="field">
                                <label for="region">
                                    Region <span class="req">*</span>
                                </label>

                                <select
                                    class="input"
                                    id="region"
                                    name="region"
                                    required
                                >
                                    <option value="">Loading regions...</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="province">
                                    Province
                                </label>

                                <select
                                    class="input"
                                    id="province"
                                    name="province"
                                    disabled
                                >
                                    <option value="">Select region first</option>
                                </select>
                            </div>

                            <div class="field full">
                                <label for="city">
                                    Assigned City / Municipality
                                    <span class="req">*</span>
                                </label>

                                <select
                                    class="input"
                                    id="city"
                                    name="city"
                                    required
                                    disabled
                                >
                                    <option value="">Select province first</option>
                                </select>

                                <div
                                    class="status"
                                    id="cityStatus"
                                    aria-live="polite"
                                ></div>
                            </div>

                            <div class="field">
                                <label for="barangay">
                                    Barangay <span class="req">*</span>
                                </label>

                                <select
                                    class="input"
                                    id="barangay"
                                    name="barangay"
                                    required
                                    disabled
                                >
                                    <option value="">Select city first</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="postal_code">
                                    Postal Code <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    id="postal_code"
                                    name="postal_code"
                                    maxlength="4"
                                    inputmode="numeric"
                                    required
                                    placeholder="Four-digit postal code"
                                >

                                <p class="hint" id="postalHint">
                                    Automatically filled for supported locations.
                                </p>
                            </div>

                            <div class="field">
                                <label for="house_number">
                                    House / Building Number
                                    <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    id="house_number"
                                    name="house_number"
                                    maxlength="100"
                                    required
                                    placeholder="Building number"
                                >
                            </div>

                            <div class="field">
                                <label for="street">
                                    Street Address
                                    <span class="req">*</span>
                                </label>

                                <input
                                    class="input"
                                    id="street"
                                    name="street"
                                    maxlength="150"
                                    required
                                    placeholder="Street name"
                                >
                            </div>

                        </div>

                        <div class="notice">
                            Only one approved Logistics provider is allowed
                            per city or municipality. Your selected location
                            will be checked against the database.
                        </div>
                    </section>

                    <!-- STEP 4: DOCUMENTS -->
                    <section class="panel" data-step="4" hidden>
                        <div class="kicker">STEP 04 / 07</div>
                        <h3>Verification documents</h3>

                        <p class="description">
                            Upload clear copies of the required documents.
                        </p>

                        <div class="fields">

                            <div class="field full">
                                <label for="valid_id">
                                    Representative Valid ID
                                    <span class="req">*</span>
                                </label>

                                <input
                                    class="file"
                                    type="file"
                                    id="valid_id"
                                    name="valid_id"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    required
                                >

                                <p class="hint">
                                    JPG, PNG, or PDF. Maximum 5 MB.
                                </p>

                                <div
                                    class="status"
                                    id="validIdStatus"
                                    aria-live="polite"
                                ></div>
                            </div>

                            <div class="field full">
                                <label for="business_permit">
                                    Business / DTI Permit
                                    <span class="req">*</span>
                                </label>

                                <input
                                    class="file"
                                    type="file"
                                    id="business_permit"
                                    name="business_permit"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    required
                                >

                                <p class="hint">
                                    JPG, PNG, or PDF. Maximum 5 MB.
                                </p>

                                <div
                                    class="status"
                                    id="permitStatus"
                                    aria-live="polite"
                                ></div>
                            </div>

                        </div>

                        <div class="notice">
                            Green confirmation means the selected document
                            passed browser-side validation. Documents will
                            be uploaded securely when you submit.
                        </div>
                    </section>

                    <!-- STEP 5: SECURITY -->
                    <section class="panel" data-step="5" hidden>
                        <div class="kicker">STEP 05 / 07</div>
                        <h3>Account security</h3>

                        <p class="description">
                            Create a secure password for your account.
                        </p>

                        <div class="fields">

                            <div class="field full">
                                <label for="password">
                                    Password <span class="req">*</span>
                                </label>

                                <div class="pw">
                                    <input
                                        class="input"
                                        type="password"
                                        id="password"
                                        name="password"
                                        minlength="8"
                                        autocomplete="new-password"
                                        required
                                        placeholder="Create a strong password"
                                    >

                                    <button
                                        type="button"
                                        class="toggle"
                                        data-target="password"
                                    >Show</button>
                                </div>

                                <p class="hint">
                                    At least 8 characters, including uppercase,
                                    lowercase, number, and special character.
                                </p>

                                <div
                                    class="status"
                                    id="passwordStatus"
                                    aria-live="polite"
                                ></div>
                            </div>

                            <div class="field full">
                                <label for="password_confirmation">
                                    Confirm Password
                                    <span class="req">*</span>
                                </label>

                                <div class="pw">
                                    <input
                                        class="input"
                                        type="password"
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        autocomplete="new-password"
                                        required
                                        placeholder="Repeat your password"
                                    >

                                    <button
                                        type="button"
                                        class="toggle"
                                        data-target="password_confirmation"
                                    >Show</button>
                                </div>

                                <div
                                    class="status"
                                    id="confirmPasswordStatus"
                                    aria-live="polite"
                                ></div>
                            </div>

                        </div>
                    </section>

                    <!-- STEP 6: OTP -->
                    <section class="panel" data-step="6" hidden>
                        <div class="kicker">STEP 06 / 07</div>
                        <h3>Email verification</h3>

                        <p class="description">
                            Verify your email using a six-digit code.
                        </p>

                        <div class="otp-box">
                            <div class="otp-icon">✉</div>
                            <h4>Verify your email</h4>

                            <p>Your code will be sent to:</p>
                            <strong id="otpEmail">example@email.com</strong>

                            <div class="otp-digits">
                                <input class="otp-digit" maxlength="1" inputmode="numeric" aria-label="OTP digit 1">
                                <input class="otp-digit" maxlength="1" inputmode="numeric" aria-label="OTP digit 2">
                                <input class="otp-digit" maxlength="1" inputmode="numeric" aria-label="OTP digit 3">
                                <input class="otp-digit" maxlength="1" inputmode="numeric" aria-label="OTP digit 4">
                                <input class="otp-digit" maxlength="1" inputmode="numeric" aria-label="OTP digit 5">
                                <input class="otp-digit" maxlength="1" inputmode="numeric" aria-label="OTP digit 6">
                            </div>

                            <p>
                                Code expires in:
                                <strong id="otpCountdown">Not started</strong>
                            </p>

                            <p>
                                Didn't receive the code?
                                <button
                                    type="button"
                                    class="otp-resend"
                                    id="resendOtp"
                                    disabled
                                >Resend Code</button>
                            </p>

                            <div class="notice">
                                Your verification code expires after five
                                minutes. You may request another code
                                after the resend cooldown.
                            </div>

                            <div
                                class="status"
                                id="otpStatus"
                                role="status"
                            ></div>
                        </div>
                    </section>

                    <!-- STEP 7: REVIEW -->
                    <section class="panel" data-step="7" hidden>
                        <div class="kicker">STEP 07 / 07</div>
                        <h3>Review application</h3>

                        <p class="description">
                            Review your details before submitting.
                        </p>

                        <div class="review" id="review"></div>

                        <label class="check">
                            <input
                                type="checkbox"
                                id="terms"
                                name="terms"
                                value="1"
                                required
                            >

                            <span>
                                I confirm that my information is accurate
                                and agree to the Terms of Service
                                and Privacy Policy.
                            </span>
                        </label>

                        <div class="notice">
                            Your application will be submitted with
                            <strong>Pending Approval</strong> status.
                            You must wait for administrator approval
                            before accessing Logistics operations.
                        </div>
                    </section>

                    <div class="error" id="error" role="alert"></div>

                    <div class="actions">
                        <button
                            type="button"
                            class="btn"
                            id="backBtn"
                            hidden
                        >← Back</button>

                        <button
                            type="button"
                            class="btn primary"
                            id="nextBtn"
                        >Continue →</button>

                        <button
                            type="button"
                            class="btn primary"
                            id="finishBtn"
                            hidden
                        >Submit Application</button>
                    </div>

                </div>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const $ = id => document.getElementById(id);

    const form = $('logisticsForm');
    const panels = [...document.querySelectorAll('.panel')];
    const indicators = [...document.querySelectorAll('.step')];
    const otpInputs = [...document.querySelectorAll('.otp-digit')];

    const stepNames = [
        'Company',
        'Representative',
        'Location',
        'Documents',
        'Security',
        'Email OTP',
        'Review'
    ];

    const urls = {
        validate: @json(route('logistics.register.validate')),
        sendOtp: @json(route('logistics.register.send-otp')),
        verifyOtp: @json(route('logistics.register.verify-otp')),
        submit: @json(route('logistics.register.submit')),
        municipality: @json(route('logistics.municipality.availability'))
    };

    const csrfToken = document.querySelector(
        'meta[name="csrf-token"]'
    ).content;

    const namePattern = /^[\p{L}]+(?:[ .'-][\p{L}]+)*\.?$/u;
    const mobilePattern = /^(?:09\d{9}|\+639\d{9})$/;
    const secPattern = /^[A-Za-z]{2}\d{9}$/;
    const tinPattern = /^\d{3}-\d{3}-\d{3}-\d{3}$/;
    const postalPattern = /^\d{4}$/;

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'application/pdf'
    ];

    const maxFileSize = 5 * 1024 * 1024;

    let step = 1;
    let addressRequestId = 0;
    let availabilityRequestId = 0;

    let municipalityAvailable = false;
    let municipalityChecking = false;

    let otpVerified = false;
    let otpVerifiedEmail = null;
    let otpSent = false;
    let otpSending = false;
    let otpVerifying = false;

    let otpTimer = null;
    let resendTimer = null;

    let submitting = false;

    function showError(message, input = null) {
        $('error').textContent = message;
        $('error').classList.add('show');

        if (input) {
            input.classList.add('invalid');
            input.focus();
        }
    }

    function clearError() {
        $('error').textContent = '';
        $('error').classList.remove('show');

        document.querySelectorAll('.input.invalid').forEach(input => {
            input.classList.remove('invalid');
        });
    }

    function setStatus(id, message, good) {
        const element = $(id);

        if (!element) return;

        element.textContent = message;
        element.className = 'status';

        if (message) {
            element.classList.add(good ? 'good' : 'bad');
        }
    }

    function fieldStatus(input, message, good) {
        let status = $(input.id + 'Status');

        if (!status) {
            status = document.createElement('div');
            status.id = input.id + 'Status';
            status.className = 'status';
            input.insertAdjacentElement('afterend', status);
        }

        setStatus(status.id, message, good);

        input.classList.toggle('valid', Boolean(message && good));
        input.classList.toggle('invalid', Boolean(message && !good));
    }

    function firstServerError(result) {
        if (result.errors) {
            const messages = Object.values(result.errors).flat();

            if (messages.length) {
                return messages[0];
            }
        }

        return result.message || 'Something went wrong.';
    }

    async function postJson(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(firstServerError(result));
        }

        return result;
    }

    function validPassword(value) {
        return value.length >= 8 &&
            /[A-Z]/.test(value) &&
            /[a-z]/.test(value) &&
            /\d/.test(value) &&
            /[^A-Za-z0-9]/.test(value);
    }

    function showStep(number) {
        step = number;

        panels.forEach(panel => {
            panel.hidden = Number(panel.dataset.step) !== number;
        });

        indicators.forEach((indicator, index) => {
            indicator.classList.toggle(
                'active',
                index === number - 1
            );

            indicator.classList.toggle(
                'done',
                index < number - 1
            );
        });

        $('progressTitle').textContent =
            `Step ${number} of 7 · ${stepNames[number - 1]}`;

        $('progressPercent').textContent =
            Math.round(number / 7 * 100) + '%';

        $('progressFill').style.width =
            (number / 7 * 100) + '%';

        $('backBtn').hidden = number === 1;
        $('nextBtn').hidden = number === 7;
        $('finishBtn').hidden = number !== 7;

        clearError();

        if (number === 6) {
            $('otpEmail').textContent =
                $('email').value.trim();

            if (!otpSent && !otpVerified) {
                sendLogisticsOtp();
            }
        }

        if (number === 7) {
            buildReview();
        }

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // ==========================================
    // LIVE FIELD VALIDATION
    // ==========================================

    function checkName(input) {
        const value = input.value.trim();

        const valid =
            value.length >= 2 &&
            value.length <= 50 &&
            namePattern.test(value);

        fieldStatus(
            input,
            !value ? '' : valid
                ? '✓ Valid name.'
                : '✕ Enter a valid name using letters and standard punctuation.',
            valid
        );

        return valid;
    }

    ['first_name', 'last_name'].forEach(id => {
        $(id).addEventListener('input', function () {
            checkName(this);
        });
    });

    $('middle_initial').addEventListener('input', function () {
        const value = this.value.trim();
        const valid = !value || /^\p{L}$/u.test(value);

        fieldStatus(
            this,
            !value ? '' : valid
                ? '✓ Valid middle initial.'
                : '✕ Only one letter is allowed.',
            valid
        );
    });

    $('mobile').addEventListener('input', function () {
        const value = this.value;
        const valid = mobilePattern.test(value);

        fieldStatus(
            this,
            !value ? '' : valid
                ? '✓ Valid mobile number.'
                : '✕ Enter 09XXXXXXXXX or +639XXXXXXXXX.',
            valid
        );
    });

    $('sec_number').addEventListener('input', function () {
        const value = this.value.trim();
        const valid = secPattern.test(value);

        fieldStatus(
            this,
            !value ? '' : valid
                ? '✓ Valid SEC number format.'
                : '✕ Enter two letters followed by nine digits.',
            valid
        );
    });

    $('tin').addEventListener('input', function () {
        const value = this.value.trim();
        const valid = tinPattern.test(value);

        fieldStatus(
            this,
            !value ? '' : valid
                ? '✓ Valid TIN format.'
                : '✕ Use 000-000-000-000.',
            valid
        );
    });

    $('postal_code').addEventListener('input', function () {
        const valid = postalPattern.test(this.value);

        fieldStatus(
            this,
            !this.value ? '' : valid
                ? '✓ Valid postal code.'
                : '✕ Postal code must contain four digits.',
            valid
        );
    });

    [
        'company_name',
        'first_name',
        'last_name',
        'middle_initial',
        'sec_number',
        'tin',
        'email',
        'house_number',
        'street'
    ].forEach(id => {
        $(id).addEventListener('blur', function () {
            this.value = this.value.trim();
        });
    });

    // ==========================================
    // BIRTHDAY AND AGE
    // ==========================================

    const today = new Date();

    $('birthday').max = [
        today.getFullYear(),
        String(today.getMonth() + 1).padStart(2, '0'),
        String(today.getDate()).padStart(2, '0')
    ].join('-');

    $('birthday').addEventListener('change', function () {
        if (!this.value) {
            $('age').value = '';
            return;
        }

        const birth = new Date(this.value + 'T00:00:00');
        const now = new Date();

        if (Number.isNaN(birth.getTime()) || birth > now) {
            $('age').value = '';
            return;
        }

        let age = now.getFullYear() - birth.getFullYear();

        if (
            now.getMonth() < birth.getMonth() ||
            (
                now.getMonth() === birth.getMonth() &&
                now.getDate() < birth.getDate()
            )
        ) {
            age--;
        }

        $('age').value = age >= 0 ? age : '';
    });

    // ==========================================
    // PASSWORD VALIDATION
    // ==========================================

    function passwordFeedback() {
        const password = $('password').value;
        const confirmation = $('password_confirmation').value;

        const strong = validPassword(password);
        const matches = strong && password === confirmation;

        setStatus(
            'passwordStatus',
            !password ? '' : strong
                ? '✓ All password requirements met.'
                : '✕ Requires 8+ characters, uppercase, lowercase, number, and special character.',
            strong
        );

        setStatus(
            'confirmPasswordStatus',
            !confirmation ? '' : matches
                ? '✓ Passwords match.'
                : '✕ Passwords do not match or password is invalid.',
            matches
        );
    }

    $('password').addEventListener('input', passwordFeedback);
    $('password_confirmation').addEventListener(
        'input',
        passwordFeedback
    );

    document.querySelectorAll('.toggle').forEach(button => {
        button.addEventListener('click', function () {
            const input = $(this.dataset.target);
            const visible = input.type === 'text';

            input.type = visible ? 'password' : 'text';
            this.textContent = visible ? 'Show' : 'Hide';
        });
    });

    // ==========================================
    // DOCUMENT VALIDATION
    // ==========================================

    function validateFile(id, statusId, label) {
        const file = $(id).files[0];

        if (!file) {
            setStatus(statusId, '', false);
            return false;
        }

        const valid =
            allowedTypes.includes(file.type) &&
            file.size > 0 &&
            file.size <= maxFileSize;

        setStatus(
            statusId,
            valid
                ? `✓ ${label} selected: ${file.name}`
                : '✕ Invalid document. Use JPG, PNG, or PDF, maximum 5 MB.',
            valid
        );

        return valid;
    }

    $('valid_id').addEventListener('change', () => {
        validateFile('valid_id', 'validIdStatus', 'Valid ID');
    });

    $('business_permit').addEventListener('change', () => {
        validateFile(
            'business_permit',
            'permitStatus',
            'Business Permit'
        );
    });

    // ==========================================
    // PHILIPPINE ADDRESS API
    // ==========================================

    const postalCodes = {
        laguna: {
            'santa cruz': '4009',
            'victoria': '4011',
            'pila': '4010',
            'bay': '4033',
            'calamba': '4027',
            'san pablo': '4000',
            'los banos': '4030',
            'cabuyao': '4025',
            'binan': '4024',
            'santa rosa': '4026',
            'pagsanjan': '4008',
            'lumban': '4014',
            'paete': '4016',
            'siniloan': '4019',
            'san pedro': '4023',
            'alaminos': '4001',
            'nagcarlan': '4002',
            'majayjay': '4005',
            'liliw': '4004',
            'pakil': '4017',
            'pangil': '4018',
            'famy': '4021',
            'santa maria': '4022',
            'rizal': '4003',
            'calauan': '4012',
            'cavinti': '4013',
            'kalayaan': '4015',
            'magdalena': '4007',
            'luisiana': '4032'
        }
    };

    function normalizeLocation(value) {
        return (value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/^city of /, '')
            .replace(/^municipality of /, '')
            .replace(/^city /, '')
            .replace(/ city$/, '')
            .trim();
    }

    function updatePostal() {
        const province = normalizeLocation($('province').value);
        const city = normalizeLocation($('city').value);

        const postal = postalCodes[province]?.[city] || '';

        $('postal_code').value = postal;
        $('postal_code').readOnly = Boolean(postal);

        $('postalHint').textContent = postal
            ? '✓ Postal code automatically detected.'
            : 'Enter the postal code manually for this location.';
    }

    function resetSelect(id, label) {
        $(id).replaceChildren(new Option(label, ''));
        $(id).disabled = true;
    }

    function fillSelect(id, items, label) {
        const select = $(id);

        select.replaceChildren(new Option(label, ''));

        items.sort((a, b) => a.name.localeCompare(b.name));

        items.forEach(item => {
            const option = new Option(item.name, item.name);
            option.dataset.code = item.code;
            select.add(option);
        });

        select.disabled = false;
    }

    function selectedCode(id) {
        return $(id).selectedOptions[0]?.dataset.code || '';
    }

    async function psgc(path) {
        const response = await fetch(
            'https://psgc.cloud/api' + path,
            {
                headers: {
                    Accept: 'application/json'
                }
            }
        );

        if (!response.ok) {
            throw new Error('Address service unavailable.');
        }

        const json = await response.json();
        const data = Array.isArray(json) ? json : json.data;

        if (!Array.isArray(data)) {
            throw new Error('Invalid address response.');
        }

        return data.map(item => ({
            name: item.name,
            code: item.code
        }));
    }

    function resetMunicipality() {
        availabilityRequestId++;
        municipalityAvailable = false;
        municipalityChecking = false;

        $('municipality_psgc_code').value = '';
        setStatus('cityStatus', '', false);
    }

    async function checkMunicipalityAvailability(code) {
        const request = ++availabilityRequestId;

        municipalityAvailable = false;
        municipalityChecking = true;

        setStatus(
            'cityStatus',
            'Checking municipality availability...',
            true
        );

        try {
            const url = new URL(
                urls.municipality,
                window.location.origin
            );

            url.searchParams.set(
                'municipality_psgc_code',
                code
            );

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json'
                },
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (
                request !== availabilityRequestId ||
                $('municipality_psgc_code').value !== code
            ) {
                return;
            }

            if (!response.ok) {
                throw new Error(firstServerError(result));
            }

            municipalityAvailable = result.available === true;

            setStatus(
                'cityStatus',
                municipalityAvailable
                    ? '✓ This city or municipality is available.'
                    : '✕ This city or municipality already has an approved Logistics provider.',
                municipalityAvailable
            );

        } catch (error) {
            if (request !== availabilityRequestId) {
                return;
            }

            municipalityAvailable = false;

            setStatus(
                'cityStatus',
                '✕ Unable to verify municipality availability. Please try again.',
                false
            );

        } finally {
            if (request === availabilityRequestId) {
                municipalityChecking = false;
            }
        }
    }

    async function loadRegions() {
        try {
            const regions = await psgc('/regions');
            fillSelect('region', regions, 'Select region');

        } catch (error) {
            resetSelect('region', 'Unable to load regions');

            showError(
                'Unable to load regions. Check your internet connection.'
            );
        }
    }

    $('region').addEventListener('change', async function () {
        const request = ++addressRequestId;

        resetMunicipality();

        resetSelect('province', 'Loading provinces...');
        resetSelect('city', 'Select province first');
        resetSelect('barangay', 'Select city first');

        $('postal_code').value = '';
        $('postal_code').readOnly = false;

        const regionCode = selectedCode('region');

        if (!regionCode) {
            resetSelect('province', 'Select region first');
            return;
        }

        try {
            const provinces = await psgc(
                '/regions/' +
                encodeURIComponent(regionCode) +
                '/provinces'
            );

            if (request !== addressRequestId) return;

            if (provinces.length) {
                fillSelect(
                    'province',
                    provinces,
                    'Select province'
                );
            } else {
                resetSelect('province', 'Not applicable');

                const cities = await psgc(
                    '/regions/' +
                    encodeURIComponent(regionCode) +
                    '/cities-municipalities'
                );

                if (request === addressRequestId) {
                    fillSelect(
                        'city',
                        cities,
                        'Select city / municipality'
                    );
                }
            }

        } catch (error) {
            if (request === addressRequestId) {
                showError('Unable to load provinces or cities.');
            }
        }
    });

    $('province').addEventListener('change', async function () {
        const request = ++addressRequestId;

        resetMunicipality();

        resetSelect('city', 'Loading cities...');
        resetSelect('barangay', 'Select city first');

        $('postal_code').value = '';
        $('postal_code').readOnly = false;

        const provinceCode = selectedCode('province');

        if (!provinceCode) {
            resetSelect('city', 'Select province first');
            return;
        }

        try {
            const cities = await psgc(
                '/provinces/' +
                encodeURIComponent(provinceCode) +
                '/cities-municipalities'
            );

            if (request === addressRequestId) {
                fillSelect(
                    'city',
                    cities,
                    'Select city / municipality'
                );
            }

        } catch (error) {
            if (request === addressRequestId) {
                showError('Unable to load cities.');
            }
        }
    });

    $('city').addEventListener('change', async function () {
        const request = ++addressRequestId;

        resetMunicipality();
        resetSelect('barangay', 'Loading barangays...');

        updatePostal();

        const cityCode = selectedCode('city');

        $('municipality_psgc_code').value = cityCode;

        if (!cityCode) {
            resetSelect('barangay', 'Select city first');
            return;
        }

        checkMunicipalityAvailability(cityCode);

        try {
            const barangays = await psgc(
                '/cities-municipalities/' +
                encodeURIComponent(cityCode) +
                '/barangays'
            );

            if (request === addressRequestId) {
                fillSelect(
                    'barangay',
                    barangays,
                    'Select barangay'
                );
            }

        } catch (error) {
            if (request === addressRequestId) {
                showError('Unable to load barangays.');
            }
        }
    });

    // ==========================================
    // EMAIL OTP
    // ==========================================

    function formatOtpTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainder = seconds % 60;

        return `${minutes}:${String(remainder).padStart(2, '0')}`;
    }

    function startOtpCountdown(seconds) {
        clearInterval(otpTimer);

        let remaining = seconds;

        $('otpCountdown').textContent = formatOtpTime(remaining);

        otpTimer = setInterval(() => {
            remaining--;

            if (remaining <= 0) {
                clearInterval(otpTimer);

                $('otpCountdown').textContent = 'Expired';

                if (!otpVerified) {
                    setStatus(
                        'otpStatus',
                        '✕ Code expired. Please request another.',
                        false
                    );
                }

                return;
            }

            $('otpCountdown').textContent =
                formatOtpTime(remaining);

        }, 1000);
    }

    function startResendCountdown(seconds) {
        clearInterval(resendTimer);

        const button = $('resendOtp');
        let remaining = seconds;

        button.disabled = true;
        button.textContent = `Resend in ${remaining}s`;

        resendTimer = setInterval(() => {
            remaining--;

            if (remaining <= 0) {
                clearInterval(resendTimer);

                button.disabled = false;
                button.textContent = 'Resend Code';
                return;
            }

            button.textContent = `Resend in ${remaining}s`;

        }, 1000);
    }

    async function sendLogisticsOtp() {
        if (otpSending || otpVerified) return;

        const email = $('email').value.trim().toLowerCase();

        if (!email || !$('email').checkValidity()) {
            showError('Enter a valid email address.');
            return;
        }

        otpSending = true;
        $('resendOtp').disabled = true;

        setStatus(
            'otpStatus',
            'Sending verification code...',
            true
        );

        try {
            const result = await postJson(
                urls.sendOtp,
                { email }
            );

            otpVerified = false;
            otpVerifiedEmail = null;
            otpSent = true;

            otpInputs.forEach(input => {
                input.value = '';
            });

            startOtpCountdown(result.expires_in || 300);
            startResendCountdown(result.resend_after || 60);

            setStatus(
                'otpStatus',
                '✓ Verification code sent. Please check your inbox.',
                true
            );

        } catch (error) {
            setStatus(
                'otpStatus',
                '✕ ' + error.message,
                false
            );

            if (!otpSent) {
                $('resendOtp').disabled = false;
                $('resendOtp').textContent = 'Try Sending Again';
            }

        } finally {
            otpSending = false;
        }
    }

    async function verifyLogisticsOtp() {
        if (otpVerifying) return false;

        const code = otpInputs
            .map(input => input.value)
            .join('');

        if (!/^\d{6}$/.test(code)) {
            showError('Enter all six OTP digits.');
            otpInputs[0].focus();
            return false;
        }

        otpVerifying = true;

        try {
            const result = await postJson(
                urls.verifyOtp,
                {
                    email: $('email').value.trim(),
                    otp: code
                }
            );

            otpVerified = true;
            otpVerifiedEmail =
                $('email').value.trim().toLowerCase();

            clearInterval(otpTimer);
            clearInterval(resendTimer);

            $('otpCountdown').textContent = 'Verified';
            $('resendOtp').disabled = true;

            setStatus(
                'otpStatus',
                '✓ ' + result.message,
                true
            );

            return true;

        } catch (error) {
            otpVerified = false;

            setStatus(
                'otpStatus',
                '✕ ' + error.message,
                false
            );

            return false;

        } finally {
            otpVerifying = false;
        }
    }

    $('resendOtp').addEventListener(
        'click',
        sendLogisticsOtp
    );

    otpInputs.forEach((input, index) => {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 1);

            if (this.value && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', function (event) {
            if (
                event.key === 'Backspace' &&
                !this.value &&
                index > 0
            ) {
                otpInputs[index - 1].focus();
            }
        });

        input.addEventListener('paste', function (event) {
            const text = event.clipboardData
                .getData('text')
                .replace(/\D/g, '')
                .slice(0, 6);

            if (!text) return;

            event.preventDefault();

            otpInputs.forEach((digit, digitIndex) => {
                digit.value = text[digitIndex] || '';
            });

            otpInputs[Math.min(text.length, 5)].focus();
        });
    });

    $('email').addEventListener('input', function () {
        const currentEmail = this.value.trim().toLowerCase();

        if (
            otpVerifiedEmail &&
            otpVerifiedEmail !== currentEmail
        ) {
            otpVerified = false;
            otpVerifiedEmail = null;
            otpSent = false;

            setStatus(
                'otpStatus',
                'Email changed. You must verify the new email.',
                false
            );
        }
    });

    // ==========================================
    // REVIEW PAGE
    // ==========================================

    function buildReview() {
        const fields = [
            ['Company Name', 'company_name'],
            ['SEC Registration Number', 'sec_number'],
            ['TIN', 'tin'],
            ['First Name', 'first_name'],
            ['Last Name', 'last_name'],
            ['Middle Initial', 'middle_initial'],
            ['Sex', 'sex'],
            ['Birthday', 'birthday'],
            ['Age', 'age'],
            ['Email', 'email'],
            ['Mobile', 'mobile'],
            ['Region', 'region'],
            ['Province', 'province'],
            ['City / Municipality', 'city'],
            ['Barangay', 'barangay'],
            ['Postal Code', 'postal_code'],
            ['House / Building Number', 'house_number'],
            ['Street', 'street']
        ];

        const review = $('review');
        review.replaceChildren();

        fields.forEach(([label, id]) => {
            const item = document.createElement('div');
            item.className = 'review-item';

            const small = document.createElement('small');
            small.textContent = label;

            const value = document.createElement('strong');
            value.textContent = $(id).value || 'Not applicable';

            item.append(small, value);
            review.appendChild(item);
        });

        [
            ['Valid ID', 'valid_id'],
            ['Business Permit', 'business_permit']
        ].forEach(([label, id]) => {
            const item = document.createElement('div');
            item.className = 'review-item';

            const small = document.createElement('small');
            small.textContent = label;

            const value = document.createElement('strong');
            value.textContent =
                $(id).files[0]?.name || 'No file selected';

            item.append(small, value);
            review.appendChild(item);
        });
    }

    // ==========================================
    // STEP VALIDATION
    // ==========================================

    function validateRequiredFields(stepNumber) {
        const panel = document.querySelector(
            `.panel[data-step="${stepNumber}"]`
        );

        const fields = [
            ...panel.querySelectorAll(
                'input[required], select[required]'
            )
        ];

        for (const input of fields) {
            if (!input.checkValidity()) {
                showError(
                    `Please complete the ${input.id.replaceAll('_', ' ')} field correctly.`,
                    input
                );

                return false;
            }
        }

        return true;
    }

    async function validateCurrentStep() {
        clearError();

        if (step === 6) {
            if (
                otpVerified &&
                otpVerifiedEmail ===
                    $('email').value.trim().toLowerCase()
            ) {
                return true;
            }

            const verified = await verifyLogisticsOtp();

            if (!verified) {
                showError(
                    'Please verify your email before continuing.'
                );
            }

            return verified;
        }

        if (step === 7) {
            return $('terms').checked;
        }

        if (!validateRequiredFields(step)) {
            return false;
        }

        if (step === 1) {
            if (!secPattern.test($('sec_number').value.trim())) {
                showError(
                    'Enter a valid SEC registration number.',
                    $('sec_number')
                );
                return false;
            }

            if (!tinPattern.test($('tin').value.trim())) {
                showError(
                    'Enter a valid TIN.',
                    $('tin')
                );
                return false;
            }
        }

        if (step === 2) {
            if (
                !checkName($('first_name')) ||
                !checkName($('last_name'))
            ) {
                showError('Enter valid representative names.');
                return false;
            }

            if (
                $('middle_initial').value &&
                !/^\p{L}$/u.test($('middle_initial').value)
            ) {
                showError('Enter a valid middle initial.');
                return false;
            }

            if (!mobilePattern.test($('mobile').value)) {
                showError('Enter a valid mobile number.');
                return false;
            }

            if (Number($('age').value) < 18) {
                showError(
                    'The authorized representative must be at least 18 years old.'
                );
                return false;
            }
        }

        if (step === 3) {
            if (!$('municipality_psgc_code').value) {
                showError('Please select a valid municipality.');
                return false;
            }

            if (municipalityChecking) {
                showError(
                    'Please wait for municipality availability checking.'
                );
                return false;
            }

            if (!municipalityAvailable) {
                showError(
                    'The selected municipality is unavailable or could not be verified.'
                );
                return false;
            }

            if (!postalPattern.test($('postal_code').value)) {
                showError('Enter a valid four-digit postal code.');
                return false;
            }
        }

        if (step === 4) {
            if (
                !validateFile(
                    'valid_id',
                    'validIdStatus',
                    'Valid ID'
                ) ||
                !validateFile(
                    'business_permit',
                    'permitStatus',
                    'Business Permit'
                )
            ) {
                showError('Upload both required documents.');
                return false;
            }
        }

        if (step === 5) {
            if (!validPassword($('password').value)) {
                showError(
                    'Your password does not meet the security requirements.'
                );
                return false;
            }

            if (
                $('password').value !==
                $('password_confirmation').value
            ) {
                showError('Passwords do not match.');
                return false;
            }
        }

        // Server-side validation for steps 1–5.
        try {
            const data = new FormData(form);

            data.set('step', String(step));

            const response = await fetch(urls.validate, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin',
                body: data
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(firstServerError(result));
            }

            return true;

        } catch (error) {
            showError(error.message);
            return false;
        }
    }

    // ==========================================
    // NEXT / BACK BUTTONS
    // ==========================================

    $('nextBtn').addEventListener('click', async function () {
        if (this.disabled) return;

        this.disabled = true;
        const originalText = this.textContent;
        this.textContent = 'Checking...';

        try {
            const valid = await validateCurrentStep();

            if (valid && step < 7) {
                showStep(step + 1);
            }

        } finally {
            this.disabled = false;
            this.textContent = originalText;
        }
    });

    $('backBtn').addEventListener('click', function () {
        if (submitting) return;

        if (step > 1) {
            showStep(step - 1);
        }
    });

    // ==========================================
    // FINAL DATABASE SUBMISSION
    // ==========================================

    async function submitApplication() {
        if (submitting) return;

        clearError();

        if (!$('terms').checked) {
            showError(
                'Please accept the Terms of Service and Privacy Policy.'
            );
            return;
        }

        if (
            !otpVerified ||
            otpVerifiedEmail !==
                $('email').value.trim().toLowerCase()
        ) {
            showError(
                'Your email must be verified before submission.'
            );
            return;
        }

        if (
            !municipalityAvailable ||
            !$('municipality_psgc_code').value
        ) {
            showError(
                'Please return to Step 3 and verify your municipality.'
            );
            return;
        }

        submitting = true;

        const button = $('finishBtn');
        const backButton = $('backBtn');

        button.disabled = true;
        backButton.disabled = true;

        button.textContent = 'Submitting Application...';

        try {
            const formData = new FormData(form);

            formData.set(
                'municipality_psgc_code',
                $('municipality_psgc_code').value
            );

            const response = await fetch(urls.submit, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin',
                body: formData
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(firstServerError(result));
            }

            if (result.success && result.redirect) {
                window.location.assign(result.redirect);
                return;
            }

            throw new Error(
                'The server did not return a valid confirmation.'
            );

        } catch (error) {
            showError(error.message);

            submitting = false;
            button.disabled = false;
            backButton.disabled = false;

            button.textContent = 'Submit Application';
        }
    }

    $('finishBtn').addEventListener(
        'click',
        submitApplication
    );

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        submitApplication();
    });

    // ==========================================
    // INITIALIZATION
    // ==========================================

    loadRegions();
    showStep(1);
});
</script>

</body>
</html>
