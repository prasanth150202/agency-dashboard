<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partners\ActivityLog;
use App\Models\Partners\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $partners = Partner::query()
            ->withCount('stores')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->query('search');
                $q->where(function ($q2) use ($term) {
                    $q2->where('name', 'like', "%{$term}%")
                        ->orWhere('owner_email', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.partners.index', [
            'partners' => $partners,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Partner $partner)
    {
        $partner->load(['stores' => fn ($q) => $q->orderByDesc('last_active_at')]);

        $payouts = $partner->payouts()->orderByDesc('requested_at')->limit(10)->get();
        $ledgerBalance = (float) $partner->ledgerEntries()->sum('amount');
        $activity = ActivityLog::where('agency_id', $partner->id)->orderByDesc('created_at')->limit(15)->get();

        return view('admin.partners.show', [
            'partner' => $partner,
            'payouts' => $payouts,
            'ledgerBalance' => $ledgerBalance,
            'activity' => $activity,
        ]);
    }

    /**
     * Finance-only: adjust a partner's commission rate/status. Every
     * change is written to activity_logs for audit purposes.
     */
    public function update(Request $request, Partner $partner)
    {
        $validated = $request->validate([
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['active', 'trial', 'suspended'])],
        ]);

        $before = $partner->only(['commission_rate', 'status']);

        $partner->update($validated);

        ActivityLog::record('PARTNER_UPDATED_BY_ADMIN', $partner->id, null, [
            'admin_email' => Auth::guard('admin')->user()->email,
            'before' => $before,
            'after' => $validated,
        ], $request);

        return back()->with('success', 'Partner updated.');
    }
}
