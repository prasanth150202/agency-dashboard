<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\Partner;
use App\Models\Referral\ReferralCommission;
use Illuminate\Http\Request;

/**
 * Super Admin's global Commissions view — reads `referral_commissions`
 * directly (no agency scope; an agency filter narrows it). Legacy `Commission`
 * (transactions) is already visible via each Partner's own page and is not
 * duplicated here — this view is additive, not a replacement.
 */
class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['agency', 'status', 'source']);

        $commissions = ReferralCommission::query()
            ->with(['store', 'agency', 'lead', 'trackingLink', 'revenueEvent'])
            ->when(! empty($filters['agency']), fn ($q) => $q->where('agency_id', (int) $filters['agency']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['source']), fn ($q) => $q->where('revenue_type', $filters['source']))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $summary = ReferralCommission::query()
            ->whereIn('status', ReferralCommission::EARNED_STATUSES)
            ->selectRaw('status, currency, SUM(commission_amount) as total')
            ->groupBy('status', 'currency')
            ->get()
            ->groupBy('status');

        return view('admin.commissions.index', [
            'commissions' => $commissions,
            'filters' => $filters,
            'agencies' => Partner::orderBy('name')->get(['id', 'name']),
            'statuses' => [
                ReferralCommission::STATUS_PENDING, ReferralCommission::STATUS_ELIGIBLE,
                ReferralCommission::STATUS_IN_PAYOUT, ReferralCommission::STATUS_PAID,
                ReferralCommission::STATUS_REVERSED, ReferralCommission::STATUS_CANCELLED,
            ],
            'summary' => $summary,
        ]);
    }
}
