<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogisticsHeadAdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('logistics.head-admin.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($data['email']));

        $key = 'logistics-head-admin-login:' .
            hash('sha256', $email . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again later.',
            ]);
        }

        if (!Auth::attempt([
            'email' => $email,
            'password' => $data['password'],
        ])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        $user = Auth::user();

        $isHeadAdmin = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('roles.name', 'logistics_head_admin')
            ->exists();

        if (!$isHeadAdmin || $user->is_suspended) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account is not authorized as Logistics Head Admin.',
            ]);
        }

        RateLimiter::clear($key);

        $request->session()->regenerate();

        return redirect()->route('logistics.head-admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logistics.head-admin.login');
    }
}
