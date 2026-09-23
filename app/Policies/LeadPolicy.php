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

    /**
     * Deletion is for cleaning up a mistake (wrong domain, duplicate entry,
     * etc), never for erasing a real BRIX relationship — once a lead is
     * matched to an actual store it's live data the pipeline (and
     * commission history) depends on, so it must be handled by changing
     * its stage instead. store_id null is the same signal recheckInstall()
     * already uses for "not yet a real, matched relationship".
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead) && $lead->store_id === null;
    }
}
