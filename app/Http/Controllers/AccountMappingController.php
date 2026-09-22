<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Services\Referral\AccountMapping;
use Illuminate\Http\Request;

/** Account Mapping: referral leads <-> BRIX stores <-> authorization state. Read-only. */
class AccountMappingController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');
        $agency = $organisation->brixAgency();
        $mapping = new AccountMapping($agency->id);

        $role = $request->user()->organisations()->whereKey($organisation->id)->first()?->pivot?->role;

        return view('account-mapping.index', [
            'mapping' => $mapping,
            'agencyId' => $agency->id,
            'partner' => $agency,
            'role' => $role,
            'members' => $organisation->users()->orderBy('name')->get(['users.id', 'users.name']),
            'summary' => $mapping->summary(),
            'leads' => $mapping->leads()->orderByDesc('created_at')->orderByDesc('id')->paginate(15, pageName: 'leads_page')->withQueryString(),
            'directStores' => $mapping->directStores()->orderBy('store_name')->paginate(10, pageName: 'stores_page')->withQueryString(),
        ]);
    }
}
