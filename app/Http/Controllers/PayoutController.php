<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Payout;
use App\Models\PayoutAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $finance = $organisation->finance();

        $transactions = Payout::where('organisation_id', $organisation->id)
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('payouts.index', [
            'organisation' => $organisation,
            'finance' => $finance,
            'account' => $organisation->payoutAccount,
            'metrics' => [
                'available' => $finance->availableBalance(),
                'pending' => $finance->pendingPayouts(),
                'processing' => $finance->processingPayouts(),
                'paid' => $finance->totalPaid(),
            ],
            'minimumPayout' => $finance->minimumPayoutAmount(),
            'canRequestPayout' => $finance->canRequestPayout(),
            'hasActiveRequest' => $finance->hasPendingOrProcessingPayout(),
            'transactions' => $transactions,
        ]);
    }

    /**
     * Request a payout. Fully validated and calculated server-side —
     * the browser only supplies the amount the agency wants withdrawn,
     * never the resulting balance.
     *
     * The balance re-check and the payout insert happen inside a single
     * DB transaction, holding a row lock on the organisation for its
     * duration. That closes the race where two near-simultaneous
     * requests both read "sufficient balance" before either commits —
     * the second request blocks until the first finishes, then re-reads
     * a balance that already accounts for it. Every query is scoped to
     * $organisation->id from the session, so there is no path for one
     * agency to reserve or withdraw another agency's balance.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $account = $organisation->payoutAccount;

        if (! $account) {
            return response()->json(['message' => 'Payout account not configured.'], 422);
        }

        if (! $account->is_verified) {
            return response()->json(['message' => 'You cannot request a payout until your payout account is verified.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $requestedAmount = round((float) $validated['amount'], 2);

        try {
            $payout = DB::transaction(function () use ($organisation, $account, $requestedAmount) {
                // Lock this agency's row for the duration of the check +
                // insert. Concurrent requests for *other* agencies are
                // completely unaffected — only this one row serializes.
                $locked = Organisation::whereKey($organisation->id)->lockForUpdate()->first();
                $finance = $locked->finance();

                if ($finance->hasPendingOrProcessingPayout()) {
                    throw new RuntimeException('You already have a payout request in progress.');
                }

                $availableBalance = $finance->availableBalance();
                $minimum = $finance->minimumPayoutAmount();

                if ($availableBalance < $minimum) {
                    throw new RuntimeException(
                        'Your balance has not reached the minimum payout amount of ₹'.number_format($minimum, 2).'.'
                    );
                }

                if ($requestedAmount <= 0) {
                    throw new RuntimeException('Amount must be greater than zero.');
                }

                if ($requestedAmount > $availableBalance) {
                    throw new RuntimeException('Amount cannot exceed your available balance.');
                }

                if ($requestedAmount < $minimum) {
                    throw new RuntimeException(
                        'Amount cannot be below the minimum payout of ₹'.number_format($minimum, 2).'.'
                    );
                }

                return Payout::create([
                    'organisation_id' => $locked->id,
                    'date' => now()->toDateString(),
                    'description' => 'Payout requested',
                    'amount' => $requestedAmount,
                    'currency' => 'INR',
                    'status' => Payout::STATUS_PENDING,
                    'payment_method' => $account->method,
                    'payout_account_id' => $account->id,
                    'provider' => 'manual',
                    'requested_at' => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $organisation->notifications()->create([
            'type' => 'payout_requested',
            'title' => 'Payout request submitted',
            'message' => "Your payout request {$payout->payout_code} of ₹".number_format($requestedAmount, 2).' has been submitted.',
        ]);

        return response()->json([
            'payout' => [
                'id' => $payout->id,
                'payout_code' => $payout->payout_code,
                'amount' => number_format($requestedAmount, 2),
                'status' => 'Pending Review',
                'requested_at' => $payout->requested_at->format('M j, Y'),
                'detail_url' => route('payouts.show', $payout),
            ],
        ]);
    }

    public function show(Request $request, Payout $payout)
    {
        Gate::authorize('view', $payout);

        $payout->load('payoutAccount', 'store');

        return view('payouts.show', [
            'payout' => $payout,
        ]);
    }
}
