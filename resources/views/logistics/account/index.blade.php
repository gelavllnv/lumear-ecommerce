
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
            <p>Manage your sorting center profile, personal information, and account preferences.</p>
        </div>
        <span class="la-demo-label">Demo Account</span>
    </header>

    <section class="la-profile-banner">
        <div class="la-banner-decoration"></div>
        <div class="la-profile-content">
            <div class="la-avatar" id="laAvatar">SC</div>
            <div class="la-profile-identity">
                <span class="la-role">LOGISTICS PARTNER</span>
                <h2 id="laProfileName">Santa Cruz Sorting Center</h2>
                <p id="laProfileEmail">logistics@example.test</p>
                <span class="la-account-status">
                    <span class="la-status-dot"></span>
                    Active Account
                </span>
            </div>
        </div>
        <div class="la-profile-meta">
            <div><small>Account ID</small><strong>LOG-001</strong></div>
            <div><small>Service Area</small><strong id="laProfileArea">Santa Cruz, Laguna</strong></div>
            <div><small>Account Type</small><strong>Sorting Center</strong></div>
        </div>
    </section>

    <div class="la-layout">
        <aside class="la-settings-nav">
            <div class="la-nav-heading">ACCOUNT SETTINGS</div>
            <button type="button" class="la-nav-item active" data-la-tab="personal">
                <span class="la-nav-icon">♙</span>
                Personal Information
            </button>
            <button type="button" class="la-nav-item" data-la-tab="business">
                <span class="la-nav-icon">▣</span>
                Business Information
            </button>
            <button type="button" class="la-nav-item" data-la-tab="security">
                <span class="la-nav-icon">◇</span>
                Security & Password
            </button>
            <button type="button" class="la-nav-item" data-la-tab="activity">
                <span class="la-nav-icon">◷</span>
                Account Activity
            </button>
            <div class="la-nav-note">
                Account information is shown for demonstration purposes.
            </div>
        </aside>

        <main class="la-main">

            {{-- PERSONAL INFORMATION --}}
            <section class="la-section active" id="la-tab-personal">
                <div class="la-section-header">
                    <div>
                        <p class="la-eyebrow">PROFILE DETAILS</p>
                        <h2>Personal Information</h2>
                        <p>Update the personal details of the sorting center account holder.</p>
                    </div>
                </div>

                <form id="laPersonalForm">
                    <div class="la-form-grid">
                        <div class="la-field">
                            <label for="laFirstName">First Name</label>
                            <input id="laFirstName" required maxlength="80">
                        </div>
                        <div class="la-field">
                            <label for="laLastName">Last Name</label>
                            <input id="laLastName" required maxlength="80">
                        </div>
                        <div class="la-field">
                            <label for="laMiddleInitial">Middle Initial</label>
                            <input id="laMiddleInitial" maxlength="2">
                        </div>
                        <div class="la-field">
                            <label for="laSex">Sex</label>
                            <select id="laSex">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                        </div>
                        <div class="la-field">
                            <label for="laBirthday">Birthday</label>
                            <input type="date" id="laBirthday" required>
                        </div>
                        <div class="la-field">
                            <label for="laAge">Age</label>
                            <input type="number" id="laAge" readonly>
                            <small>Automatically calculated from birthday</small>
                        </div>
                        <div class="la-field">
                            <label for="laEmail">Email Address</label>
                            <input type="email" id="laEmail" required maxlength="150">
                        </div>
                        <div class="la-field">
                            <label for="laPhone">Contact Number</label>
                            <input type="tel" id="laPhone" required
                                   pattern="09[0-9]{9}" maxlength="11"
                                   placeholder="09XXXXXXXXX">
                        </div>
                    </div>

                    <div class="la-form-footer">
                        <span>Changes are saved to this browser only.</span>
                        <button type="submit" class="la-btn la-btn-primary">Save Personal Information</button>
                    </div>
                </form>
            </section>

            {{-- BUSINESS INFORMATION --}}
            <section class="la-section" id="la-tab-business">
                <div class="la-section-header">
                    <div>
                        <p class="la-eyebrow">SORTING CENTER PROFILE</p>
                        <h2>Business Information</h2>
                        <p>Review your sorting center details and registered service area.</p>
                    </div>
                </div>

                <form id="laBusinessForm">
                    <div class="la-form-grid">
                        <div class="la-field la-field-full">
                            <label for="laBusinessName">Business / Sorting Center Name</label>
                            <input id="laBusinessName" required maxlength="150">
                        </div>
                        <div class="la-field">
                            <label for="laProvince">Province</label>
                            <input id="laProvince" readonly>
                            <small>Registered service area</small>
                        </div>
                        <div class="la-field">
                            <label for="laMunicipality">Municipality / City</label>
                            <input id="laMunicipality" readonly>
                            <small>Changes require administrator approval</small>
                        </div>
                        <div class="la-field">
                            <label for="laBarangay">Barangay</label>
                            <input id="laBarangay" required maxlength="100">
                        </div>
                        <div class="la-field">
                            <label for="laPostalCode">Postal Code</label>
                            <input id="laPostalCode" maxlength="4" pattern="[0-9]{4}" required>
                        </div>
                        <div class="la-field la-field-full">
                            <label for="laStreet">Street / House / Building Number</label>
                            <input id="laStreet" required maxlength="200">
                        </div>
                    </div>

                    <div class="la-documents">
                        <h3>Registration Documents</h3>
                        <p>Documents submitted during registration are displayed here as placeholders.</p>
                        <div class="la-document-grid">
                            <div class="la-document">
                                <div class="la-document-icon">▤</div>
                                <div><strong>Valid Identification</strong><span>Demo placeholder — no document loaded</span></div>
                                <span class="la-document-tag">Not verified</span>
                            </div>
                            <div class="la-document">
                                <div class="la-document-icon">▤</div>
                                <div><strong>Business / DTI Permit</strong><span>Demo placeholder — no document loaded</span></div>
                                <span class="la-document-tag">Not verified</span>
                            </div>
                        </div>
                    </div>

                    <div class="la-form-footer">
                        <span>Registered municipality cannot be changed here.</span>
                        <button type="submit" class="la-btn la-btn-primary">Save Business Information</button>
                    </div>
                </form>
            </section>

            {{-- SECURITY --}}
            <section class="la-section" id="la-tab-security">
                <div class="la-section-header">
                    <div>
                        <p class="la-eyebrow">ACCOUNT PROTECTION</p>
                        <h2>Security & Password</h2>
                        <p>Review security options for your Logistics account.</p>
                    </div>
                </div>

                <div class="la-security-notice">
                    <strong>Demo Security Settings</strong>
                    <p>This form validates password requirements for demonstration. It does not change your real Laravel account password.</p>
                </div>

                <form id="laPasswordForm">
                    <div class="la-form-grid">
                        <div class="la-field la-field-full">
                            <label for="laCurrentPassword">Current Password</label>
                            <input type="password" id="laCurrentPassword" required autocomplete="current-password">
                        </div>
                        <div class="la-field">
                            <label for="laNewPassword">New Password</label>
                            <input type="password" id="laNewPassword" required autocomplete="new-password">
                        </div>
                        <div class="la-field">
                            <label for="laConfirmPassword">Confirm New Password</label>
                            <input type="password" id="laConfirmPassword" required autocomplete="new-password">
                        </div>
                    </div>

                    <div class="la-password-requirements">
                        <strong>Password Requirements</strong>
                        <span id="laReqLength">○ At least 8 characters</span>
                        <span id="laReqUpper">○ At least one uppercase letter</span>
                        <span id="laReqLower">○ At least one lowercase letter</span>
                        <span id="laReqNumber">○ At least one number</span>
                        <span id="laReqMatch">○ Passwords match</span>
                    </div>

                    <div class="la-form-footer">
                        <span>No passwords will be saved in browser storage.</span>
                        <button type="submit" class="la-btn la-btn-primary">Validate Password</button>
                    </div>
                </form>

                <div class="la-security-extra">
                    <h3>Email Verification</h3>
                    <p>Account email verification and Gmail OTP must be connected to your Laravel backend before becoming functional.</p>
                    <span class="la-tag">Backend integration required</span>
                </div>
            </section>

            {{-- ACTIVITY --}}
            <section class="la-section" id="la-tab-activity">
                <div class="la-section-header">
                    <div>
                        <p class="la-eyebrow">ACCOUNT HISTORY</p>
                        <h2>Account Activity</h2>
                        <p>View profile changes made during this browser demonstration.</p>
                    </div>
                </div>

                <div class="la-activity-list" id="laActivityList"></div>

                <div class="la-activity-footer">
                    <button type="button" class="la-btn la-btn-outline" id="laClearActivity">
                        Clear Demo Activity
                    </button>
                </div>
            </section>

        </main>
    </div>

    <div class="la-toast" id="laToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics/account.js') }}" defer></script>
@endpush
