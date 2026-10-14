<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function show(Request $request): View
    {
        return view('account.show', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return redirect()->route('account.show')->withErrors(['profile' => 'Admin profiles are locked. Contact another administrator for account changes.']);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);
        $before = $user->only(['name', 'phone']);
        $user->update($data);
        $this->audit->record($request, 'account.profile_updated', $user, $before, $user->only(['name', 'phone']));

        return redirect()->route('account.show')->with('status', 'Your profile was updated.');
    }
}
