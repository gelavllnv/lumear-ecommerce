
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create Account — LUMEAR</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&display=swap" rel="stylesheet">

    <style>
        :root {
            --maroon: #62191c;
            --dark: #3d1012;
            --light-maroon: #873632;
            --cream: #fffaf3;
            --paper: #fffdf9;
            --beige: #f4e7da;
            --text: #382520;
            --muted: #907c70;
            --border: #e8d9cc;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'DM Sans', sans-serif;
            background: #f8f0e7;
            color: var(--text);
            font-size: 13px;
        }

        button, input, select { font: inherit; }
        button { cursor: pointer; }
        a { color: inherit; text-decoration: none; }
        [hidden] { display: none !important; }

        .page {
            display: grid;
            grid-template-columns: minmax(300px, 36%) minmax(0, 64%);
            min-height: 100vh;
        }

        .branding {
            position: relative;
            background: linear-gradient(155deg, #3d1012, #62191c 65%, #873632);
            color: white;
            padding: 40px clamp(26px, 4vw, 70px);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .branding::after {
            content: '';
            position: absolute;
            width: 440px;
            height: 440px;
            border: 80px solid #ffffff09;
            border-radius: 50%;
            bottom: -290px;
            left: -220px;
            pointer-events: none;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font: italic 600 32px 'Fraunces', serif;
            width: fit-content;
        }

        .logo img {
            width: 46px;
            height: 46px;
            object-fit: contain;
        }

        .brand-content {
            margin: auto 0;
            padding: 55px 0;
            position: relative;
            z-index: 1;
        }

        .brand-eyebrow {
            font-size: 10px;
            letter-spacing: .24em;
            color: #eac4b3;
            font-weight: 700;
        }

        .brand-content h1 {
            font: 400 clamp(43px, 4.3vw, 66px)/1.13 'Fraunces', serif;
            letter-spacing: -.055em;
            margin: 24px 0;
        }

        .brand-content h1 em { color: #e8bda7; }

        .brand-content p {
            color: #edd2c6;
            line-height: 1.9;
            max-width: 400px;
        }

        .brand-features {
            display: grid;
            gap: 18px;
            margin-top: 42px;
        }

        .brand-feature {
            display: flex;
            align-items: center;
            gap: 14px;
            color: #f6e7df;
            font-size: 12px;
        }

        .feature-icon {
            width: 39px;
            height: 39px;
            border: 1px solid #ba8073;
            border-radius: 50%;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            font-size: 18px;
        }

        .brand-footer {
            color: #d4aa9c;
            font-size: 11px;
            border-top: 1px solid #ffffff22;
            padding-top: 20px;
        }

        .main {
            display: flex;
            flex-direction: column;
            padding: 27px clamp(20px, 4vw, 60px) 45px;
            background: radial-gradient(circle at top, #fffaf3, #f8efe5 85%);
        }

        .topbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 14px;
            color: var(--muted);
            font-size: 12px;
        }

        .signin {
            border: 1px solid var(--maroon);
            color: var(--maroon);
            border-radius: 30px;
            padding: 10px 19px;
            font-weight: 700;
        }

        .signin:hover { background: #f3e2d7; }

        .wizard {
            width: 100%;
            max-width: 790px;
            margin: auto;
            padding-top: 25px;
        }

        .wizard-heading .eyebrow {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .2em;
            color: var(--light-maroon);
        }

        .wizard-heading h2 {
            font: 400 clamp(32px, 3.4vw, 45px) 'Fraunces', serif;
            letter-spacing: -.045em;
            margin: 9px 0;
        }

        .wizard-heading p {
            color: var(--muted);
            line-height: 1.7;
            margin: 0;
        }

        .progress-area { margin: 27px 0 21px; }

        .progress-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 11px;
        }

        .progress-top strong { color: var(--maroon); }
        .progress-top span { color: var(--muted); }

        .progress-track {
            width: 100%;
            height: 6px;
            border-radius: 20px;
            background: #ead9cd;
            overflow: hidden;
        }

        .progress-fill {
            width: 16.6667%;
            height: 100%;
            background: var(--maroon);
            border-radius: inherit;
            transition: width .3s ease;
        }

        .step-dots {
            display: flex;
            justify-content: space-between;
            gap: 5px;
            margin-top: 14px;
        }

        .step-dot {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #aa968a;
            font-size: 10px;
            white-space: nowrap;
        }

        .step-dot .number {
            width: 21px;
            height: 21px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #ebded3;
            color: #90776b;
            font-size: 10px;
            font-weight: 700;
        }

        .step-dot.active {
            color: var(--maroon);
            font-weight: 700;
        }

        .step-dot.active .number {
            background: var(--maroon);
            color: white;
        }

        .step-dot.done .number {
            background: #d7b6a6;
            color: var(--dark);
        }

        .wizard-card {
            background: var(--paper);
            border: 1px solid #eadfd5;
            border-radius: 18px;
            padding: clamp(22px, 3vw, 35px);
            box-shadow: 0 15px 45px #4c24100b;
        }

        .step-panel { animation: appear .25s ease; }

        @keyframes appear {
            from { opacity: 0; transform: translateY(7px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-kicker {
            color: var(--light-maroon);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .15em;
        }

        .step-title {
            font: 400 29px 'Fraunces', serif;
            margin: 9px 0;
            letter-spacing: -.03em;
        }

        .step-description {
            margin: 0 0 25px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.7;
        }

        .role-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .role-card {
            position: relative;
            border: 1px solid var(--border);
            background: #fffaf5;
            border-radius: 13px;
            padding: 22px;
            text-align: left;
            min-height: 180px;
            transition: .2s;
        }

        .role-card:hover { border-color: #b88a78; }

        .role-card.selected {
            border: 2px solid var(--maroon);
            background: #fff6f1;
            padding: 21px;
        }

        .role-symbol {
            display: grid;
            place-items: center;
            width: 49px;
            height: 49px;
            background: #f3e0d1;
            border-radius: 50%;
            font-size: 24px;
            margin-bottom: 17px;
        }

        .role-card strong {
            display: block;
            font: 500 22px 'Fraunces', serif;
            margin-bottom: 8px;
        }

        .role-card small {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.65;
        }

        .role-check {
            position: absolute;
            top: 17px;
            right: 17px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 1px solid #c9b5a8;
        }

        .selected .role-check {
            border: 6px solid var(--maroon);
        }

        .fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .field.full { grid-column: 1 / -1; }

        .field label {
            display: block;
            margin-bottom: 8px;
            color: #533c33;
            font-size: 11px;
            font-weight: 700;
        }

        .required { color: #ad4545; }

        .input {
            display: block;
            width: 100%;
            height: 45px;
            border: 1px solid #dfd1c5;
            border-radius: 8px;
            background: #fffdfa;
            padding: 0 13px;
            outline: none;
            color: var(--text);
        }

        .input:focus {
            border-color: var(--light-maroon);
            box-shadow: 0 0 0 3px #87363215;
        }

        .input:disabled { background: #f1eae4; }
        .input::placeholder { color: #b3a49a; }

        .input[readonly] {
            background: #f5ece5;
            color: var(--maroon);
            font-weight: 700;
            cursor: default;
        }

        .hint {
            color: #9c8a7f;
            font-size: 10px;
            margin-top: 7px;
            line-height: 1.6;
        }

        .file-input {
            display: block;
            width: 100%;
            border: 1px dashed #cba998;
            background: #fff8f0;
            border-radius: 8px;
            padding: 13px;
            font-size: 11px;
        }

        .file-input::file-selector-button {
            border: none;
            background: #efddcf;
            color: var(--maroon);
            padding: 9px;
            border-radius: 5px;
            margin-right: 10px;
            cursor: pointer;
        }

        .subsection {
            padding-top: 23px;
            margin-top: 25px;
            border-top: 1px solid var(--border);
        }

        .subsection h4 {
            font: 500 21px 'Fraunces', serif;
            margin: 0 0 17px;
        }

        .password-wrap { position: relative; }
        .password-wrap input { padding-right: 65px; }

        .show-password {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 7px;
            border: none;
            background: transparent;
            color: var(--maroon);
            font-size: 11px;
            font-weight: 700;
        }

        .notice {
            background: #f8eadd;
            border: 1px solid #ead2bf;
            border-radius: 9px;
            padding: 14px;
            color: #80503f;
            font-size: 11px;
            line-height: 1.8;
            margin-top: 20px;
        }

        .checkbox-row {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            line-height: 1.7;
            font-size: 11px;
            margin-top: 20px;
        }

        .checkbox-row input {
            accent-color: var(--maroon);
            margin-top: 4px;
        }

        .review-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .review-item {
            padding: 13px;
            background: #fbf3eb;
            border-radius: 8px;
            min-width: 0;
        }

        .review-item small {
            display: block;
            font-size: 10px;
            color: #987b6d;
            margin-bottom: 6px;
        }

        .review-item strong {
            display: block;
            font-size: 12px;
            overflow-wrap: anywhere;
            line-height: 1.6;
        }

        .wizard-actions {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding-top: 23px;
            margin-top: 25px;
            border-top: 1px solid var(--border);
        }

        .btn {
            min-height: 46px;
            padding: 0 22px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid transparent;
        }

        .btn-back {
            border-color: #d7c3b5;
            background: #fffaf5;
            color: var(--maroon);
        }

        .btn-next {
            background: var(--maroon);
            color: white;
            margin-left: auto;
        }

        .btn-next:hover { background: var(--light-maroon); }

        .error-message {
            display: none;
            padding: 13px;
            border-radius: 8px;
            background: #fff0ee;
            color: #992e2e;
            font-size: 11px;
            line-height: 1.7;
            margin-top: 16px;
        }

        .error-message.visible { display: block; }

        .preview-result {
            display: none;
            background: #ecf5ed;
            border: 1px solid #c6e0c9;
            color: #2f6542;
            padding: 15px;
            border-radius: 9px;
            margin-top: 18px;
            font-size: 12px;
            line-height: 1.7;
        }

        .preview-result.visible { display: block; }

        @media (max-width: 1050px) {
            .page { grid-template-columns: 1fr; }
            .branding { padding: 23px; }
            .brand-content, .brand-footer { display: none; }
            .main { padding: 25px 20px 50px; }
            .wizard { padding-top: 35px; }
        }

        @media (max-width: 650px) {
            .fields { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
            .step-dots .step-label { display: none; }
            .role-card { padding: 15px; min-height: 175px; }
            .role-card.selected { padding: 14px; }
            .role-card strong { font-size: 19px; }
            .review-grid { grid-template-columns: 1fr; }
            .wizard-card { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="page">

    <aside class="branding">
        <a href="{{ url('/home') }}" class="logo">
            <img src="{{ asset('images/lumear-logo.png') }}" alt="">
            Lumear
        </a>

        <div class="brand-content">
            <div class="brand-eyebrow">YOUR MARKETPLACE STARTS HERE</div>

            <h1>
                A world of<br>
                possibilities<br>
                <em>awaits you.</em>
            </h1>

            <p>
                Join Lumear as a buyer or seller.
                A thoughtful marketplace built for everyday discoveries.
            </p>

            <div class="brand-features">
                <div class="brand-feature">
                    <span class="feature-icon">✦</span>
                    Discover products you'll love
                </div>

                <div class="brand-feature">
                    <span class="feature-icon">▤</span>
                    Build your own online store
                </div>

                <div class="brand-feature">
                    <span class="feature-icon">♡</span>
                    Connect with the Lumear community
                </div>
            </div>
        </div>

        <div class="brand-footer">
            © {{ date('Y') }} Lumear · Academic development project
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <span>Already have an account?</span>
            <a href="{{ url('/login') }}" class="signin">Sign In</a>
        </div>

        <div class="wizard">

            <div class="wizard-heading">
                <span class="eyebrow">BECOME PART OF LUMEAR</span>
                <h2>Create your account.</h2>
                <p>Complete the steps below to get started.</p>
            </div>

            <div class="progress-area">
                <div class="progress-top">
                    <strong id="progressTitle">Step 1 of 6 · Account Type</strong>
                    <span id="progressPercent">17% Complete</span>
                </div>

                <div class="progress-track">
                    <div class="progress-fill" id="progressFill"></div>
                </div>

                <div class="step-dots">
                    @foreach (['Type', 'Personal', 'Address', 'Verification', 'Security', 'Review'] as $index => $label)
                        <div class="step-dot {{ $index === 0 ? 'active' : '' }}"
                             data-step-dot="{{ $index + 1 }}">
                            <span class="number">{{ $index + 1 }}</span>
                            <span class="step-label">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <form id="registerWizard"
                  enctype="multipart/form-data"
                  novalidate>

                @csrf

                <input type="hidden" name="role" id="role" value="buyer">

                <div class="wizard-card">

                    {{-- STEP 1: ACCOUNT TYPE --}}
                    <section class="step-panel" data-step="1">
                        <div class="step-kicker">STEP 01 / 06</div>
                        <h3 class="step-title">How will you use Lumear?</h3>

                        <p class="step-description">
                            Select your account type to personalize your registration.
                        </p>

                        <div class="role-grid">
                            <button type="button"
                                    class="role-card selected"
                                    id="buyerRole"
                                    aria-pressed="true">
                                <span class="role-check"></span>
                                <span class="role-symbol">♧</span>
                                <strong>I'm a Buyer</strong>
                                <small>Explore, shop, and save your favorite products.</small>
                            </button>

                            <button type="button"
                                    class="role-card"
                                    id="sellerRole"
                                    aria-pressed="false">
                                <span class="role-check"></span>
                                <span class="role-symbol">▤</span>
                                <strong>I'm a Seller</strong>
                                <small>Create a store and reach more customers.</small>
                            </button>
                        </div>

                        <div class="notice">
                            Your account type determines which information
                            you'll need to provide during registration.
                        </div>
                    </section>

                    {{-- STEP 2: PERSONAL INFORMATION --}}
                    <section class="step-panel" data-step="2" hidden>
                        <div class="step-kicker">STEP 02 / 06</div>
                        <h3 class="step-title">Tell us about yourself.</h3>

                        <p class="step-description">
                            Enter your personal information accurately.
                        </p>

                        <div class="fields">
                            <div class="field">
                                <label>First Name <span class="required">*</span></label>
                                <input class="input" name="first_name" id="firstName"
                                       placeholder="Juan" maxlength="100" required>
                            </div>

                            <div class="field">
                                <label>Last Name <span class="required">*</span></label>
                                <input class="input" name="last_name" id="lastName"
                                       placeholder="Dela Cruz" maxlength="100" required>
                            </div>

                            <div class="field">
                                <label>Middle Initial</label>
                                <input class="input" name="middle_initial"
                                       id="middleInitial" placeholder="S." maxlength="2">
                            </div>

                            <div class="field">
                                <label>Sex <span class="required">*</span></label>
                                <select class="input" name="sex" id="sex" required>
                                    <option value="">Select sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Birthday <span class="required">*</span></label>
                                <input class="input" type="date" name="birthday"
                                       id="birthday" required>
                            </div>

                            <div class="field">
                                <label>Age (Auto-generated)</label>
                                <input class="input" name="age" id="age"
                                       placeholder="Calculated automatically" readonly>
                            </div>

                            <div class="field full">
                                <label>Email Address <span class="required">*</span></label>
                                <input class="input" type="email" name="email" id="email"
                                       autocomplete="email"
                                       placeholder="juan@email.com" maxlength="254" required>
                            </div>

                            <div class="field full">
                                <label>Contact Number <span class="required">*</span></label>
                                <input class="input" type="tel" name="contact_no"
                                       id="contact" placeholder="09XXXXXXXXX"
                                       inputmode="numeric" maxlength="11"
                                       pattern="09[0-9]{9}" required>
                                <p class="hint">Enter an 11-digit Philippine mobile number.</p>
                            </div>
                        </div>
                    </section>

                    {{-- STEP 3: ADDRESS --}}
                    <section class="step-panel" data-step="3" hidden>
                        <div class="step-kicker">STEP 03 / 06</div>
                        <h3 class="step-title">Where are you located?</h3>

                        <p class="step-description">
                            Select your Philippine address. Your postal code
                            will be filled automatically where available.
                        </p>

                        <div class="fields">
                            <div class="field full">
                                <label>Region <span class="required">*</span></label>
                                <select class="input" name="region" id="region" required>
                                    <option value="">Loading regions...</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Province <span class="required">*</span></label>
                                <select class="input" name="province" id="province" required disabled>
                                    <option value="">Select region first</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>City / Municipality <span class="required">*</span></label>
                                <select class="input" name="city" id="city" required disabled>
                                    <option value="">Select province first</option>
                                </select>
                            </div>

                            <div class="field full">
                                <label>Barangay <span class="required">*</span></label>
                                <select class="input" name="barangay" id="barangay" required disabled>
                                    <option value="">Select city first</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>House / Unit Number <span class="required">*</span></label>
                                <input class="input" name="house_number" id="houseNumber"
                                       placeholder="123 / Unit 4" maxlength="100" required>
                            </div>

                            <div class="field">
                                <label>Street / Subdivision <span class="required">*</span></label>
                                <input class="input" name="street" id="street"
                                       placeholder="Mabini Street" maxlength="150" required>
                            </div>

                            <div class="field full">
                                <label>
                                    Postal Code (Auto-generated)
                                    <span class="required">*</span>
                                </label>

                                <input class="input"
                                       name="postal_code"
                                       id="postalCode"
                                       placeholder="Select your city / municipality first"
                                       inputmode="numeric"
                                       maxlength="4"
                                       pattern="[0-9]{4}"
                                       readonly
                                       required>

                                <p class="hint" id="postalHint">
                                    Your postal code will appear automatically
                                    after selecting your city or municipality.
                                </p>
                            </div>
                        </div>
                    </section>

                    {{-- STEP 4: VERIFICATION --}}
                    <section class="step-panel" data-step="4" hidden>
                        <div class="step-kicker">STEP 04 / 06</div>
                        <h3 class="step-title">Verify your information.</h3>

                        <p class="step-description">
                            Provide the required documents for account review.
                        </p>

                        <div class="fields">
                            <div class="field full">
                                <label>Valid ID <span class="required">*</span></label>
                                <input class="file-input" type="file"
                                       name="valid_id" id="validId"
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       required>
                                <p class="hint">PDF, JPG, or PNG. Maximum 5 MB.</p>
                            </div>
                        </div>

                        <div class="subsection" id="sellerFields" hidden>
                            <h4>Seller Business Information</h4>

                            <div class="fields">
                                <div class="field full">
                                    <label>Business Name <span class="required">*</span></label>
                                    <input class="input" name="business_name"
                                           id="businessName"
                                           placeholder="Registered business name"
                                           maxlength="150">
                                </div>

                                <div class="field full">
                                    <label>Business Category <span class="required">*</span></label>
                                    <select class="input" name="business_category"
                                            id="businessCategory">
                                        <option value="">Select category</option>
                                        <option>Pet Supplies</option>
                                        <option>Kids & Baby</option>
                                        <option>Electronics</option>
                                        <option>Home & Garden</option>
                                        <option>Women's Fashion</option>
                                        <option>Men's Fashion</option>
                                        <option>Sports</option>
                                        <option>Beauty & Personal Care</option>
                                        <option>Books & Media</option>
                                        <option>Food</option>
                                        <option>Furniture</option>
                                        <option>Accessories</option>
                                        <option>Other</option>
                                    </select>
                                </div>

                                <div class="field full">
                                    <label>Business / DTI Permit <span class="required">*</span></label>
                                    <input class="file-input" type="file"
                                           name="business_permit" id="businessPermit"
                                           accept=".pdf,.jpg,.jpeg,.png">
                                    <p class="hint">PDF, JPG, or PNG. Maximum 5 MB.</p>
                                </div>
                            </div>
                        </div>

                        <div class="notice">
                            Documents will be reviewed by the administrator
                            once the registration backend is connected.
                        </div>
                    </section>

                    {{-- STEP 5: ACCOUNT SECURITY --}}
                    <section class="step-panel" data-step="5" hidden>
                        <div class="step-kicker">STEP 05 / 06</div>
                        <h3 class="step-title">Secure your account.</h3>

                        <p class="step-description">
                            Create a strong password to protect your information.
                        </p>

                        <div class="fields">
                            <div class="field full">
                                <label>Password <span class="required">*</span></label>
                                <div class="password-wrap">
                                    <input class="input" type="password"
                                           name="password" id="password"
                                           autocomplete="new-password"
                                           placeholder="Create a password"
                                           minlength="8" required>
                                    <button class="show-password" type="button"
                                            data-password="password">Show</button>
                                </div>
                            </div>

                            <div class="field full">
                                <label>Confirm Password <span class="required">*</span></label>
                                <div class="password-wrap">
                                    <input class="input" type="password"
                                           name="password_confirmation"
                                           id="confirmPassword"
                                           autocomplete="new-password"
                                           placeholder="Confirm your password"
                                           minlength="8" required>
                                    <button class="show-password" type="button"
                                            data-password="confirmPassword">Show</button>
                                </div>
                            </div>
                        </div>

                        <div class="notice">
                            Your password must contain at least 8 characters,
                            uppercase and lowercase letters, a number, and a symbol.
                        </div>
                    </section>

                    {{-- STEP 6: REVIEW --}}
                    <section class="step-panel" data-step="6" hidden>
                        <div class="step-kicker">STEP 06 / 06</div>
                        <h3 class="step-title">One last look.</h3>

                        <p class="step-description">
                            Review your registration details before proceeding.
                        </p>

                        <div class="review-grid" id="reviewGrid"></div>

                        <div class="notice">
                            <strong>Administrator Approval Required</strong><br>
                            Registration is subject to administrator review.
                            Actual submission and email notifications will be
                            connected to your team's backend.
                        </div>

                        <label class="checkbox-row">
                            <input type="checkbox" name="agree" id="agree" required>
                            <span>
                                I confirm that my information is accurate and
                                agree to Lumear's Terms of Service and Privacy Policy.
                            </span>
                        </label>
                    </section>

                    <div class="error-message"
                         id="wizardError"
                         role="alert"></div>

                    <div class="preview-result"
                         id="previewResult"
                         role="status"></div>

                    <div class="wizard-actions">
                        <button class="btn btn-back"
                                type="button"
                                id="backButton"
                                hidden>
                            ← Back
                        </button>

                        <button class="btn btn-next"
                                type="button"
                                id="nextButton">
                            Continue →
                        </button>

                        <button class="btn btn-next"
                                type="button"
                                id="submitButton"
                                hidden>
                            Review Application ✓
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </main>
</div>

<script>
(function () {
    'use strict';

    const form = document.getElementById('registerWizard');
    const panels = [...document.querySelectorAll('[data-step]')];
    const dots = [...document.querySelectorAll('[data-step-dot]')];

    const nextButton = document.getElementById('nextButton');
    const backButton = document.getElementById('backButton');
    const submitButton = document.getElementById('submitButton');

    const errorBox = document.getElementById('wizardError');
    const previewResult = document.getElementById('previewResult');

    const progressTitle = document.getElementById('progressTitle');
    const progressPercent = document.getElementById('progressPercent');
    const progressFill = document.getElementById('progressFill');

    const stepNames = [
        'Account Type',
        'Personal Information',
        'Address',
        'Verification',
        'Account Security',
        'Review'
    ];

    let currentStep = 1;
    let addressRequest = 0;

    const field = id => document.getElementById(id);

    /*
    |--------------------------------------------------------------------------
    | POSTAL CODE LOOKUP
    |--------------------------------------------------------------------------
    | PSGC supplies geographic divisions but not postal codes.
    | These entries cover selected Laguna cities/municipalities.
    | Add verified entries for other provinces before production.
    |--------------------------------------------------------------------------
    */

    const postalCodeDatabase = {
        'laguna': {
            'alaminos': '4001',
            'bay': '4033',
            'binan': '4024',
            'biñan': '4024',
            'cabuyao': '4025',
            'calamba': '4027',
            'calauan': '4012',
            'cavinti': '4013',
            'famy': '4021',
            'kalayaan': '4015',
            'liliw': '4004',
            'los banos': '4030',
            'los baños': '4030',
            'lumban': '4014',
            'luisiana': '4032',
            'magdalena': '4007',
            'mahayhay': '4005',
            'majayjay': '4005',
            'nagcarlan': '4002',
            'paete': '4016',
            'pagsanjan': '4008',
            'pakil': '4017',
            'pangil': '4018',
            'pila': '4010',
            'rizal': '4003',
            'san pablo': '4000',
            'san pedro': '4023',
            'santa cruz': '4009',
            'sta cruz': '4009',
            'santa maria': '4022',
            'santa rosa': '4026',
            'sta rosa': '4026',
            'siniloan': '4019',
            'victoria': '4011'
        }
    };

    function normalizeLocation(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/^city of\s+/, '')
            .replace(/^municipality of\s+/, '')
            .replace(/^city\s+/, '')
            .replace(/\s+city$/, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function clearPostalCode() {
        const input = field('postalCode');

        input.value = '';
        input.readOnly = true;
        input.placeholder = 'Select your city / municipality first';

        field('postalHint').textContent =
            'Your postal code will appear automatically after selecting your city or municipality.';
    }

    function updatePostalCode() {
        const province = normalizeLocation(field('province').value);
        const city = normalizeLocation(field('city').value);

        const postalInput = field('postalCode');
        const postalHint = field('postalHint');

        postalInput.value = '';
        postalInput.readOnly = true;

        if (!city) {
            clearPostalCode();
            return;
        }

        const postalCode =
            postalCodeDatabase[province]?.[city] || '';

        if (postalCode) {
            postalInput.value = postalCode;
            postalInput.placeholder = '';
            postalHint.textContent =
                '✓ Postal code automatically filled based on your city / municipality.';
        } else {
            postalInput.readOnly = false;
            postalInput.placeholder = 'Enter your 4-digit postal code';
            postalHint.textContent =
                'Automatic postal-code lookup is not yet available for this location. Please enter the correct 4-digit code.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | WIZARD CONTROLS
    |--------------------------------------------------------------------------
    */

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.add('visible');
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.classList.remove('visible');
    }

    function setRole(role) {
        field('role').value = role;

        const seller = role === 'seller';

        field('buyerRole').classList.toggle('selected', !seller);
        field('sellerRole').classList.toggle('selected', seller);

        field('buyerRole').setAttribute('aria-pressed', String(!seller));
        field('sellerRole').setAttribute('aria-pressed', String(seller));

        field('sellerFields').hidden = !seller;

        field('businessName').required = seller;
        field('businessCategory').required = seller;
        field('businessPermit').required = seller;

        clearError();
        previewResult.classList.remove('visible');
    }

    field('buyerRole').addEventListener('click', () => setRole('buyer'));
    field('sellerRole').addEventListener('click', () => setRole('seller'));

    function showStep(step) {
        currentStep = step;

        panels.forEach(panel => {
            panel.hidden = Number(panel.dataset.step) !== step;
        });

        dots.forEach(dot => {
            const number = Number(dot.dataset.stepDot);

            dot.classList.toggle('active', number === step);
            dot.classList.toggle('done', number < step);
        });

        const percent = Math.round(step / 6 * 100);

        progressTitle.textContent =
            `Step ${step} of 6 · ${stepNames[step - 1]}`;

        progressPercent.textContent = percent + '% Complete';
        progressFill.style.width = percent + '%';

        backButton.hidden = step === 1;
        nextButton.hidden = step === 6;
        submitButton.hidden = step !== 6;

        clearError();

        if (step === 6) {
            renderReview();
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function checkInput(input) {
        if (!input.checkValidity()) {
            input.reportValidity();
            input.focus();
            return false;
        }

        return true;
    }

    function validateFiles() {
        const maxSize = 5 * 1024 * 1024;
        const allowed = ['application/pdf', 'image/jpeg', 'image/png'];

        const inputs = [field('validId')];

        if (field('role').value === 'seller') {
            inputs.push(field('businessPermit'));
        }

        for (const input of inputs) {
            const file = input.files[0];

            if (!file) {
                showError('Please upload all required documents.');
                input.focus();
                return false;
            }

            if (!allowed.includes(file.type) || file.size > maxSize) {
                showError(
                    'Documents must be PDF, JPG, or PNG and no larger than 5 MB.'
                );
                input.focus();
                return false;
            }
        }

        return true;
    }

    function validateStep(step) {
        clearError();

        const panel = panels.find(
            item => Number(item.dataset.step) === step
        );

        const inputs = [
            ...panel.querySelectorAll('input, select')
        ].filter(input =>
            !input.disabled &&
            !input.closest('[hidden]') &&
            input.type !== 'hidden'
        );

        for (const input of inputs) {
            if (!checkInput(input)) return false;
        }

        if (step === 2) {
            if (!/^09\d{9}$/.test(field('contact').value)) {
                showError('Please enter a valid 11-digit Philippine mobile number.');
                return false;
            }

            if (field('age').value === '') {
                showError('Please enter a valid birthday.');
                return false;
            }
        }

        if (step === 3) {
            if (
                !field('region').value ||
                (!field('province').value && field('province').required) ||
                !field('city').value ||
                !field('barangay').value
            ) {
                showError('Please complete your Philippine address.');
                return false;
            }

            if (!/^\d{4}$/.test(field('postalCode').value)) {
                showError('Please provide a valid 4-digit postal code.');
                field('postalCode').focus();
                return false;
            }
        }

        if (step === 4 && !validateFiles()) {
            return false;
        }

        if (step === 5) {
            const password = field('password').value;
            const confirm = field('confirmPassword').value;

            const strong =
                password.length >= 8 &&
                /[A-Z]/.test(password) &&
                /[a-z]/.test(password) &&
                /[0-9]/.test(password) &&
                /[^A-Za-z0-9]/.test(password);

            if (!strong) {
                showError(
                    'Use at least 8 characters, uppercase and lowercase letters, a number, and a symbol.'
                );
                return false;
            }

            if (password !== confirm) {
                showError('Your passwords do not match.');
                return false;
            }
        }

        return true;
    }

    nextButton.addEventListener('click', function () {
        if (!validateStep(currentStep)) return;

        if (currentStep < 6) {
            showStep(currentStep + 1);
        }
    });

    backButton.addEventListener('click', function () {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    });

    /*
    |--------------------------------------------------------------------------
    | PERSONAL INFORMATION
    |--------------------------------------------------------------------------
    */

    field('contact').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });

    field('middleInitial').addEventListener('input', function () {
        this.value = this.value
            .replace(/[^a-zA-Z]/g, '')
            .slice(0, 1)
            .toUpperCase();
    });

    field('postalCode').addEventListener('input', function () {
        if (!this.readOnly) {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        }
    });

    const today = new Date();

    field('birthday').max = [
        today.getFullYear(),
        String(today.getMonth() + 1).padStart(2, '0'),
        String(today.getDate()).padStart(2, '0')
    ].join('-');

    field('birthday').addEventListener('change', function () {
        if (!this.value) {
            field('age').value = '';
            return;
        }

        const birth = new Date(this.value + 'T00:00:00');

        if (Number.isNaN(birth.getTime()) || birth > today) {
            field('age').value = '';
            return;
        }

        let age = today.getFullYear() - birth.getFullYear();

        if (
            today.getMonth() < birth.getMonth() ||
            (
                today.getMonth() === birth.getMonth() &&
                today.getDate() < birth.getDate()
            )
        ) {
            age--;
        }

        field('age').value = age >= 0 ? age : '';
    });

    document.querySelectorAll('[data-password]').forEach(button => {
        button.addEventListener('click', function () {
            const input = field(this.dataset.password);
            const visible = input.type === 'password';

            input.type = visible ? 'text' : 'password';
            this.textContent = visible ? 'Hide' : 'Show';
        });
    });

    /*
    |--------------------------------------------------------------------------
    | PHILIPPINE ADDRESS API
    |--------------------------------------------------------------------------
    */

    function resetSelect(select, text) {
        select.replaceChildren(new Option(text, ''));
        select.disabled = true;
    }

    function fillSelect(select, items, placeholder) {
        select.replaceChildren(new Option(placeholder, ''));

        items.sort((a, b) => a.name.localeCompare(b.name));

        items.forEach(item => {
            const option = new Option(item.name, item.name);
            option.dataset.code = item.code;
            select.add(option);
        });

        select.disabled = false;
    }

    function selectedCode(select) {
        return select.selectedOptions[0]?.dataset.code || '';
    }

    async function getPSGC(endpoint) {
        const response = await fetch(
            'https://psgc.cloud/api' + endpoint,
            { headers: { Accept: 'application/json' } }
        );

        if (!response.ok) {
            throw new Error('Address service unavailable');
        }

        const result = await response.json();
        const data = Array.isArray(result) ? result : result.data;

        if (!Array.isArray(data)) {
            throw new Error('Invalid address response');
        }

        return data.map(item => ({
            name: item.name,
            code: item.code
        }));
    }

    async function loadRegions() {
        try {
            const regions = await getPSGC('/regions');
            fillSelect(field('region'), regions, 'Select region');
        } catch {
            resetSelect(field('region'), 'Unable to load regions');
            showError(
                'Unable to load Philippine address data. Check your internet connection.'
            );
        }
    }

    field('region').addEventListener('change', async function () {
        const request = ++addressRequest;

        resetSelect(field('province'), 'Loading provinces...');
        resetSelect(field('city'), 'Select province first');
        resetSelect(field('barangay'), 'Select city first');

        field('province').required = true;

        clearPostalCode();

        const code = selectedCode(this);
        if (!code) return;

        try {
            const provinces = await getPSGC(
                '/regions/' + encodeURIComponent(code) + '/provinces'
            );

            if (request !== addressRequest) return;

            if (provinces.length) {
                fillSelect(field('province'), provinces, 'Select province');
            } else {
                field('province').required = false;
                resetSelect(field('province'), 'Not applicable');

                const cities = await getPSGC(
                    '/regions/' + encodeURIComponent(code) +
                    '/cities-municipalities'
                );

                if (request !== addressRequest) return;

                fillSelect(field('city'), cities, 'Select city / municipality');
            }
        } catch {
            if (request === addressRequest) {
                showError('Could not load provinces. Please select the region again.');
            }
        }
    });

    field('province').addEventListener('change', async function () {
        const request = ++addressRequest;

        resetSelect(field('city'), 'Loading cities...');
        resetSelect(field('barangay'), 'Select city first');

        clearPostalCode();

        const code = selectedCode(this);
        if (!code) return;

        try {
            const cities = await getPSGC(
                '/provinces/' + encodeURIComponent(code) +
                '/cities-municipalities'
            );

            if (request !== addressRequest) return;

            fillSelect(field('city'), cities, 'Select city / municipality');
        } catch {
            if (request === addressRequest) {
                showError('Could not load cities. Please select the province again.');
            }
        }
    });

    field('city').addEventListener('change', async function () {
        const request = ++addressRequest;

        resetSelect(field('barangay'), 'Loading barangays...');

        /*
         * NEW: Automatically calculate postal code
         * as soon as a city / municipality is selected.
         */
        updatePostalCode();

        const code = selectedCode(this);
        if (!code) return;

        try {
            const barangays = await getPSGC(
                '/cities-municipalities/' + encodeURIComponent(code) +
                '/barangays'
            );

            if (request !== addressRequest) return;

            fillSelect(field('barangay'), barangays, 'Select barangay');
        } catch {
            if (request === addressRequest) {
                showError('Could not load barangays. Please select the city again.');
            }
        }
    });

    /*
    |--------------------------------------------------------------------------
    | REVIEW APPLICATION
    |--------------------------------------------------------------------------
    */

    function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[character]));
    }

    function renderReview() {
        const data = [
            ['Account Type', field('role').value.toUpperCase()],
            ['Full Name', [
                field('firstName').value,
                field('middleInitial').value,
                field('lastName').value
            ].filter(Boolean).join(' ')],
            ['Sex', field('sex').value],
            ['Birthday', field('birthday').value],
            ['Age', field('age').value],
            ['Email', field('email').value],
            ['Contact Number', field('contact').value],
            ['Region', field('region').value],
            ['Province', field('province').value || 'Not applicable'],
            ['City / Municipality', field('city').value],
            ['Barangay', field('barangay').value],
            ['House / Unit', field('houseNumber').value],
            ['Street', field('street').value],
            ['Postal Code', field('postalCode').value],
            ['Valid ID', field('validId').files[0]?.name || 'Not selected']
        ];

        if (field('role').value === 'seller') {
            data.push(
                ['Business Name', field('businessName').value],
                ['Business Category', field('businessCategory').value],
                [
                    'Business Permit',
                    field('businessPermit').files[0]?.name || 'Not selected'
                ]
            );
        }

        field('reviewGrid').innerHTML = data.map(([label, value]) => `
            <div class="review-item">
                <small>${escapeHTML(label)}</small>
                <strong>${escapeHTML(value)}</strong>
            </div>
        `).join('');
    }

    submitButton.addEventListener('click', function () {
        clearError();

        if (!field('agree').checked) {
            showError('Please confirm that your information is accurate.');
            return;
        }

        previewResult.textContent =
            'Your ' + field('role').value +
            ' registration passed the frontend checks. ' +
            'This is a UI preview only. No account has been created, ' +
            'no documents have been uploaded, and no email or ' +
            'administrator approval request has been sent.';

        previewResult.classList.add('visible');
        previewResult.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    });

    form.addEventListener('submit', event => event.preventDefault());

    setRole('buyer');
    showStep(1);
    clearPostalCode();
    loadRegions();
})();
</script>
</body>
</html>
