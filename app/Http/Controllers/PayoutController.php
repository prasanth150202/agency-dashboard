<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Organisation;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Support\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $currency = $organisation->currency;

        try {
            $payout = DB::transaction(function () use ($organisation, $account, $requestedAmount, $currency) {
                // Lock this agency's row for the duration of the check +
                // insert. Concurrent requests for *other* agencies are
                // completely unaffected — only this one row serializes.
                $locked = Organisation::whereKey($organisation->id)->lockForUpdate()->first();
                $finance = $locked->finance();

                if ($finance->hasPendingOrProcessingPayout()) {
                    throw new RuntimeException('You already have a payout request in progress.');
                }

                if ($requestedAmount <= 0) {
                    throw new RuntimeException('Amount must be greater than zero.');
                }

                $minimum = $finance->minimumPayoutAmount();

                if ($requestedAmount < $minimum) {
                    throw new RuntimeException(
                        'Amount cannot be below the minimum payout of '.Currency::format($minimum, $currency).'.'
                    );
                }

                // Claim the actual commission rows first (locked for
                // update) — this is the authoritative balance check.
                // availableBalance() alone would agree with this sum by
                // construction, but claiming directly means the payout is
                // never created from a stale/racing read of the balance.
                $claimed = $finance->claimCommissionsForPayout($requestedAmount);
                $claimedSum = round((float) $claimed->sum('commission_amount'), 2);

                if ($claimedSum < $requestedAmount) {
                    throw new RuntimeException('Amount cannot exceed your available balance.');
                }

                // Commissions are claimed as whole rows (never split), so
                // the claimed sum can land above the amount the agency
                // typed in — e.g. requesting 3,000 when the oldest
                // available commission is a single 5,000 row. The payout
                // is for what was actually claimed; charging only the
                // typed amount while marking the full 5,000 IN_PAYOUT
                // would strand the 2,000 difference the moment this
                // payout is paid.
                $payout = Payout::create([
                    'organisation_id' => $locked->id,
                    'date' => now()->toDateString(),
                    'description' => 'Payout requested',
                    'amount' => $claimedSum,
                    'currency' => $currency,
                    'status' => Payout::STATUS_PENDING,
                    'payment_method' => $account->method,
                    'payout_account_id' => $account->id,
                    'provider' => 'manual',
                    'requested_at' => now(),
                ]);

                foreach ($claimed as $commission) {
                    $payout->commissions()->attach($commission->id, ['amount' => $commission->commission_amount]);
                    $commission->update(['status' => Commission::STATUS_IN_PAYOUT]);
                }

                return $payout;
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $organisation->notifications()->create([
            'type' => 'payout_requested',
            'title' => 'Payout request submitted',
            'message' => "Your payout request {$payout->payout_code} of ".Currency::format($payout->amount, $currency).' has been submitted.',
        ]);

        return response()->json([
            'payout' => [
                'id' => $payout->id,
                'payout_code' => $payout->payout_code,
                'amount' => number_format((float) $payout->amount, 2),
                'status' => 'Pending Review',
                'requested_at' => $payout->requested_at->format('M j, Y'),
                'detail_url' => route('payouts.show', $payout),
            ],
        ]);
    }

    public function show(Request $request, Payout $payout)
    {
        Gate::authorize('view', $payout);

        $payout->load('payoutAccount', 'store', 'commissions.store');

        return view('payouts.show', [
            'payout' => $payout,
        ]);
    }

    /**
     * The only status change an agency can make to their own payout —
     * withdraw a request that's still pending. Model-side guard
     * (Payout::markCancelled) re-checks the current status so this can
     * never cancel a payout BRIX has already approved/started processing.
     */
    public function cancel(Payout $payout): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $payout);

        if ($payout->status !== Payout::STATUS_PENDING) {
            $message = 'This payout can no longer be cancelled.';

            return request()->wantsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        DB::transaction(fn () => $payout->markCancelled());

        return request()->wantsJson()
            ? response()->json(['success' => true])
            : back()->with('success', 'Payout request cancelled.');
    }
}
