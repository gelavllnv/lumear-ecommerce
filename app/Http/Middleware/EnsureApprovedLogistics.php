<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedLogistics
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('logistics.login');
        }

        $user = Auth::user();

        $hasLogisticsRole = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('roles.name', 'logistics')
            ->exists();

        if (!$hasLogisticsRole || $user->is_suspended) {
            abort(403, 'You are not authorized to access Logistics.');
        }

        $provider = DB::table('logistics_providers')
            ->where('user_id', $user->id)
            ->first();

        if (!$provider) {
            abort(403, 'No Logistics provider record was found.');
        }

        if ($provider->status === 'pending') {
            return redirect()->route('logistics.pending');
        }

        if ($provider->status !== 'approved') {
            abort(403, 'Your Logistics account is not approved.');
        }

        return $next($request);
    }
}
