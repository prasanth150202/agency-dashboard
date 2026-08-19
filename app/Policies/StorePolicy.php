<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    /**
     * Determine whether the user can view the store.
     *
     * A store may only be viewed by a user whose currently selected
     * organisation (per session) owns the store, and who is actually
     * a member of that organisation.
     */
    public function view(User $user, Store $store): bool
    {
        $organisationId = session('organisation_id');

        if ($organisationId === null || (int) $organisationId !== $store->organisation_id) {
            return false;
        }

        return $user->organisations()->whereKey($organisationId)->exists();
    }

    public function update(User $user, Store $store): bool
    {
        return $this->view($user, $store);
    }
}
