<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Organisation;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Agency Dashboard's "Commissions" page (kept on the historical
 * /earnings route/name — see routes/web.php). Renders the commission
 * ledger, KPI summary, and the right-rail Available Balance / My Stores
 * cards, all scoped to the session-derived organisation.
 */
class EarningsController extends Controller
{
    /** Status filter options shown in the UI — see Commission::visual_status. */
    private const STATUS_FILTERS = ['pending', 'eligible', 'available', 'in_payout', 'paid', 'refunded', 'cancelled'];

    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $finance = $organisation->finance();

        $filters = array_merge([
            'search' => '', 'range' => 'all_time', 'status' => 'all', 'store' => 'all',
        ], array_filter($request->only(['search', 'range', 'status', 'store']), fn ($v) => $v !== null && $v !== ''));

        [$rangeStart, $rangeEnd] = $this->resolveDateRange($filters['range']);

        $commissions = Commission::query()
            ->where('organisation_id', $organisation->id)
            ->with(['store', 'payouts'])
            ->when($rangeStart, fn ($q) => $q->whereBetween('transaction_date', [$rangeStart, $rangeEnd]))
            ->when($filters['store'] !== 'all', fn ($q) => $q->where('store_id', $filters['store']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = $filters['search'];
                // "Order ID" is displayed/searched as TXN-{id} (see
                // export() and the ledger view) — there's no separate
                // real order record to search against, so a "TXN-123"
                // or bare "123" search matches the commission's own id.
                $numericId = (int) preg_replace('/\D/', '', $term);

                $q->where(function ($q2) use ($term, $numericId) {
                    if ($numericId > 0) {
                        $q2->orWhere('id', $numericId);
                    }
                    $q2->orWhere('commission_amount', 'like', "%{$term}%")
                        ->orWhere('gross_amount', 'like', "%{$term}%")
                        ->orWhereHas('store', function ($q3) use ($term) {
                            $q3->where('name', 'like', "%{$term}%")->orWhere('shop_domain', 'like', "%{$term}%");
                        });
                });
            })
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get()
            // Status filtering happens on visual_status (display-only —
            // see Commission::getVisualStatusAttribute()), which isn't a
            // real column to filter by in SQL.
            ->when($filters['status'] !== 'all', fn ($c) => $c->filter(fn (Commission $row) => $row->visual_status === $filters['status'])->values());

        $perPage = 15;
        $page = (int) $request->query('page', 1);
        $commissionsPage = new LengthAwarePaginator(
            $commissions->forPage($page, $perPage)->values(),
            $commissions->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $myStores = Store::where('organisation_id', $organisation->id)
            ->withSum(['commissions as commission_total' => function ($q) {
                $q->where('status', '!=', Commission::STATUS_REFUNDED);
            }], 'commission_amount')
            ->orderByDesc('commission_total')
            ->limit(8)
            ->get();

        $stores = Store::where('organisation_id', $organisation->id)->orderBy('name')->get(['id', 'name']);

        return view('earnings.index', [
            'organisation' => $organisation,
            'finance' => $finance,
            'metrics' => [
                'total_earned' => $finance->lifetimeEarnings(),
                'pending' => $finance->pendingCommission(),
                'available' => $finance->availableBalance(),
                'paid' => $finance->commissionsPaid(),
            ],
            'commissions' => $commissionsPage,
            'stores' => $stores,
            'myStores' => $myStores,
            'statusFilters' => self::STATUS_FILTERS,
            'filters' => $filters,
            'minimumPayout' => $finance->minimumPayoutAmount(),
            'canRequestPayout' => $finance->canRequestPayout(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        [$rangeStart, $rangeEnd] = $this->resolveDateRange($request->query('range', 'all_time'));

        $commissions = Commission::query()
            ->where('organisation_id', $organisation->id) // never trust anything but the session-scoped org
            ->with('store')
            ->when($rangeStart, fn ($q) => $q->whereBetween('transaction_date', [$rangeStart, $rangeEnd]))
            ->orderByDesc('transaction_date')
            ->get();

        $filename = 'brix-commissions-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($commissions) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order ID', 'Store', 'Order Amount', 'Commission Rate', 'Commission', 'Date', 'Status']);

            foreach ($commissions as $commission) {
                fputcsv($out, [
                    'TXN-'.$commission->id,
                    $commission->store->name,
                    $commission->gross_amount,
                    $commission->commission_rate.'%',
                    $commission->commission_amount,
                    $commission->transaction_date->format('Y-m-d'),
                    $commission->status_label,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveDateRange(string $range): array
    {
        return match ($range) {
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'last_7_days' => [now()->subDays(7), now()],
            default => [null, null], // all_time
        };
    }
}
