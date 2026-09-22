<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Services\Referral\ReferralFunnel;
use Illuminate\Http\Request;

/** Referral Tracking: the click-to-revenue funnel, per link and per channel. */
class TrackingController extends Controller
{
    private const RANGES = [7, 30, 90];

    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $range = (int) $request->query('range', 30);
        $range = in_array($range, self::RANGES, true) ? $range : 30;
        $from = now()->subDays($range - 1)->startOfDay();

        $funnel = new ReferralFunnel($organisation->brixAgency()->id);
        $rows = $funnel->byLink($from);
        $totals = ReferralFunnel::totals($rows);
        $daily = $funnel->dailyClicks($from);

        return view('tracking.index', [
            'range' => $range,
            'ranges' => self::RANGES,
            'rows' => $rows,
            'totals' => $totals,
            'channels' => ReferralFunnel::byChannel($rows),
            'daily' => $daily,
            'dailyMax' => max(1, max($daily ?: [0])),
            'hasAnyLinks' => $rows->isNotEmpty(),
        ]);
    }
}
