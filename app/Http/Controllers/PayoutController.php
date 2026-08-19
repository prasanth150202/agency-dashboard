<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\OrganisationSettings;
use App\Models\Payout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $settings = $organisation->settings ?? OrganisationSettings::create([
            'organisation_id' => $organisation->id,
        ]);

        $pending = Payout::where('organisation_id', $organisation->id)
            ->where('status', 'pending')
            ->sum('amount');

        $transactions = Payout::where('organisation_id', $organisation->id)
            ->with('store')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(10);

        return view('payouts.index', [
            'organisation' => $organisation,
            'settings' => $settings,
            'pending' => $pending,
            'transactions' => $transactions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $settings = $organisation->settings;

        if (! $settings || (float) $settings->available_balance <= 0) {
            return back()->with('error', 'No available balance to request a payout for.');
        }

        $amount = (float) $settings->available_balance;

        Payout::create([
            'organisation_id' => $organisation->id,
            'date' => now()->toDateString(),
            'description' => 'Payout requested',
            'amount' => $amount,
            'status' => 'pending',
        ]);

        $settings->update(['available_balance' => 0]);

        $organisation->notifications()->create([
            'type' => 'payout_requested',
            'title' => 'Payout requested',
            'message' => 'A payout of ₹'.number_format($amount, 2).' has been requested.',
        ]);

        return redirect()
            ->route('payouts')
            ->with('success', 'Payout requested successfully.');
    }
}
