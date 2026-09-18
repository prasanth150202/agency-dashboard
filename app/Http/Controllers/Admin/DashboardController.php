<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\ActivityLog;
use App\Models\Partners\Partner;
use App\Models\Payout;
use App\Models\Store;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $metrics = [
            'partners' => Partner::count(),
            'active_partners' => Partner::where('status', 'active')->count(),
            'stores' => Store::count(),
            'installed_stores' => Store::where('installation_status', 'INSTALLED')->count(),
            'pending_payouts_count' => Payout::whereIn('status', Payout::RESERVING_STATUSES)->count(),
            'pending_payouts_amount' => (float) Payout::whereIn('status', Payout::RESERVING_STATUSES)->sum('amount'),
            'paid_this_month' => (float) Payout::where('status', Payout::STATUS_PAID)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];

        $recentPartners = Partner::orderByDesc('created_at')->limit(5)->get();

        $payoutQueue = Payout::with('partner')
            ->whereIn('status', Payout::RESERVING_STATUSES)
            ->orderBy('requested_at')
            ->limit(8)
            ->get();

        $recentActivity = ActivityLog::orderByDesc('created_at')->limit(10)->get();

        return view('admin.dashboard', [
            'metrics' => $metrics,
            'recentPartners' => $recentPartners,
            'payoutQueue' => $payoutQueue,
            'recentActivity' => $recentActivity,
        ]);
    }
}
