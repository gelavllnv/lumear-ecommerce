<?php

namespace App\Http\Controllers;

use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\AuditService;
use Google\Client as GoogleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleOAuthController extends Controller
{
    private const STATE_TTL_SECONDS = 600;

    public function __construct(private readonly AuditService $audit) {}

    public function redirect(Request $request): RedirectResponse
    {
        $role = $request->string('role')->value();
        abort_unless(in_array($role, ['buyer', 'seller', 'logistics'], true), 422, 'Select buyer, seller, or logistics sign-in.');

        if (! $this->isConfigured()) {
            return redirect()
                ->route($this->loginRoute($role))
                ->withErrors(['email' => 'Google Sign-In is not configured yet. Add GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI to the application .env file, then clear the configuration cache.']);
        }

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
        $state = $request->string('state')->value();
        $context = $state ? Cache::pull($this->stateKey($state)) : null;
        $role = is_array($context) ? ($context['role'] ?? null) : null;
        $loginRoute = $this->loginRoute(is_string($role) ? $role : null);

        if ($request->filled('error')) {
            return redirect()->route($loginRoute)->withErrors(['email' => 'Google Sign-In was cancelled or denied.']);
        }

        if (! is_array($context) || ! $request->filled('code')) {
            return redirect()->route($loginRoute)->withErrors(['email' => 'The Google sign-in request expired or was already used. Please try again.']);
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
            $user = $this->resolveAccount($claims, $role);
        } catch (ValidationException $exception) {
            return redirect()->route($role === 'logistics' ? 'logistics.login' : 'register')->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route($loginRoute)->withErrors(['email' => 'Google Sign-In could not be completed safely. Please try again.']);
        }

        if ($user->is_suspended || $user->isAdmin()) {
            return redirect()->route($loginRoute)->withErrors(['email' => 'This account cannot use public Google Sign-In.']);
        }

        if ($role === 'logistics') {
            $provider = $user->logisticsProvider;

            if (! $user->isLogistics() || ! $provider) {
                return redirect()->route('logistics.login')->withErrors(['email' => 'This Google account is not registered as a Lumear Logistics provider.']);
            }

            if (in_array($provider->status, ['rejected', 'suspended'], true)) {
                return redirect()->route('logistics.login')->withErrors(['email' => "Your Logistics provider account is {$provider->status}."]);
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        DB::table('login_attempts')->insert(['user_id' => $user->id, 'email' => $user->email, 'method' => 'google', 'ip_address' => $request->ip(), 'success' => true, 'created_at' => now()]);
        $this->audit->record($request, 'auth.google_login', $user, null, ['role' => $role]);

        return redirect()->to($this->destinationFor($user, $context['redirect'], $role));
    }
    /** @param array<string, mixed> $claims */
    private function resolveAccount(array $claims, ?string $requiredRole = null): User
    {
        $identity = AuthIdentity::with('user')->where('provider', 'google')->where('provider_user_id', $claims['sub'])->first();
        if ($identity) {
            $identity->update(['provider_email' => $claims['email'], 'last_used_at' => now()]);

            return $identity->user;
        }

        return DB::transaction(function () use ($claims, $requiredRole): User {
            $user = User::whereRaw('LOWER(email) = ?', [Str::lower($claims['email'])])->lockForUpdate()->first();
            if ($user && ! $user->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['email' => 'Sign in with your original method and verify the existing account before linking Google.']);
            }

            if (! $user) {
                throw ValidationException::withMessages([
                    'date_of_birth' => 'To create an account, register with your date of birth first. Google Sign-In is available after registration.',
                ]);
            }

            if ($requiredRole === 'logistics' && (! $user->isLogistics() || ! $user->logisticsProvider)) {
                throw ValidationException::withMessages([
                    'email' => 'This Google account is not registered as a Lumear Logistics provider.',
                ]);
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

        // Google issues the token using its own clock. A small leeway prevents
        // harmless local clock drift from rejecting a valid signed token.
        \Firebase\JWT\JWT::$leeway = max(
            0,
            min(120, (int) config('services.google.clock_skew_seconds', 60))
        );

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

    private function destinationFor(User $user, string $requestedRedirect, string $role): string
    {
        if ($role === 'logistics') {
            return $user->logisticsProvider?->status === 'pending'
                ? '/logistics/pending'
                : '/logistics/dashboard';
        }

        if ($user->isSeller()) {
            return $user->seller?->isApproved() ? '/seller/dashboard' : '/seller/onboarding';
        }

        return $requestedRedirect;
    }

    private function safeRedirect(string $redirect, string $role): string
    {
        $allowed = match ($role) {
            'seller' => ['/seller/onboarding', '/seller/status'],
            'logistics' => ['/logistics/dashboard', '/logistics/pending'],
            default => ['/home', '/cart'],
        };
        $fallback = match ($role) {
            'seller' => '/seller/onboarding',
            'logistics' => '/logistics/dashboard',
            default => '/home',
        };

        return in_array($redirect, $allowed, true) ? $redirect : $fallback;
    }

    private function loginRoute(?string $role): string
    {
        return $role === 'logistics' ? 'logistics.login' : 'login';
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
