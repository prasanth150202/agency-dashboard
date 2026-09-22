<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\ActivityLog;
use App\Models\Partners\Partner;
use App\Models\Payout;
use App\Support\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'queue');
        $search = trim((string) $request->query('search', ''));
        $agencyId = $request->query('agency_id');

        $payouts = Payout::query()
            ->with('partner')
            ->withCount(['commissions', 'referralCommissions'])
            ->when($status === 'queue', fn ($q) => $q->whereIn('status', Payout::RESERVING_STATUSES))
            ->when($status !== 'queue' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('payout_code', 'like', "%{$search}%")
                        ->orWhere('transfer_reference', 'like', "%{$search}%")
                        ->orWhereHas('partner', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('requested_at')
            ->paginate(20)
            ->withQueryString();

        $kpis = [
            'pending_requests' => Payout::where('status', Payout::STATUS_PENDING)->count(),
            'under_review' => Payout::where('status', Payout::STATUS_UNDER_REVIEW)->count(),
            'approved_awaiting_transfer' => Payout::where('status', Payout::STATUS_APPROVED)->count(),
            'paid_this_month' => (float) Payout::where('status', Payout::STATUS_PAID)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'total_paid' => (float) Payout::where('status', Payout::STATUS_PAID)->sum('amount'),
            'total_requested' => (float) Payout::sum('amount'),
        ];

        $agencies = Partner::orderBy('name')->get(['id', 'name']);

        return view('admin.payouts.index', [
            'payouts' => $payouts,
            'status' => $status,
            'search' => $search,
            'agencyId' => $agencyId,
            'agencies' => $agencies,
            'kpis' => $kpis,
        ]);
    }

    public function show(Payout $payout)
    {
        $payout->load('partner', 'payoutAccount', 'commissions.store', 'referralCommissions.store', 'reviewedByAdmin', 'approvedByAdmin', 'rejectedByAdmin', 'paidByAdmin');

        return view('admin.payouts.show', ['payout' => $payout]);
    }

    /**
     * pending -> under_review. Lets the Admin signal they've started
     * looking at a request, before deciding to approve or reject it.
     */
    public function review(Request $request, Payout $payout)
    {
        abort_unless($payout->status === Payout::STATUS_PENDING, 422, 'Only a pending payout can be moved to review.');

        $admin = Auth::guard('admin')->user();
        $payout->markUnderReview($admin->id);

        ActivityLog::record('PAYMENT_REVIEW_STARTED', $payout->agency_id, null, [
            'admin_email' => $admin->email,
            'payout_id' => $payout->id,
        ], $request);

        return back()->with('success', "Payout {$payout->payout_code} is now under review.");
    }

    /**
     * pending|under_review -> approved. The commissions remain reserved
     * for this payout — they are not released until reject/cancel.
     */
    public function approve(Request $request, Payout $payout)
    {
        abort_unless(in_array($payout->status, [Payout::STATUS_PENDING, Payout::STATUS_UNDER_REVIEW], true), 422, 'Only a pending or under-review payout can be approved.');

        $admin = Auth::guard('admin')->user();

        $payout->update([
            'status' => Payout::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        ActivityLog::record('PAYMENT_APPROVED', $payout->agency_id, null, [
            'admin_email' => $admin->email,
            'payout_id' => $payout->id,
            'amount' => (float) $payout->amount,
        ], $request);

        return back()->with('success', "Payout {$payout->payout_code} approved.");
    }

    public function reject(Request $request, Payout $payout)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $admin = Auth::guard('admin')->user();

        DB::transaction(fn () => $payout->markRejected($validated['reason'], $admin->id));

        ActivityLog::record('PAYMENT_REJECTED', $payout->agency_id, null, [
            'admin_email' => $admin->email,
            'payout_id' => $payout->id,
            'reason' => $validated['reason'],
        ], $request);

        return back()->with('success', "Payout {$payout->payout_code} rejected.");
    }

    /**
     * Records the manual bank transfer the Admin has already made outside
     * this application, and marks the payout PAID in one confirmed step.
     * Enforces the two financial-safety rules the app can actually check:
     * the transfer currency must match the payout's (no silent conversion),
     * and the paid amount must equal the approved amount (partial payouts
     * are not supported — see AgencyFinanceService/UnifiedCommissionService,
     * which never split a claimed commission across two payouts).
     */
    public function markPaid(Request $request, Payout $payout)
    {
        abort_unless($payout->status === Payout::STATUS_APPROVED, 422, 'Only an approved payout can be marked as paid.');

        $validated = $request->validate([
            'transfer_reference' => ['required', 'string', 'max:100'],
            'transfer_date' => ['required', 'date'],
            'paid_amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_notes' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'You must confirm that the manual bank transfer has been completed.',
        ]);

        if (strtoupper($validated['currency']) !== strtoupper($payout->currency)) {
            return back()->withErrors(['currency' => 'Currency mismatch. Manual conversion is not supported.'])->withInput();
        }

        if (round((float) $validated['paid_amount'], 2) !== round((float) $payout->amount, 2)) {
            return back()->withErrors([
                'paid_amount' => 'Paid amount must equal the approved amount of '.Currency::format($payout->amount, $payout->currency).'. Partial payouts are not supported.',
            ])->withInput();
        }

        $admin = Auth::guard('admin')->user();

        DB::transaction(function () use ($payout, $validated, $admin, $request) {
            ActivityLog::record('PAYMENT_TRANSFER_RECORDED', $payout->agency_id, null, [
                'admin_email' => $admin->email,
                'payout_id' => $payout->id,
                'transfer_reference' => $validated['transfer_reference'],
                'transfer_date' => $validated['transfer_date'],
            ], $request);

            $payout->markPaid([
                'transfer_reference' => $validated['transfer_reference'],
                'transfer_date' => $validated['transfer_date'],
                'paid_amount' => round((float) $validated['paid_amount'], 2),
                'paid_currency' => strtoupper($validated['currency']),
                'payment_notes' => $validated['payment_notes'] ?? null,
                'paid_by' => $admin->id,
            ]);

            ActivityLog::record('PAYMENT_PAID', $payout->agency_id, null, [
                'admin_email' => $admin->email,
                'payout_id' => $payout->id,
                'amount' => (float) $payout->amount,
            ], $request);
        });

        return back()->with('success', "Payout {$payout->payout_code} marked paid.");
    }
}
