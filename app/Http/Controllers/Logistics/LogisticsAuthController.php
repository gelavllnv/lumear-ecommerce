<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogisticsAuthController extends Controller
{
    public function showLogin()
    {
        return view('logistics.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $email = Str::lower(trim($credentials['email']));

        $throttleKey = 'logistics-login:' .
            hash('sha256', $email . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again later.',
            ]);
        }

        if (!Auth::attempt([
            'email' => $email,
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {

            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        $user = Auth::user();

        $hasLogisticsRole = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('roles.name', 'logistics')
            ->exists();

        if (!$hasLogisticsRole) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account is not registered as a Logistics provider.',
            ]);
        }

        if ($user->is_suspended) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account has been suspended.',
            ]);
        }

        $provider = DB::table('logistics_providers')
            ->where('user_id', $user->id)
            ->first();

        if (!$provider) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'No Logistics provider record was found for this account.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        if ($provider->status === 'pending') {
            return redirect()->route('logistics.pending');
        }

        if ($provider->status === 'rejected') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your Logistics application was rejected.',
            ]);
        }

        if ($provider->status === 'suspended') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your Logistics provider account is suspended.',
            ]);
        }

        if ($provider->status !== 'approved') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your Logistics account is not authorized.',
            ]);
        }

        return redirect()->route('logistics.dashboard');
    }

    public function status(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isLogistics(), 403);

        $provider = DB::table('logistics_providers')
            ->where('user_id', $user->id)
            ->first();

        if (! $provider) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'No Logistics provider record was found for this account.',
            ]);
        }

        return match ($provider->status) {
            'approved' => redirect()->route('logistics.dashboard'),
            'pending' => redirect()->route('logistics.pending'),
            'rejected', 'suspended' => $this->denyStatusAccess(
                $request,
                "Your Logistics provider account is {$provider->status}."
            ),
            default => $this->denyStatusAccess(
                $request,
                'Your Logistics provider account is not authorized.'
            ),
        };
    }

    private function denyStatusAccess(Request $request, string $message)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logistics.login');
    }
}
