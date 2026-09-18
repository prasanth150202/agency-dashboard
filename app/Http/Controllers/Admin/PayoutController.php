<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\ActivityLog;
use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'queue');

        $payouts = Payout::query()
            ->with('partner')
            ->when($status === 'queue', fn ($q) => $q->whereIn('status', Payout::RESERVING_STATUSES))
            ->when($status !== 'queue' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('requested_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payouts.index', [
            'payouts' => $payouts,
            'status' => $status,
        ]);
    }

    public function show(Payout $payout)
    {
        $payout->load('partner', 'payoutAccount', 'commissions.store');

        return view('admin.payouts.show', ['payout' => $payout]);
    }

    /**
     * pending -> approved. The one step this app didn't have an admin UI
     * for before — payouts.status already supported 'approved', nothing
     * ever set it.
     */
    public function approve(Request $request, Payout $payout)
    {
        abort_unless($payout->status === Payout::STATUS_PENDING, 422, 'Only a pending payout can be approved.');

        $payout->update(['status' => Payout::STATUS_APPROVED, 'approved_at' => now()]);

        ActivityLog::record('PAYOUT_APPROVED', $payout->agency_id, null, [
            'admin_email' => Auth::guard('admin')->user()->email,
            'payout_id' => $payout->id,
            'amount' => (float) $payout->amount,
        ], $request);

        return back()->with('success', "Payout {$payout->payout_code} approved.");
    }

    public function reject(Request $request, Payout $payout)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        DB::transaction(fn () => $payout->markRejected($validated['reason']));

        ActivityLog::record('PAYOUT_REJECTED', $payout->agency_id, null, [
            'admin_email' => Auth::guard('admin')->user()->email,
            'payout_id' => $payout->id,
            'reason' => $validated['reason'],
        ], $request);

        return back()->with('success', "Payout {$payout->payout_code} rejected.");
    }

    /** approved -> processing -> paid, once BRIX has actually sent the money. */
    public function markPaid(Request $request, Payout $payout)
    {
        abort_unless(in_array($payout->status, [Payout::STATUS_APPROVED, Payout::STATUS_PROCESSING], true), 422, 'This payout is not ready to be marked paid.');

        DB::transaction(fn () => $payout->markPaid());

        ActivityLog::record('PAYOUT_PAID', $payout->agency_id, null, [
            'admin_email' => Auth::guard('admin')->user()->email,
            'payout_id' => $payout->id,
            'amount' => (float) $payout->amount,
        ], $request);

        return back()->with('success', "Payout {$payout->payout_code} marked paid.");
    }
}
