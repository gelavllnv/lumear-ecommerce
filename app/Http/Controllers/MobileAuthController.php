<?php

namespace App\Http\Controllers;

use App\Models\User;
use Google\Client as GoogleClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function sendPasswordResetCode(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user && $user->hasRole('logistics') && $user->logisticsProvider) {
            $code = (string) random_int(1000, 9999);
            DB::table('logistics_password_reset_codes')->updateOrInsert(
                ['email' => $email],
                [
                    'code_hash' => Hash::make($code),
                    'reset_token_hash' => null,
                    'attempts' => 0,
                    'expires_at' => now()->addMinutes(10),
                    'verified_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            Mail::raw("Your Lumear Logistics password reset code is {$code}. It expires in 10 minutes.", function ($message) use ($email): void {
                $message->to($email)->subject('Lumear Logistics password reset code');
            });
        }

        return response()->json([
            'message' => 'If a Logistics account exists for this email, a reset code has been sent.',
        ]);
    }

    public function verifyPasswordResetCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:4'],
        ]);
        $email = Str::lower(trim($data['email']));
        $reset = DB::table('logistics_password_reset_codes')->where('email', $email)->first();

        if (! $reset || now()->greaterThan($reset->expires_at) || $reset->verified_at !== null || $reset->attempts >= 5) {
            throw ValidationException::withMessages(['code' => 'The code is invalid or expired. Request a new code.']);
        }

        if (! Hash::check($data['code'], $reset->code_hash)) {
            DB::table('logistics_password_reset_codes')->where('email', $email)->increment('attempts');
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }

        $resetToken = Str::random(64);
        DB::table('logistics_password_reset_codes')->where('email', $email)->update([
            'reset_token_hash' => hash('sha256', $resetToken),
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['reset_token' => $resetToken]);
    }

    public function resetForgottenPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $email = Str::lower(trim($data['email']));
        $reset = DB::table('logistics_password_reset_codes')->where('email', $email)->first();

        if (! $reset || ! $reset->verified_at || now()->greaterThan($reset->expires_at)
            || ! hash_equals((string) $reset->reset_token_hash, hash('sha256', $data['reset_token']))) {
            throw ValidationException::withMessages(['email' => 'Password reset session is invalid or expired. Request a new code.']);
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! $user->hasRole('logistics') || ! $user->logisticsProvider) {
            throw ValidationException::withMessages(['email' => 'No Logistics account was found for this email.']);
        }

        DB::transaction(function () use ($user, $data, $email): void {
            $user->update(['password' => Hash::make($data['password'])]);
            DB::table('mobile_api_tokens')->where('user_id', $user->id)->delete();
            DB::table('logistics_password_reset_codes')->where('email', $email)->delete();
        });

        return response()->json(['message' => 'Your Logistics password has been reset.']);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'role' => ['sometimes', Rule::in(['buyer', 'seller', 'logistics'])],
        ]);
        $user = User::where('email', Str::lower(trim($data['email'])))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }
        $role = $data['role'] ?? ($user->isBuyer() ? 'buyer' : 'seller');
        abort_unless(! $user->is_suspended && $user->hasRole($role), 403, 'This account cannot access this app.');
        if ($role !== 'logistics' && ! $user->hasVerifiedEmail()) {
            abort(403, 'Verify your email on the Lumear website before signing in.');
        }
        if ($role === 'seller') {
            abort_unless($user->seller?->isApproved(), 403, 'Your seller application must be approved first.');
        }
        if ($role === 'logistics') {
            abort_unless($user->logisticsProvider?->status === 'approved', 403, 'Your logistics application must be approved first.');
        }
        $token = Str::random(80);
        DB::table('mobile_api_tokens')->insert([
            'user_id' => $user->id, 'role' => $role, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['token' => $token, 'role' => $role]);
    }

    public function googleLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:8192'],
        ]);
        $clientId = config('services.google.mobile_client_id');
        abort_unless(filled($clientId), 503, 'Google sign-in is not configured on the server.');

        $client = new GoogleClient(['client_id' => $clientId]);
        $client->setHttpClient(new \GuzzleHttp\Client([
            'verify' => $this->googleCaBundlePath(),
        ]));
        $claims = $client->verifyIdToken($data['id_token']);

        abort_unless(is_array($claims), 401, 'Google identity verification failed.');
        abort_unless(
            in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true),
            401,
            'Invalid Google token issuer.'
        );
        abort_unless(($claims['aud'] ?? null) === $clientId, 401, 'Invalid Google token audience.');
        abort_unless(
            filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            401,
            'Your Google email address must be verified.'
        );
        abort_unless(isset($claims['email']), 401, 'Google account information is incomplete.');

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower((string) $claims['email'])])
            ->first();

        abort_unless($user, 403, 'No Logistics account is registered with this Google email.');
        abort_unless(! $user->is_suspended && $user->hasRole('logistics'), 403, 'This account cannot access the Logistics app.');
        abort_unless($user->logisticsProvider?->status === 'approved', 403, 'Your Logistics account must be approved first.');

        $token = Str::random(80);
        DB::table('mobile_api_tokens')->insert([
            'user_id' => $user->id,
            'role' => 'logistics',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['token' => $token, 'role' => 'logistics']);
    }

    private function googleCaBundlePath(): string
    {
        $path = config('services.google.ca_bundle');

        if (! is_string($path) || ! is_readable($path)) {
            throw new \RuntimeException('Google sign-in requires a readable CA certificate bundle for secure HTTPS connections.');
        }

        return $path;
    }

    public function logout(Request $request): JsonResponse
    {
        DB::table('mobile_api_tokens')->where('id', $request->attributes->get('mobile_api_token_id'))->delete();

        return response()->json(['success' => true]);
    }
}
