<?php

namespace App\Policies;

use App\Models\Payout;
use App\Models\User;

class PayoutPolicy
{
    /**
     * A payout may only be viewed by a user whose currently selected
     * organisation (per session) owns it, and who actually belongs to
     * that organisation. Never trust an ?organisation_id from the URL.
     */
    public function view(User $user, Payout $payout): bool
    {
        $organisationId = session('organisation_id');

        if ($organisationId === null || (int) $organisationId !== $payout->organisation_id) {
            return false;
        }

        return $user->organisations()->whereKey($organisationId)->exists();
    }
}
