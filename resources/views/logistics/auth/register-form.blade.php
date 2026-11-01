@extends('layouts.auth')

@section('title', 'Join Lumear Logistics')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics/register-wizard.css') }}">
@endpush

@section('content')
    <div class="logistics-wizard-shell">
        <aside class="logistics-wizard-aside">
            <a class="wizard-brand" href="{{ route('shop') }}" aria-label="Lumear marketplace"><span class="wizard-brand-mark">L</span><span>Lumear</span></a>
            <div class="wizard-aside-copy">
                <p class="wizard-eyebrow">LUMEAR LOGISTICS</p>
                <h1>Deliver local finds with care.</h1>
                <p>Apply to operate a Lumear logistics hub. Applications are reviewed by an administrator before access is granted.</p>
            </div>
            <ul class="wizard-benefits"><li><span>01</span> Manage pickup requests</li><li><span>02</span> Track parcel operations</li><li><span>03</span> Coordinate delivery teams</li></ul>
            <p class="wizard-aside-foot">© {{ now()->year }} Lumear · Academic development project</p>
        </aside>

        <section class="logistics-wizard-main">
            <div class="wizard-topline"><span>Already have an account?</span><a href="{{ route('login', ['role' => 'logistics']) }}">Sign in</a></div>
            <p class="wizard-eyebrow">BECOME A LOGISTICS PARTNER</p>
            <h2>Create your account.</h2>
            <p class="wizard-intro">Complete the steps below to submit your logistics application.</p>

            <div class="wizard-progress" aria-label="Registration progress">
                <div class="wizard-progress-copy"><strong data-wizard-step-label>Step 1 of 3 · Operator details</strong><span data-wizard-progress-text>33% complete</span></div>
                <div class="wizard-progress-track"><span data-wizard-progress-bar></span></div>
                <ol><li class="is-current" data-wizard-indicator="0"><span>1</span> Details</li><li data-wizard-indicator="1"><span>2</span> Security</li><li data-wizard-indicator="2"><span>3</span> Review</li></ol>
            </div>

            <form method="post" action="{{ route('logistics.register.store') }}" class="logistics-wizard-form" data-logistics-wizard data-initial-step="{{ ($errors->has('password') || $errors->has('password_confirmation') || $errors->has('agreement')) ? 1 : 0 }}" novalidate>
                @csrf
                <section data-wizard-panel="0">
                    <div class="wizard-panel-heading"><p>STEP 01 / 03</p><h3>Who will operate the hub?</h3><span>Use the representative’s legal name and the public name of your logistics provider or hub.</span></div>
                    <div class="wizard-fields">
                        <label>Operator name<input class="field" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="100" placeholder="Full name"></label>
                        <label data-age-gate>Date of birth<input class="field" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" max="{{ now()->toDateString() }}" required data-date-of-birth><span class="mt-1 block text-xs text-maroon-900/55" data-age-status aria-live="polite">You must be 18 or older to create a Lumear account.</span></label>
                        <label>Provider or hub name<input class="field" name="provider_name" value="{{ old('provider_name') }}" required minlength="3" maxlength="100" placeholder="e.g. Santa Cruz Hub"></label>
                        <label>Email address<input class="field" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="name@business.com"></label>
                        <label>Philippine mobile number<input class="field" name="phone" value="{{ old('phone') }}" inputmode="tel" autocomplete="tel" required pattern="(09|\+639)[0-9]{9}" placeholder="09XXXXXXXXX"></label>
                    </div>
                    <p class="wizard-help">We will use this email for verification and application updates.</p>
                </section>

                <section data-wizard-panel="1" hidden>
                    <div class="wizard-panel-heading"><p>STEP 02 / 03</p><h3>Secure the account.</h3><span>Create a password for the operator account. You can sign in after your application is approved.</span></div>
                    <div class="wizard-fields wizard-fields-single">
                        <label>Password<input class="field" name="password" type="password" autocomplete="new-password" required minlength="8" placeholder="At least 8 characters"></label>
                        <label>Confirm password<input class="field" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="Enter password again"></label>
                    </div>
                    <p class="wizard-help">Use upper- and lower-case letters, a number, and a symbol.</p>
                    <label class="wizard-check"><input name="agreement" type="checkbox" value="1" @checked(old('agreement')) required><span>I agree to Lumear’s logistics operations and safety terms, and confirm that the information I provided is accurate.</span></label>
                </section>

                <section data-wizard-panel="2" hidden>
                    <div class="wizard-panel-heading"><p>STEP 03 / 03</p><h3>Review your application.</h3><span>Check the details below before submitting. An administrator will review your application after email verification.</span></div>
                    <dl class="wizard-review"><div><dt>Operator</dt><dd data-review="name">—</dd></div><div><dt>Date of birth</dt><dd data-review="date_of_birth">—</dd></div><div><dt>Hub / provider</dt><dd data-review="provider_name">—</dd></div><div><dt>Email</dt><dd data-review="email">—</dd></div><div><dt>Mobile number</dt><dd data-review="phone">—</dd></div></dl>
                    <div class="wizard-notice"><strong>Administrator approval required</strong><span>Approval is required before you can access the Logistics Centre.</span></div>
                </section>

                <div class="wizard-actions"><button class="wizard-back" type="button" data-wizard-back hidden>Back</button><button class="wizard-next" type="button" data-wizard-next>Continue <span aria-hidden="true">→</span></button><button class="wizard-submit" type="submit" data-wizard-submit hidden>Submit application <span aria-hidden="true">→</span></button></div>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/logistics/register-wizard.js') }}" defer></script>
@endpush
