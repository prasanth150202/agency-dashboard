<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Organisation;
use App\Models\Partners\ActivityLog;
use App\Models\Payout;
use App\Models\Referral\ReferralCommission;
use App\Services\Finance\UnifiedCommissionService;
use App\Support\Currency;
use App\Support\DecimalMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $filter = $request->query('filter', 'all');
        $search = trim((string) $request->query('search', ''));

        $statusesByFilter = [
            'pending' => [Payout::STATUS_PENDING, Payout::STATUS_UNDER_REVIEW],
            'approved' => [Payout::STATUS_APPROVED, Payout::STATUS_PROCESSING],
            'paid' => [Payout::STATUS_PAID],
            'rejected' => [Payout::STATUS_REJECTED],
        ];

        $transactions = $organisation->payouts()
            ->when(isset($statusesByFilter[$filter]), fn ($q) => $q->whereIn('status', $statusesByFilter[$filter]))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('payout_code', 'like', "%{$search}%")
                    ->orWhere('transfer_reference', 'like', "%{$search}%");
            }))
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('payouts.index', [
            'organisation' => $organisation,
            'finance' => $finance,
            'account' => $organisation->payoutAccount,
            'metrics' => [
                'available' => $finance->availableBalance(),
                'pending' => $finance->pendingPayouts(),
                'processing' => $finance->processingPayouts(),
                'paid' => $finance->totalPaid(),
                'total_earned' => $finance->lifetimeEarnings(),
            ],
            'minimumPayout' => $finance->minimumPayoutAmount(),
            'canRequestPayout' => $finance->canRequestPayout(),
            'hasActiveRequest' => $finance->hasPendingOrProcessingPayout(),
            'transactions' => $transactions,
            'filter' => $filter,
            'search' => $search,
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
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $requestedAmount = round((float) $validated['amount'], 2);
        $currency = $organisation->currency;
        $requestNotes = $validated['notes'] ?? null;
        $requestedBy = Auth::id();

        try {
            $payout = DB::transaction(function () use ($organisation, $account, $requestedAmount, $currency, $requestNotes, $requestedBy) {
                // Lock this organisation's row for the duration of the
                // check + insert. Concurrent requests for *other*
                // agencies are completely unaffected — only this one row
                // serializes.
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
                // Store commissions and referral commissions (in this
                // organisation's currency) are claimed together, oldest
                // first, from the unified commission layer.
                $requestedCents = DecimalMoney::toCents($requestedAmount);
                $claim = (new UnifiedCommissionService($locked))->claim($requestedCents);

                if ($claim['cents'] < $requestedCents) {
                    throw new RuntimeException('Amount cannot exceed your available balance.');
                }

                $claimedSum = DecimalMoney::format($claim['cents']);

                // Commissions are claimed as whole rows (never split), so
                // the claimed sum can land above the amount the agency
                // typed in — e.g. requesting 3,000 when the oldest
                // available commission is a single 5,000 row. The payout
                // is for what was actually claimed; charging only the
                // typed amount while marking the full 5,000 IN_PAYOUT
                // would strand the 2,000 difference the moment this
                // payout is paid.
                $payout = Payout::create([
                    'agency_id' => $locked->brix_agency_id,
                    'notes' => $requestNotes,
                    'amount' => $claimedSum,
                    'currency' => $currency,
                    'status' => Payout::STATUS_PENDING,
                    'payment_method' => $account->method_label,
                    'payout_account_id' => $account->id,
                    'requested_at' => now(),
                    'requested_by' => $requestedBy,
                ]);

                foreach ($claim['store'] as $commission) {
                    $payout->commissions()->attach($commission->id, ['amount' => $commission->agency_commission]);
                    $commission->update(['commission_status' => Commission::STATUS_IN_PAYOUT]);
                }

                foreach ($claim['referral'] as $commission) {
                    $payout->referralCommissions()->attach($commission->id, ['amount' => $commission->commission_amount]);
                    $commission->update(['status' => ReferralCommission::STATUS_IN_PAYOUT]);
                }

                return $payout;
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $organisation->notifications()->create([
            'type' => 'payout_requested',
            'title' => 'Payout request submitted',
            'message' => "Payment request {$payout->payout_code} has been submitted and is awaiting review.",
        ]);

        ActivityLog::record('PAYMENT_REQUESTED', $organisation->brix_agency_id, null, [
            'payout_id' => $payout->id,
            'amount' => (float) $payout->amount,
            'commission_count' => $payout->commissions()->count() + $payout->referralCommissions()->count(),
            'requested_by' => $requestedBy,
        ], $request);

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

        $payout->load('payoutAccount', 'commissions.store', 'referralCommissions.store', 'paidByAdmin');

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
