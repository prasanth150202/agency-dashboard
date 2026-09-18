<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\PayoutAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutSettingsController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        return view('payout-settings.index', [
            'organisation' => $organisation,
            'account' => $organisation->payoutAccount,
        ]);
    }

    /**
     * Validated entirely server-side, per method — client-side checks are
     * a convenience only. Full account numbers are never stored in
     * plaintext (see PayoutAccount::$casts) and never redisplayed.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $method = $request->input('method');

        $validated = $request->validate([
            'method' => ['required', Rule::in([PayoutAccount::METHOD_BANK_TRANSFER, PayoutAccount::METHOD_UPI])],
            'account_holder_name' => ['required_if:method,'.PayoutAccount::METHOD_BANK_TRANSFER, 'nullable', 'string', 'max:255'],
            'account_number' => ['required_if:method,'.PayoutAccount::METHOD_BANK_TRANSFER, 'nullable', 'digits_between:6,20'],
            'account_number_confirmation' => ['required_if:method,'.PayoutAccount::METHOD_BANK_TRANSFER, 'nullable', 'same:account_number'],
            'ifsc_code' => ['required_if:method,'.PayoutAccount::METHOD_BANK_TRANSFER, 'nullable', 'string', 'max:20'],
            'account_type' => ['required_if:method,'.PayoutAccount::METHOD_BANK_TRANSFER, 'nullable', Rule::in(['savings', 'current'])],
            'upi_id' => ['required_if:method,'.PayoutAccount::METHOD_UPI, 'nullable', 'string', 'max:255', 'regex:/^[\w.\-]+@[\w.\-]+$/'],
        ], [
            'account_number_confirmation.same' => 'Bank account numbers do not match.',
        ]);

        $payload = [
            'method' => $method,
            // Any change to payout details requires re-verification.
            'verification_status' => PayoutAccount::STATUS_PENDING_VERIFICATION,
        ];

        if ($method === PayoutAccount::METHOD_BANK_TRANSFER) {
            $payload['account_holder_name'] = $validated['account_holder_name'];
            $payload['account_number'] = $validated['account_number'];
            $payload['account_last4'] = substr($validated['account_number'], -4);
            $payload['ifsc_code'] = strtoupper($validated['ifsc_code']);
            $payload['account_type'] = $validated['account_type'];
            $payload['upi_id'] = null;
        } else {
            $payload['upi_id'] = $validated['upi_id'];
            $payload['account_holder_name'] = null;
            $payload['account_number'] = null;
            $payload['account_last4'] = null;
            $payload['ifsc_code'] = null;
            $payload['account_type'] = null;
        }

        PayoutAccount::updateOrCreate(
            ['organisation_id' => $organisation->id],
            $payload
        );

        // The audit trail visible to the agency for this domain — no
        // separate audit_logs table exists here, so this reuses the
        // existing notifications system rather than introducing one.
        $organisation->notifications()->create([
            'type' => 'bank_details_updated',
            'title' => 'Payout details updated',
            'message' => 'Your payout account details were updated and are now pending verification.',
        ]);

        return redirect()
            ->route('payout-settings')
            ->with('success', 'Payout details saved. Your account is now pending verification.');
    }
}
