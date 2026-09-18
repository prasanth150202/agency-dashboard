<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts payout approval/rejection and commission-rate edits to
 * AdminUser::FINANCE_ROLES (SUPER_ADMIN, FINANCE_ADMIN) — a SUPPORT_ADMIN
 * or ANALYST can view everything under /admin but never move money or
 * change a partner's rate.
 */
class EnsureAdminIsFinance
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::guard('admin')->user()?->isFinance(), 403, 'Only Finance or Super Admins can do this.');

        return $next($request);
    }
}
