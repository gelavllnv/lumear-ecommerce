@extends('layouts.logistics')

@section('title', 'My Account | LUMEAR Logistics')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics/account.css') }}">
@endpush

@section('content')
<div class="la-page" id="logisticsAccountApp">

    <header class="la-page-header">
        <div>
            <p class="la-eyebrow">LOGISTICS / ACCOUNT SETTINGS</p>
            <h1>My Account</h1>
            <p>Manage your sorting center profile, personal information, and account security.</p>
        </div>
        <span class="la-tag" id="laHeaderStatus">Loading account...</span>
    </header>

    <section class="la-profile-banner">
        <div class="la-banner-decoration"></div>

        <div class="la-profile-content">
            <div class="la-avatar" id="laAvatar">LM</div>

            <div class="la-profile-identity">
                <span class="la-role">LOGISTICS PARTNER</span>
                <h2 id="laProfileName">Loading...</h2>
                <p id="laProfileEmail">Loading account information...</p>

                <span class="la-account-status">
                    <span class="la-status-dot"></span>
                    <span id="laAccountStatus">Checking status...</span>
                </span>
            </div>
        </div>

        <div class="la-profile-meta">
            <div>
                <small>Account ID</small>
                <strong id="laAccountId">—</strong>
            </div>
            <div>
                <small>Service Area</small>
                <strong id="laProfileArea">—</strong>
            </div>
            <div>
                <small>Account Type</small>
                <strong>Sorting Center</strong>
            </div>
        </div>
    </section>

    <div class="la-layout">

        <aside class="la-settings-nav">
            <div class="la-nav-heading">ACCOUNT SETTINGS</div>

            <button type="button"
                    class="la-nav-item active"
                    data-la-tab="personal">
                <span class="la-nav-icon">♙</span>
                Personal Information
            </button>

            <button type="button"
                    class="la-nav-item"
                    data-la-tab="business">
                <span class="la-nav-icon">▣</span>
                Business Information
            </button>

            <button type="button"
                    class="la-nav-item"
                    data-la-tab="security">
                <span class="la-nav-icon">◇</span>
                Security & Password
            </button>

            <button type="button"
                    class="la-nav-item"
                    data-la-tab="activity">
                <span class="la-nav-icon">◷</span>
                Account Activity
            </button>

            <div class="la-nav-note">
                Your account information is securely synchronized
                with LUMEAR's database.
            </div>
        </aside>

        <main class="la-main">

            {{-- PERSONAL INFORMATION --}}
            <section class="la-section active" id="la-tab-personal">
                <div class="la-section-header">
                    <div>
                        <p class="la-eyebrow">PROFILE DETAILS</p>
                        <h2>Personal Information</h2>
                        <p>Manage the registered account holder's information.</p>
                    </div>
                </div>

                <form id="laPersonalForm">
                    <div class="la-form-grid">

                        <div class="la-field">
                            <label for="laFirstName">First Name</label>
                            <input id="laFirstName"
                                   required minlength="2" maxlength="50">
                        </div>

                        <div class="la-field">
                            <label for="laLastName">Last Name</label>
                            <input id="laLastName"
                                   required minlength="2" maxlength="50">
                        </div>

                        <div class="la-field">
                            <label for="laMiddleInitial">Middle Initial</label>
                            <input id="laMiddleInitial" maxlength="1">
                        </div>

                        <div class="la-field">
                            <label for="laSex">Sex</label>
                            <select id="laSex" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <div class="la-field">
                            <label for="laBirthday">Birthday</label>
                            <input type="date"
                                   id="laBirthday"
                                   required>
                        </div>

                        <div class="la-field">
                            <label for="laAge">Age</label>
                            <input type="number"
                                   id="laAge"
                                   readonly>
                            <small>Automatically calculated from birthday</small>
                        </div>

                        <div class="la-field">
                            <label for="laEmail">Email Address</label>
                            <input type="email"
                                   id="laEmail"
                                   readonly>
                            <small>
                                Email changes require a separate verification process.
                            </small>
                        </div>

                        <div class="la-field">
                            <label for="laPhone">Contact Number</label>
                            <input type="tel"
                                   id="laPhone"
                                   required
                                   maxlength="13"
                                   placeholder="09XXXXXXXXX or +639XXXXXXXXX">
                        </div>

                    </div>

                    <div class="la-form-footer">
                        <span>Changes are saved securely to your LUMEAR account.</span>
                        <button type="submit"
                                class="la-btn la-btn-primary">
                            Save Personal Information
                        </button>
                    </div>
                </form>
            </section>

            {{-- BUSINESS INFORMATION --}}
            <section class="la-section" id="la-tab-business">
                <div class="la-section-header">
                    <div>
                        <p class="la-eyebrow">SORTING CENTER PROFILE</p>
                        <h2>Business Information</h2>
                        <p>Review and update your registered business details.</p>
                    </div>
                </div>

                <form id="laBusinessForm">
                    <div class="la-form-grid">

                        <div class="la-field la-field-full">
                            <label for="laBusinessName">
                                Business / Sorting Center Name
                            </label>
                            <input id="laBusinessName"
                                   required maxlength="150">
                        </div>

                        <div class="la-field">
                            <label for="laProvince">Province</label>
                            <input id="laProvince" readonly>
                            <small>Registered service area</small>
                        </div>

                        <div class="la-field">
                            <label for="laMunicipality">
                                Municipality / City
                            </label>
                            <input id="laMunicipality" readonly>
                            <small>
                                Changes require administrator approval.
                            </small>
                        </div>

                        <div class="la-field">
                            <label for="laBarangay">Barangay</label>
                            <input id="laBarangay"
                                   required maxlength="150">
                        </div>

                        <div class="la-field">
                            <label for="laPostalCode">Postal Code</label>
                            <input id="laPostalCode"
                                   required
                                   maxlength="4"
                                   pattern="[0-9]{4}">
                        </div>

                        <div class="la-field">
                            <label for="laHouseNumber">
                                House / Building Number
                            </label>
                            <input id="laHouseNumber"
                                   required maxlength="100">
                        </div>

                        <div class="la-field">
                            <label for="laStreet">Street</label>
                            <input id="laStreet"
                                   required maxlength="150">
                        </div>

                        <div class="la-field">
                            <label for="laSEC">SEC Registration Number</label>
                            <input id="laSEC" readonly>
                        </div>

                        <div class="la-field">
                            <label for="laTIN">TIN</label>
                            <input id="laTIN" readonly>
                        </div>
                    </div>

                    <div class="la-documents">
                        <h3>Registration Documents</h3>
                        <p>
                            View the original documents submitted during
                            your Logistics registration.
                        </p>

                        <div class="la-document-grid">

                            <div class="la-document">
                                <div class="la-document-icon">▤</div>
                                <div>
                                    <strong>Valid Identification</strong>
                                    <span id="laValidIdStatus">
                                        Checking document...
                                    </span>
                                </div>
                                <a id="laValidIdLink"
                                   class="la-btn la-btn-outline"
                                   href="#"
                                   target="_blank"
                                   rel="noopener"
                                   hidden>
                                    View Document
                                </a>
                            </div>

                            <div class="la-document">
                                <div class="la-document-icon">▤</div>
                                <div>
                                    <strong>Business / DTI Permit</strong>
                                    <span id="laPermitStatus">
                                        Checking document...
                                    </span>
                                </div>
                                <a id="laPermitLink"
                                   class="la-btn la-btn-outline"
                                   href="#"
                                   target="_blank"
                                   rel="noopener"
                                   hidden>
                                    View Document
                                </a>
                            </div>

                        </div>
                    </div>

                    <div class="la-form-footer">
                        <span>
                            Registered municipality, SEC number,
                            and TIN cannot be changed here.
                        </span>
                        <button type="submit"
                                class="la-btn la-btn-primary">
                            Save Business Information
                        </button>
                    </div>
                </form>
            </section>

            {{-- SECURITY --}}
            <section class="la-section" id="la-tab-security">
                <div class="la-section-header">
                    <div>
                        <p class="la-eyebrow">ACCOUNT PROTECTION</p>
                        <h2>Security & Password</h2>
                        <p>Protect your LUMEAR Logistics account.</p>
                    </div>
                </div>

                <div class="la-security-notice">
                    <strong>Secure Password Management</strong>
                    <p>
                        Changing your password requires your current
                        password. Your new password is securely hashed
                        before being saved.
                    </p>
                </div>

                <form id="laPasswordForm">
                    <div class="la-form-grid">

                        <div class="la-field la-field-full">
                            <label for="laCurrentPassword">
                                Current Password
                            </label>
                            <input type="password"
                                   id="laCurrentPassword"
                                   required
                                   autocomplete="current-password">
                        </div>

                        <div class="la-field">
                            <label for="laNewPassword">
                                New Password
                            </label>
                            <input type="password"
                                   id="laNewPassword"
                                   required
                                   autocomplete="new-password">
                        </div>

                        <div class="la-field">
                            <label for="laConfirmPassword">
                                Confirm New Password
                            </label>
                            <input type="password"
                                   id="laConfirmPassword"
                                   required
                                   autocomplete="new-password">
                        </div>

                    </div>

                    <div class="la-password-requirements">
                        <strong>Password Requirements</strong>
                        <span id="laReqLength">○ At least 8 characters</span>
                        <span id="laReqUpper">○ At least one uppercase letter</span>
                        <span id="laReqLower">○ At least one lowercase letter</span>
                        <span id="laReqNumber">○ At least one number</span>
                        <span id="laReqSpecial">○ At least one special character</span>
                        <span id="laReqMatch">○ Passwords match</span>
                    </div>

                    <div class="la-form-footer">
                        <span>
                            Your password is never stored in browser storage.
                        </span>
                        <button type="submit"
                                class="la-btn la-btn-primary">
                            Change Password
                        </button>
                    </div>
                </form>

                <div class="la-security-extra">
                    <h3>Email Verification</h3>
                    <p id="laEmailVerification">
                        Checking verification status...
                    </p>
                    <span class="la-tag" id="laEmailVerificationTag">
                        Checking...
                    </span>
                </div>
            </section>

            {{-- ACCOUNT ACTIVITY --}}
            <section class="la-section" id="la-tab-activity">
                <div class="la-section-header">
                    <div>
                        <p class="la-eyebrow">ACCOUNT HISTORY</p>
                        <h2>Account Activity</h2>
                        <p>
                            Review recent changes made to your
                            Logistics account.
                        </p>
                    </div>
                </div>

                <div class="la-activity-list"
                     id="laActivityList">
                    <div class="la-empty">
                        Loading account activity...
                    </div>
                </div>

                <div class="la-activity-footer">
                    <span class="la-nav-note">
                        Account activity is retained for security
                        and cannot be cleared from this page.
                    </span>
                </div>
            </section>

        </main>
    </div>

    <div class="la-toast"
         id="laToast"
         role="status"
         aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/account.js') }}" defer></script>
@endpush
