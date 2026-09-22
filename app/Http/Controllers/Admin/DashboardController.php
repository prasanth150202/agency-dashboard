<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\ActivityLog;
use App\Models\Partners\Partner;
use App\Models\Payout;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
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

        // Global referral metrics — every agency, every currency kept apart.
        $range = (int) $request->query('range', 30);
        $range = in_array($range, [7, 30, 90], true) ? $range : 30;
        $from = now()->subDays($range - 1)->startOfDay();

        $agenciesWithReferralActivity = Partner::whereHas('leads')->count();

        $referralMetrics = [
            'leads' => Lead::where('created_at', '>=', $from)->count(),
            'active_stores' => Lead::where('lead_stage', Lead::STAGE_ACTIVE)->count(),
            'revenue' => ReferralRevenueEvent::where('occurred_at', '>=', $from)
                ->selectRaw('currency, SUM(revenue_amount) as total')->groupBy('currency')->pluck('total', 'currency'),
            'pending_commission' => ReferralCommission::whereIn('status', [ReferralCommission::STATUS_PENDING, ReferralCommission::STATUS_ELIGIBLE])
                ->selectRaw('currency, SUM(commission_amount) as total')->groupBy('currency')->pluck('total', 'currency'),
            'paid_commission' => ReferralCommission::where('status', ReferralCommission::STATUS_PAID)
                ->selectRaw('currency, SUM(commission_amount) as total')->groupBy('currency')->pluck('total', 'currency'),
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
            'range' => $range,
            'agenciesWithReferralActivity' => $agenciesWithReferralActivity,
            'referralMetrics' => $referralMetrics,
        ]);
    }
}
