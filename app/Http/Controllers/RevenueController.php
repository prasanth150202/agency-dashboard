<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Services\Referral\Commission\CommissionSourceMode;
use App\Services\Referral\ReferralReporting;
use Illuminate\Http\Request;

/**
 * Revenue: verified BRIX billing earned by referred stores. Reads only
 * `referral_revenue_events` (and the commissions derived from them), never
 * the legacy `transactions` table.
 */
class RevenueController extends Controller
{
    private const RANGES = ['30' => 30, '90' => 90, '365' => 365, 'all' => null];

    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agencyId = $organisation->brixAgency()->id;

        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '90';
        $days = self::RANGES[$range];
        $from = $days ? now()->subDays($days - 1)->startOfDay() : null;
        $type = in_array($request->query('type'), ReferralRevenueEvent::TYPES, true) ? $request->query('type') : null;

        // A link filter only means something inside this agency: a foreign id
        // matches no lead of ours, so it can only ever yield an empty result.
        $linkId = $request->filled('link') ? (int) $request->query('link') : null;

        $reporting = new ReferralReporting($agencyId);
        $events = $reporting->eventsQuery($from, null, $type, $linkId);

        $months = collect(range(5, 0))->map(fn (int $i) => now()->subMonthsNoOverflow($i)->startOfMonth());

        $mode = CommissionSourceMode::forAgency($agencyId);

        return view('revenue.index', [
            'range' => $range,
            'ranges' => array_keys(self::RANGES),
            'type' => $type,
            'types' => ReferralRevenueEvent::TYPES,
            'linkId' => $linkId,
            'links' => TrackingLink::where('agency_id', $agencyId)->orderBy('name')->get(['id', 'name']),
            'revenue' => $reporting->revenueFor($events),
            'commission' => $reporting->commissionFor($events),
            'eventCount' => (clone $events)->count(),
            'storeCount' => (clone $events)->distinct()->count('referral_revenue_events.store_id'),
            'rows' => (clone $events)->with(['lead.trackingLink', 'commission'])
                ->orderByDesc('referral_revenue_events.occurred_at')->orderByDesc('referral_revenue_events.id')
                ->paginate(20)->withQueryString(),
            'monthly' => $months->map(function ($month) use ($reporting, $type, $linkId) {
                $q = $reporting->eventsQuery($month, $month->copy()->endOfMonth(), $type, $linkId);

                return ['label' => $month->format('M Y'), 'revenue' => $reporting->revenueFor($q), 'commission' => $reporting->commissionFor($q)];
            }),
            'subscriptionNotVerifiable' => CommissionSourceMode::isValid($mode)
                && CommissionSourceMode::includes($mode, ReferralRevenueEvent::TYPE_SUBSCRIPTION),
        ]);
    }
}
