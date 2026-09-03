<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EarningsController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $finance = $organisation->finance();

        $filters = $request->only(['search', 'range', 'status', 'plan', 'rate']);
        [$rangeStart, $rangeEnd] = $this->resolveDateRange($filters['range'] ?? 'this_month');

        $commissions = Commission::query()
            ->where('organisation_id', $organisation->id)
            ->with('store')
            ->when($rangeStart, fn ($q) => $q->whereBetween('transaction_date', [$rangeStart, $rangeEnd]))
            ->get();

        // Store Earnings is aggregated per store; every filter below acts
        // on that aggregate so the table and the CSV export stay in sync.
        $storeEarnings = $commissions
            ->groupBy('store_id')
            ->map(function ($group) {
                $store = $group->first()->store;
                $latest = $group->sortByDesc('transaction_date')->first();

                // Split per-store earnings by effective status — a "pending"
                // commission whose holding period has already lifted counts
                // toward "available", not "pending" (see Commission::effective_status).
                $pending = $group->filter(fn ($c) => $c->effective_status === Commission::STATUS_PENDING);
                $available = $group->filter(fn ($c) => $c->effective_status === Commission::STATUS_AVAILABLE);

                return (object) [
                    'store' => $store,
                    'gross_revenue' => $group->sum('gross_amount'),
                    'commission_earned' => $group->sum('commission_amount'),
                    'commission_rate' => $store->effective_commission_rate,
                    'commission_source_label' => $store->commission_source_label,
                    'pending_amount' => $pending->sum('commission_amount'),
                    'available_amount' => $available->sum('commission_amount'),
                    'status' => $latest->effective_status,
                    'latest_commission' => $latest,
                ];
            })
            ->filter(function ($row) use ($filters) {
                if (! empty($filters['search']) && ! str_contains(strtolower($row->store->name), strtolower($filters['search']))) {
                    return false;
                }
                if (! empty($filters['status']) && $filters['status'] !== 'all' && $row->status !== $filters['status']) {
                    return false;
                }
                if (! empty($filters['plan']) && $filters['plan'] !== 'all' && $row->store->plan !== $filters['plan']) {
                    return false;
                }

                return true;
            })
            ->values();

        // Rate-type filter (kept outside the closure above for clarity).
        if (! empty($filters['rate']) && $filters['rate'] !== 'all') {
            $storeEarnings = $storeEarnings->filter(
                fn ($row) => $row->store->commission_source === ($filters['rate'] === 'custom' ? 'custom' : 'agency_default')
            )->values();
        }

        $plans = \App\Models\Store::where('organisation_id', $organisation->id)
            ->select('plan')->distinct()->pluck('plan');

        return view('earnings.index', [
            'organisation' => $organisation,
            'finance' => $finance,
            'metrics' => [
                'this_month' => $finance->thisMonthEarnings(),
                'available' => $finance->availableBalance(),
                'pending' => $finance->pendingCommission(),
                'lifetime' => $finance->lifetimeEarnings(),
            ],
            'storeEarnings' => $storeEarnings,
            'plans' => $plans,
            'filters' => array_merge([
                'search' => '', 'range' => 'this_month', 'status' => 'all', 'plan' => 'all', 'rate' => 'all',
            ], array_filter($filters, fn ($v) => $v !== null)),
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

        $filename = 'brix-earnings-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($commissions) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Store', 'Plan', 'Transaction ID', 'Date', 'Gross Revenue', 'Commission Rate', 'Commission', 'Status']);

            foreach ($commissions as $commission) {
                fputcsv($out, [
                    $commission->store->name,
                    $commission->store->plan,
                    'TXN-'.$commission->id,
                    $commission->transaction_date->format('Y-m-d'),
                    $commission->gross_amount,
                    $commission->commission_rate.'%',
                    $commission->commission_amount,
                    ucfirst($commission->effective_status),
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
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'last_7_days' => [now()->subDays(7), now()],
            'all_time' => [null, null],
            default => [now()->startOfMonth(), now()->endOfMonth()], // this_month
        };
    }
}
