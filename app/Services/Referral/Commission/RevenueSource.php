<?php

namespace App\Services\Referral\Commission;

use App\Models\Referral\Lead;
use Illuminate\Support\Collection;

interface RevenueSource
{
    /** The revenue type this provides: subscription | usage. */
    public function key(): string;

    /**
     * Verified revenue for the given referred leads' shops. Must be
     * read-only against BRIX billing data and must never estimate.
     *
     * @param  Collection<int, Lead>  $leads
     */
    public function collect(Collection $leads): RevenueCollection;
}
