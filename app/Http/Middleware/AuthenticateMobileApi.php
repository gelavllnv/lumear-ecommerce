<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileApi
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $token = $request->bearerToken();
        $record = $token ? DB::table('mobile_api_tokens')->where('token_hash', hash('sha256', $token))->when($role !== 'any', fn ($query) => $query->where('role', $role))->where('expires_at', '>', now())->first() : null;
        abort_unless($record, 401, 'Please sign in again.');
        $role = $record->role;
        $user = User::find($record->user_id);
        abort_unless($user && ! $user->is_suspended && $user->hasRole($role), 403, 'This account is unavailable.');
        abort_unless(in_array($role, ['logistics', 'rider_admin'], true) || $user->hasVerifiedEmail(), 403, 'Verify your email first.');
        if ($role === 'seller') {
            abort_unless($user->seller?->isApproved(), 403, 'An approved seller account is required.');
        }
        if ($role === 'logistics') {
            abort_unless($user->logisticsProvider?->status === 'approved', 403, 'An approved logistics account is required.');
        }
        $request->attributes->set('mobile_api_token_id', $record->id);
        $request->setUserResolver(fn () => $user);
        Auth::setUser($user);

        return $next($request);
    }
}
