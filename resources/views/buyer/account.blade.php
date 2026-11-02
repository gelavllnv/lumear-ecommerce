
@extends('layouts.buyer')

@section('title', 'My Account — Lumear')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/buyer/account-center.css') }}?v=1">
@endpush

@section('content')
<div class="lac-page" id="lumearAccount">
    <div class="lac-container">
        <nav class="lac-breadcrumb"><a href="{{ url('/home') }}">Home</a><span>/</span><span>My Account</span></nav>

        <div class="lac-heading">
            <div>
                <span class="lac-eyebrow">YOUR PERSONAL SPACE</span>
                <h1>My <em>Account.</em></h1>
                <p>Manage your details and make every shopping experience your own.</p>
            </div>
            <span class="lac-heading-mark">LUMEAR / ACCOUNT</span>
        </div>

        <div class="lac-layout">
            <aside class="lac-sidebar">
                <div class="lac-person">
                    <div class="lac-avatar" id="lacAvatar">JD</div>
                    <div>
                        <strong id="lacSidebarName">Juana Dela Cruz</strong>
                        <span>Buyer Account · Demo</span>
                    </div>
                </div>

                <div class="lac-sidebar-label">ACCOUNT SETTINGS</div>
                <nav class="lac-menu" aria-label="Account sections">
                    <button type="button" class="active" data-account-tab="profile"><span>◉</span> Personal Information <b>›</b></button>
                    <button type="button" data-account-tab="addresses"><span>⌖</span> My Addresses <b>›</b></button>
                    <button type="button" data-account-tab="notifications"><span>♧</span> Notifications <b>›</b></button>
                    <button type="button" data-account-tab="security"><span>◇</span> Privacy & Security <b>›</b></button>
                </nav>

                <div class="lac-sidebar-label">MY SHOPPING</div>
                <nav class="lac-menu">
                    <a href="{{ url('/orders') }}"><span>▣</span> My Orders <b>↗</b></a>
                    <a href="{{ url('/saved') }}"><span>♡</span> Saved Products <b>↗</b></a>
                    <a href="{{ url('/cart') }}"><span>▤</span> Shopping Cart <b>↗</b></a>
                </nav>

                <div class="lac-sidebar-bottom">A little more you, in every discovery. ✦</div>
            </aside>

            <div class="lac-content">
                {{-- PROFILE --}}
                <section class="lac-panel active" data-account-panel="profile">
                    <div class="lac-panel-heading">
                        <div><span>01 / YOUR DETAILS</span><h2>Personal Information</h2><p>Keep your contact details up to date.</p></div>
                        <div class="lac-panel-symbol">✳</div>
                    </div>

                    <form id="lacProfileForm">
                        <div class="lac-fields">
                            <label>First Name <input name="firstName" required maxlength="60" placeholder="First name"></label>
                            <label>Last Name <input name="lastName" required maxlength="60" placeholder="Last name"></label>
                            <label>Middle Initial <input name="middleInitial" maxlength="4" placeholder="Optional"></label>
                            <label>Gender (optional)
                                <select name="gender">
                                    <option value="">Prefer not to say</option>
                                    <option value="Female">Female</option>
                                    <option value="Male">Male</option>
                                    <option value="Other">Other</option>
                                </select>
                            </label>
                            <label>Email Address <input name="email" type="email" required placeholder="you@example.com"></label>
                            <label>Contact Number <input name="phone" type="tel" maxlength="20" placeholder="09XXXXXXXXX"></label>
                        </div>
                        <div class="lac-form-actions">
                            <span>Demo profile · Saved in this browser only</span>
                            <button type="submit" class="lac-primary">Save Changes ↗</button>
                        </div>
                    </form>
                </section>

                {{-- ADDRESSES --}}
                <section class="lac-panel" data-account-panel="addresses">
                    <div class="lac-panel-heading">
                        <div><span>02 / DELIVERY DETAILS</span><h2>My Addresses</h2><p>Save addresses for a smoother checkout.</p></div>
                        <div class="lac-panel-symbol">⌖</div>
                    </div>

                    <div id="lacAddressList" class="lac-address-list"></div>

                    <div class="lac-subheading">
                        <h3 id="lacAddressFormTitle">Add a New Address</h3>
                        <p>Enter a delivery address below.</p>
                    </div>

                    <form id="lacAddressForm">
                        <input type="hidden" name="addressId">
                        <div class="lac-fields">
                            <label>Recipient Name <input name="recipient" required maxlength="100" placeholder="Full name"></label>
                            <label>Mobile Number <input name="mobile" required maxlength="20" placeholder="09XXXXXXXXX"></label>
                            <label>Province <input name="province" required maxlength="100" placeholder="e.g. Laguna"></label>
                            <label>City / Municipality <input name="city" required maxlength="100" placeholder="e.g. Santa Cruz"></label>
                            <label>Barangay <input name="barangay" required maxlength="100" placeholder="Barangay"></label>
                            <label>Postal Code <input name="postal" maxlength="12" placeholder="e.g. 4009"></label>
                            <label class="lac-field-full">House No., Street, Building <input name="street" required maxlength="200" placeholder="Complete street address"></label>
                        </div>
                        <label class="lac-inline-check"><input type="checkbox" name="isDefault"> Set as my default delivery address</label>
                        <div class="lac-form-actions">
                            <button type="button" class="lac-secondary" id="lacCancelAddress" hidden>Cancel Edit</button>
                            <button type="submit" class="lac-primary">Save Address ↗</button>
                        </div>
                    </form>
                </section>

                {{-- NOTIFICATIONS --}}
                <section class="lac-panel" data-account-panel="notifications">
                    <div class="lac-panel-heading">
                        <div><span>03 / STAY CONNECTED</span><h2>Notifications</h2><p>Choose which updates you would like to receive.</p></div>
                        <div class="lac-panel-symbol">✦</div>
                    </div>
                    <form id="lacPreferencesForm">
                        <label class="lac-setting"><div><strong>Order Updates</strong><p>Order confirmations and delivery progress.</p></div><input type="checkbox" name="orders" checked></label>
                        <label class="lac-setting"><div><strong>Promotions & Offers</strong><p>Discounts, collections and special campaigns.</p></div><input type="checkbox" name="promos" checked></label>
                        <label class="lac-setting"><div><strong>Saved Product Updates</strong><p>Updates about products you've bookmarked.</p></div><input type="checkbox" name="saved" checked></label>
                        <div class="lac-form-actions"><span>Demo preferences only</span><button type="submit" class="lac-primary">Save Preferences ↗</button></div>
                    </form>
                </section>

                {{-- SECURITY --}}
                <section class="lac-panel" data-account-panel="security">
                    <div class="lac-panel-heading">
                        <div><span>04 / ACCOUNT PROTECTION</span><h2>Privacy & Security</h2><p>Understand how your account will be protected.</p></div>
                        <div class="lac-panel-symbol">◇</div>
                    </div>
                    <div class="lac-security-note">
                        <strong>Account security is being prepared.</strong>
                        <p>Password changes, active sessions, and account verification require Laravel authentication and database integration. This demo does not collect or store passwords.</p>
                    </div>
                    <div class="lac-security-feature"><span>✓</span><div><strong>Profile Management</strong><p>Your demonstration profile can be edited above.</p></div></div>
                    <div class="lac-security-feature"><span>○</span><div><strong>Password Management</strong><p>Available when real buyer authentication is connected.</p></div></div>
                    <div class="lac-security-feature"><span>○</span><div><strong>Session Management</strong><p>Available when real login and logout are implemented.</p></div></div>
                </section>
            </div>
        </div>
    </div>
    <div class="lac-toast" id="lacToast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/buyer/account-center.js') }}?v=1" defer></script>
@endpush
