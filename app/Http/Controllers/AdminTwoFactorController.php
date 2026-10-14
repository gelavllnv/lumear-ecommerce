<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorCredential;
use App\Services\AuditService;
use App\Services\TotpService;
use Illuminate\Http\Request;

class AdminTwoFactorController extends Controller
{
    public function __construct(private readonly TotpService $totp, private readonly AuditService $audit) {}

    public function setup(Request $request)
    {
        $user = $this->admin($request);
        $credential = $user->twoFactorCredential()->firstOrCreate([], ['secret' => $this->totp->generateSecret()]);

        return view('admin.two-factor-setup', compact('credential'));
    }

    public function confirm(Request $request)
    {
        $user = $this->admin($request);
        $credential = $user->twoFactorCredential;
        abort_unless($credential, 422, 'Start two-factor setup first.');
        $code = $request->validate(['code' => ['required', 'digits:6']])['code'];
        if (! $this->totp->verify($credential->secret, $code)) {
            return back()->withErrors(['code' => 'That authenticator code is not valid.']);
        }

        $credential->update(['confirmed_at' => now(), 'last_used_at' => now()]);
        $request->session()->put('admin_2fa_verified_at', now()->toIso8601String());
        $this->audit->record($request, 'admin.2fa_enabled', $credential);

        return redirect()->route('admin.dashboard')->with('status', 'Two-factor authentication is enabled.');
    }

    public function challenge(Request $request)
    {
        $user = $this->admin($request);
        if (! $user->twoFactorCredential?->confirmed_at) {
            return redirect()->route('admin.2fa.setup');
        }

        return view('admin.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $user = $this->admin($request);
        $credential = $user->twoFactorCredential;
        if (! $credential?->confirmed_at) {
            return redirect()->route('admin.2fa.setup');
        }
        $code = $request->validate(['code' => ['required', 'digits:6']])['code'];
        if (! $this->totp->verify($credential->secret, $code)) {
            return back()->withErrors(['code' => 'That authenticator code is not valid.']);
        }

        $credential->update(['last_used_at' => now()]);
        $request->session()->put('admin_2fa_verified_at', now()->toIso8601String());
        $this->audit->record($request, 'admin.2fa_challenged', $credential);

        return redirect()->intended(route('admin.dashboard'));
    }

    private function admin(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return $request->user();
    }
}
