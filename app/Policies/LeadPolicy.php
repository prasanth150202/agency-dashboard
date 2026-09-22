<?php

namespace App\Policies;

use App\Models\Referral\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * A lead may only be viewed/managed by a user whose currently selected
     * organisation (per session) owns it, and who actually belongs to that
     * organisation. Never trust an agency id from the request.
     */
    public function view(User $user, Lead $lead): bool
    {
        $organisationId = session('organisation_id');

        if ($organisationId === null) {
            return false;
        }

        $organisation = $user->organisations()->whereKey($organisationId)->first();

        return $organisation !== null && (int) $organisation->brix_agency_id === (int) $lead->agency_id;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }
}
