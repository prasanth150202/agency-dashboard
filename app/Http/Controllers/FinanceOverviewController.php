<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use Illuminate\Http\Request;

class FinanceOverviewController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $finance = $organisation->finance();

        return view('finance.overview', [
            'organisation' => $organisation,
            'metrics' => [
                'total_earnings' => $finance->lifetimeEarnings(),
                'this_month' => $finance->thisMonthEarnings(),
                'pending_commission' => $finance->pendingCommission(),
                'available_balance' => $finance->availableBalance(),
                'processing_payouts' => $finance->processingPayouts(),
                'paid_out' => $finance->totalPaid(),
            ],
        ]);
    }
}
