<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Organisation;
use App\Services\Finance\LedgerEntry;
use App\Services\Finance\UnifiedCommissionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Agency Dashboard's "Commissions" page (kept on the historical
 * /earnings route/name — see routes/web.php). Renders the unified commission
 * ledger — store commissions and referral commissions side by side, each
 * still in its own table — plus the KPI summary and the right-rail Available
 * Balance / My Stores cards, all scoped to the session-derived organisation.
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
        $unified = new UnifiedCommissionService($organisation);

        $filters = array_merge([
            'search' => '', 'range' => 'all_time', 'status' => 'all', 'store' => 'all', 'source' => 'all',
        ], array_filter($request->only(['search', 'range', 'status', 'store', 'source']), fn ($v) => $v !== null && $v !== ''));

        [$rangeStart, $rangeEnd] = $this->resolveDateRange($filters['range']);

        $entries = $unified->entries($rangeStart, $rangeEnd, [
            'search' => $filters['search'],
            'status' => $filters['status'],
            'store' => $filters['store'],
            'source' => in_array($filters['source'], LedgerEntry::SOURCES, true) ? $filters['source'] : null,
        ]);

        $perPage = 15;
        $page = max(1, (int) $request->query('page', 1));
        $commissionsPage = new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $myStores = $organisation->stores()
            ->withSum(['commissions as commission_total' => function ($q) {
                $q->where('commission_status', '!=', Commission::STATUS_REFUNDED);
            }], 'agency_commission')
            ->orderByDesc('commission_total')
            ->limit(8)
            ->get();

        $stores = $organisation->stores()->orderBy('store_name')->get(['id', 'store_name', 'agency_id']);

        return view('earnings.index', [
            'organisation' => $organisation,
            'finance' => $finance,
            'summary' => $unified->summary(),
            'metrics' => [
                'available' => $finance->availableBalance(),
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

        // Never trust anything but the session-scoped organisation.
        $entries = (new UnifiedCommissionService($organisation))->entries($rangeStart, $rangeEnd);

        $filename = 'brix-commissions-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order ID', 'Source', 'Store', 'Order Amount', 'Commission Rate', 'Commission', 'Currency', 'Date', 'Status']);

            foreach ($entries as $entry) {
                fputcsv($out, [
                    $entry->reference,
                    $entry->sourceLabel(),
                    $entry->storeName,
                    $entry->baseAmount,
                    $entry->rate.'%',
                    $entry->amount,
                    $entry->currency,
                    $entry->date->format('Y-m-d'),
                    $entry->statusLabel,
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
