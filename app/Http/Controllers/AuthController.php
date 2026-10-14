<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Category;
use App\Models\EmailVerificationCode;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerAgreementAcceptance;
use App\Models\SellerKycSubmission;
use App\Models\SellerOnboardingStep;
use App\Models\User;
use App\Notifications\SellerEmailVerificationCode;
use App\Services\AuditService;
use App\Services\SecureDocumentStorage;
use App\Support\PhilippineLocations;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private readonly AuditService $audit, private readonly SecureDocumentStorage $documents) {}

    public function registerForm() { return view('auth.register-new', ['categories' => Category::where('is_active', true)->orderBy('position')->orderBy('name')->get()]); }
    public function loginForm() { return view('auth.login'); }

    public function registerBuyer(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(8)], 'phone' => ['required', 'string', 'max:255'], 'delivery_recipient' => ['required', 'string', 'max:255'], 'delivery_phone' => ['required', 'string', 'max:255'], 'delivery_line1' => ['required', 'string', 'max:255'], 'delivery_barangay' => ['required', 'string', 'max:255'], 'delivery_city' => ['required', 'string', 'max:255'], 'delivery_province' => ['required', 'string', 'max:255'], 'delivery_postal_code' => ['required', 'string', 'max:255']]);
        $user = DB::transaction(function () use ($data) {
            $user = $this->createUser($data, 'buyer');
            Address::create(['user_id' => $user->id, 'label' => 'Delivery address', 'recipient' => $data['delivery_recipient'], 'phone' => $data['delivery_phone'], 'line1' => $data['delivery_line1'], 'barangay' => $data['delivery_barangay'], 'city' => $data['delivery_city'], 'province' => $data['delivery_province'], 'postal_code' => $data['delivery_postal_code'], 'is_default' => true]);
            return $user;
        });
        Auth::login($user);
        event(new Registered($user));
        $this->audit->record($request, 'buyer.registered', $user, null, ['email' => $user->email]);
        return redirect()->route('shop')->with('status', 'Welcome to Lumear. Your account is ready.');
    }

    public function registerSeller(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'phone' => $this->normalizePhilippineMobile((string) $request->input('phone')),
            'pickup_phone' => $this->normalizePhilippineMobile((string) $request->input('pickup_phone')),
            'tin' => trim((string) $request->input('tin')),
        ]);
        $nameRule = ['required', 'string', 'min:2', 'max:50', "regex:/^[\\pL][\\pL .'-]{1,49}$/u"];
        $data = $request->validate([
            'first_name' => $nameRule,
            'last_name' => $nameRule,
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'phone' => ['required', 'regex:/^09\\d{9}$/', Rule::unique('users', 'phone')],
            'shop_name' => ['required', 'string', 'min:3', 'max:30', 'unique:sellers,name'],
            'category_id' => ['required', 'exists:categories,id'],
            'seller_type' => ['required', Rule::in(['individual', 'sole_proprietorship', 'corporation'])],
            'tin' => ['required', 'regex:/^\\d{3}-\\d{3}-\\d{3}-\\d{3}$/', Rule::unique('seller_profiles', 'tax_number')],
            'shop_description' => ['nullable', 'string', 'max:3000'],
            'pickup_recipient' => ['required', 'string', 'max:255'],
            'pickup_phone' => ['required', 'regex:/^09\\d{9}$/'],
            'pickup_line1' => ['required', 'string', 'max:255'],
            'pickup_region' => ['required', 'string', 'max:100'],
            'pickup_barangay' => ['required', 'string', 'max:255'],
            'pickup_city' => ['required', 'string', 'max:255'],
            'pickup_province' => ['required', 'string', 'max:255'],
            'pickup_postal_code' => ['required', 'regex:/^\\d{4}$/'],
            'government_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'dti_certificate' => [Rule::requiredIf(fn () => $request->input('seller_type') === 'sole_proprietorship'), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'sec_certificate' => [Rule::requiredIf(fn () => $request->input('seller_type') === 'corporation'), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'bir_certificate' => [Rule::requiredIf(fn () => in_array($request->input('seller_type'), ['sole_proprietorship', 'corporation'], true)), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'seller_agreement' => ['accepted'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ]);
        $regions = PhilippineLocations::regions();
        if (! in_array($data['pickup_province'], $regions[$data['pickup_region']] ?? [], true)) {
            throw ValidationException::withMessages(['pickup_province' => 'Choose a province that belongs to the selected region.']);
        }
        if (! in_array($data['pickup_city'], PhilippineLocations::all()[$data['pickup_province']] ?? [], true)) {
            throw ValidationException::withMessages(['pickup_city' => 'Choose a city or municipality that belongs to the selected province.']);
        }
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);

        $seller = DB::transaction(function () use ($data, $request): Seller {
            $user = $this->createUser($data, 'seller');
            $address = Address::create(['user_id' => $user->id, 'label' => 'Shop pickup address', 'recipient' => $data['pickup_recipient'], 'phone' => $data['pickup_phone'], 'line1' => $data['pickup_line1'], 'barangay' => $data['pickup_barangay'], 'city' => $data['pickup_city'], 'province' => $data['pickup_province'], 'postal_code' => $data['pickup_postal_code'], 'is_default' => true]);
            $seller = Seller::create(['user_id' => $user->id, 'name' => $data['shop_name'], 'slug' => $this->uniqueSellerSlug($data['shop_name']), 'category_id' => $data['category_id'], 'description' => $data['shop_description'] ?? null, 'logo_path' => $request->file('logo')?->store('seller-assets', 'public'), 'banner_path' => $request->file('banner')?->store('seller-assets', 'public'), 'pickup_address_id' => $address->id, 'status' => 'pending']);
            $registrationDocument = $data['seller_type'] === 'sole_proprietorship' ? $request->file('dti_certificate') : $request->file('sec_certificate');
            $registrationPath = $registrationDocument ? $this->documents->store($registrationDocument, "seller-kyc/{$seller->id}") : null;
            $birPath = $request->file('bir_certificate') ? $this->documents->store($request->file('bir_certificate'), "seller-kyc/{$seller->id}") : null;
            $seller->profile()->create(['business_name' => $data['shop_name'], 'business_type' => $data['seller_type'], 'tax_number' => $data['tin'], 'business_registration_path' => $registrationPath, 'bir_certificate_path' => $birPath]);
            SellerKycSubmission::create(['seller_id' => $seller->id, 'business_type' => $data['seller_type'], 'tax_number' => $data['tin'], 'government_id_path' => $this->documents->store($request->file('government_id'), "seller-kyc/{$seller->id}"), 'business_permit_path' => $registrationPath, 'business_registration_path' => $registrationPath, 'bir_certificate_path' => $birPath, 'status' => 'pending', 'submitted_at' => now()]);
            SellerOnboardingStep::create(['seller_id' => $seller->id, 'step' => 'store_profile', 'completed_at' => now()]);
            SellerOnboardingStep::create(['seller_id' => $seller->id, 'step' => 'kyc_submitted', 'completed_at' => now()]);
            SellerOnboardingStep::create(['seller_id' => $seller->id, 'step' => 'payout_details']);
            SellerOnboardingStep::create(['seller_id' => $seller->id, 'step' => 'first_product']);
            SellerOnboardingStep::create(['seller_id' => $seller->id, 'step' => 'seller_agreement', 'completed_at' => now()]);
            SellerAgreementAcceptance::create(['seller_id' => $seller->id, 'version' => 'seller-terms-1.0', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'accepted_at' => now()]);
            $seller->setRelation('user', $user);

            return $seller;
        });

        Auth::login($seller->user);
        $this->issueSellerEmailCode($seller->user);
        $this->audit->record($request, 'seller.registered', $seller, null, ['email' => $seller->user->email]);
        return redirect()->route('seller.email-verification.notice')->with('status', 'A six-digit verification code was sent to your email address.');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required'], 'role' => ['nullable', 'in:buyer,seller']]);
        // Role is a post-login UI safety check, not a users-table credential.
        $credentials = ['email' => $data['email'], 'password' => $data['password']];
        $selectedRole = $data['role'] ?? null;
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            DB::table('login_attempts')->insert(['email' => $credentials['email'], 'method' => 'password', 'ip_address' => $request->ip(), 'success' => false, 'reason' => 'invalid_credentials', 'created_at' => now()]);
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $user = $request->user();
        if ($user->is_suspended) {
            DB::table('login_attempts')->insert(['user_id' => $user->id, 'email' => $user->email, 'method' => 'password', 'ip_address' => $request->ip(), 'success' => false, 'reason' => 'suspended', 'created_at' => now()]);
            Auth::logout();
            return back()->withErrors(['email' => 'This account is currently suspended.']);
        }
        // Admins may use either public login tab; they always continue to 2FA.
        if (! $user->isAdmin() && filled($selectedRole) && ! $user->{'is'.ucfirst($selectedRole)}()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is not registered as a '.ucfirst($selectedRole).'. Choose the correct tab and try again.'])->onlyInput('email');
        }
        DB::table('login_attempts')->insert(['user_id' => $user->id, 'email' => $user->email, 'method' => 'password', 'ip_address' => $request->ip(), 'success' => true, 'created_at' => now()]);
        $this->audit->record($request, 'auth.login', $user, null, ['method' => 'password']);
        if ($user->isAdmin()) {
            return redirect()->route($user->twoFactorCredential?->confirmed_at ? 'admin.2fa.challenge' : 'admin.2fa.setup');
        }

        if ($user->isSeller() && ! $user->hasVerifiedEmail()) {
            return redirect()->route('seller.email-verification.notice')->with('status', 'Verify your email before accessing seller tools.');
        }

        return $user->isSeller() ? redirect()->route('seller.status') : redirect()->route('shop');
    }

    public function logout(Request $request)
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('landing');
    }

    private function createUser(array $data, string $role): User
    {
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'phone' => $data['phone'] ?? null]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));
        return $user;
    }

    public function sellerEmailVerificationNotice(Request $request)
    {
        $this->sellerUser($request);

        return view('auth.seller-email-verification');
    }

    public function verifySellerEmail(Request $request)
    {
        $user = $this->sellerUser($request);
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        DB::transaction(function () use ($user, $data): void {
            $verification = EmailVerificationCode::where('user_id', $user->id)->latest()->lockForUpdate()->first();
            if (! $verification || $verification->used_at || $verification->expires_at->isPast() || ! Hash::check($data['code'], $verification->code_hash)) {
                throw ValidationException::withMessages(['code' => 'That verification code is invalid or expired. Request a new code and try again.']);
            }
            $verification->update(['used_at' => now()]);
            $user->forceFill(['email_verified_at' => now()])->save();
        });

        $this->audit->record($request, 'seller.email_verified', $user);

        return redirect()->route('seller.status')->with('status', 'Email verified. Your seller application is pending review.');
    }

    public function resendSellerEmailCode(Request $request)
    {
        $user = $this->sellerUser($request);
        $this->issueSellerEmailCode($user);

        return back()->with('status', 'A new verification code was sent. Previous codes no longer work.');
    }

    private function issueSellerEmailCode(User $user): void
    {
        EmailVerificationCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
        $code = (string) random_int(100000, 999999);
        EmailVerificationCode::create(['user_id' => $user->id, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10)]);
        $user->notify(new SellerEmailVerificationCode($code));
    }

    private function sellerUser(Request $request): User
    {
        abort_unless($request->user()?->isSeller(), 403);

        return $request->user();
    }

    private function normalizePhilippineMobile(string $phone): string
    {
        $phone = preg_replace('/[\\s-]+/', '', trim($phone)) ?? '';
        if (str_starts_with($phone, '+63')) {
            return '0'.substr($phone, 3);
        }
        if (str_starts_with($phone, '63') && strlen($phone) === 12) {
            return '0'.substr($phone, 2);
        }

        return $phone;
    }

    private function uniqueSellerSlug(string $shopName): string
    {
        $base = Str::slug($shopName) ?: 'shop';
        $slug = $base;
        $suffix = 2;
        while (Seller::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
