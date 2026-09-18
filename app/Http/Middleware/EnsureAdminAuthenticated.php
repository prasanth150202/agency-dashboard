<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /admin section. Deliberately separate from Laravel's default
 * `auth` middleware so an unauthenticated visit redirects to the admin
 * login page, not the partner one — the two are completely separate
 * account systems (admin_users vs users/organisations).
 */
class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        if (Auth::guard('admin')->user()->status !== 'active') {
            Auth::guard('admin')->logout();

            return redirect()->route('admin.login')->withErrors(['email' => 'This admin account has been suspended.']);
        }

        return $next($request);
    }
}
