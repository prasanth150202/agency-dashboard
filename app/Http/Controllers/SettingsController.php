<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\OrganisationSettings;
use App\Models\Partners\ActivityLog;
use App\Services\Referral\Commission\CommissionSourceMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $role = $request->user()
            ->organisations()
            ->whereKey($organisation->id)
            ->first()
            ?->pivot
            ?->role;

        return view('settings.index', [
            'organisation' => $organisation,
            'role' => $role,
            'commissionSource' => CommissionSourceMode::normalize($organisation->settings?->commission_revenue_source),
            'commissionSources' => CommissionSourceMode::ALL,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organisations', 'name')->ignore($organisation->id),
            ],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $organisation->update($validated);

        return back()->with('success', 'Partner settings updated.');
    }

    /**
     * The agency's own commission revenue source. Owner-only, validated
     * against the fixed mode list, and audited. Referral links have no say.
     */
    public function updateCommissionSource(Request $request): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $role = $request->user()->organisations()->whereKey($organisation->id)->first()?->pivot?->role;
        abort_unless($role === 'owner', 403);

        $validated = $request->validate([
            'commission_revenue_source' => ['required', Rule::in(CommissionSourceMode::ALL)],
        ]);

        $before = CommissionSourceMode::normalize($organisation->settings?->commission_revenue_source);

        OrganisationSettings::updateOrCreate(
            ['organisation_id' => $organisation->id],
            ['commission_revenue_source' => $validated['commission_revenue_source']]
        );

        ActivityLog::record('COMMISSION_REVENUE_SOURCE_CHANGED', $organisation->brixAgency()->id, null, [
            'from' => $before,
            'to' => $validated['commission_revenue_source'],
            'by' => $request->user()->email,
        ], $request);

        return back()->with('success', 'Commission revenue source updated.');
    }
}