<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogisticsHeadAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('logistics.head-admin.login');
        }

        $user = Auth::user();

        $isHeadAdmin = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('roles.name', 'logistics_head_admin')
            ->exists();

        if (!$isHeadAdmin || $user->is_suspended) {
            abort(403, 'Unauthorized Head Admin access.');
        }

        return $next($request);
    }
}
