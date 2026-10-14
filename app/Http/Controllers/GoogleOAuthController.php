<?php

namespace App\Http\Controllers;

use App\Models\AuthIdentity;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOnboardingStep;
use App\Models\User;
use App\Services\AuditService;
use Google\Client as GoogleClient;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleOAuthController extends Controller
{
    private const STATE_TTL_SECONDS = 600;

    public function __construct(private readonly AuditService $audit) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google Sign-In is not configured yet. Add GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI to the application .env file, then clear the configuration cache.']);
        }
        $role = $request->string('role')->value();
        abort_unless(in_array($role, ['buyer', 'seller'], true), 422, 'Select buyer or seller sign-in.');
        $redirect = $this->safeRedirect($request->string('redirect')->value(), $role);
        $state = Str::random(80);
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        Cache::put($this->stateKey($state), compact('role', 'redirect', 'verifier'), now()->addSeconds(self::STATE_TTL_SECONDS));

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors(['email' => 'Google Sign-In was cancelled or denied.']);
        }

        $state = $request->string('state')->value();
        $context = $state ? Cache::pull($this->stateKey($state)) : null;
        if (! is_array($context) || ! $request->filled('code')) {
            return redirect()->route('login')->withErrors(['email' => 'The Google sign-in request expired or was already used. Please try again.']);
        }

        try {
            $tokens = Http::asForm()->timeout(10)->withOptions(['verify' => $this->caBundlePath()])->post('https://oauth2.googleapis.com/token', [
                'code' => $request->string('code')->value(),
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
                'code_verifier' => $context['verifier'],
            ])->throw()->json();
            $claims = $this->verifiedClaims((string) ($tokens['id_token'] ?? ''));
            $user = $this->resolveAccount($claims, $context['role']);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors(['email' => 'Google Sign-In could not be completed safely. Please try again.']);
        }

        if ($user->is_suspended || $user->isAdmin()) {
            return redirect()->route('login')->withErrors(['email' => 'This account cannot use public Google Sign-In.']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        DB::table('login_attempts')->insert(['user_id' => $user->id, 'email' => $user->email, 'method' => 'google', 'ip_address' => $request->ip(), 'success' => true, 'created_at' => now()]);
        $this->audit->record($request, 'auth.google_login', $user, null, ['role' => $context['role']]);

        return redirect()->to($this->destinationFor($user, $context['redirect']));
    }

    /** @param array<string, mixed> $claims */
    private function resolveAccount(array $claims, string $requestedRole): User
    {
        $identity = AuthIdentity::with('user')->where('provider', 'google')->where('provider_user_id', $claims['sub'])->first();
        if ($identity) {
            $identity->update(['provider_email' => $claims['email'], 'last_used_at' => now()]);

            return $identity->user;
        }

        return DB::transaction(function () use ($claims, $requestedRole): User {
            $user = User::whereRaw('LOWER(email) = ?', [Str::lower($claims['email'])])->lockForUpdate()->first();
            if ($user && ! $user->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['email' => 'Sign in with your original method and verify the existing account before linking Google.']);
            }

            if (! $user) {
                $user = User::create([
                    'name' => $claims['name'] ?: Str::before($claims['email'], '@'),
                    'email' => $claims['email'],
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::password(48)),
                ]);
                $user->roles()->attach(Role::firstOrCreate(['name' => $requestedRole]));

                if ($requestedRole === 'seller') {
                    $this->createSellerOnboarding($user);
                }
                event(new Registered($user));
            }

            AuthIdentity::create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_user_id' => $claims['sub'],
                'provider_email' => $claims['email'],
                'linked_at' => now(),
                'last_used_at' => now(),
            ]);

            return $user;
        });
    }

    /** @return array{sub: string, email: string, name: string} */
    private function verifiedClaims(string $idToken): array
    {
        abort_if($idToken === '', 422, 'Google did not return an identity token.');
        $client = new GoogleClient(['client_id' => config('services.google.client_id')]);
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => $this->caBundlePath()]));
        $payload = $client->verifyIdToken($idToken);
        abort_unless(is_array($payload), 422, 'Google identity token verification failed.');
        abort_unless(in_array($payload['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true), 422, 'Invalid Google token issuer.');
        abort_unless(($payload['aud'] ?? null) === config('services.google.client_id'), 422, 'Invalid Google token audience.');
        abort_unless(filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN), 422, 'Your Google email address must be verified.');
        abort_unless(isset($payload['sub'], $payload['email']), 422, 'Google account information is incomplete.');

        return ['sub' => (string) $payload['sub'], 'email' => Str::lower((string) $payload['email']), 'name' => (string) ($payload['name'] ?? '')];
    }

    private function createSellerOnboarding(User $user): void
    {
        $name = trim($user->name) !== '' ? $user->name.' Store' : 'New Seller Store';
        $seller = Seller::create(['user_id' => $user->id, 'name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)), 'status' => 'pending']);
        $seller->profile()->create(['business_name' => $name]);
        foreach (['store_profile', 'kyc_submitted', 'payout_details', 'seller_agreement', 'first_product'] as $step) {
            SellerOnboardingStep::create(['seller_id' => $seller->id, 'step' => $step]);
        }
    }

    private function destinationFor(User $user, string $requestedRedirect): string
    {
        if ($user->isSeller()) {
            return $user->seller?->isApproved() ? '/seller/dashboard' : '/seller/onboarding';
        }

        return $requestedRedirect;
    }

    private function safeRedirect(string $redirect, string $role): string
    {
        $allowed = $role === 'seller' ? ['/seller/onboarding', '/seller/status'] : ['/home', '/cart'];

        return in_array($redirect, $allowed, true) ? $redirect : ($role === 'seller' ? '/seller/onboarding' : '/home');
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'));
    }

    private function caBundlePath(): string
    {
        $path = config('services.google.ca_bundle');

        if (! is_string($path) || ! is_readable($path)) {
            throw new \RuntimeException('Google OAuth requires a readable CA certificate bundle for secure HTTPS connections.');
        }

        return $path;
    }

    private function stateKey(string $state): string
    {
        return 'google-oauth-state:'.$state;
    }
}
