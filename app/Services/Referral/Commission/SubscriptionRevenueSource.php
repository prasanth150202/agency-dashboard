<?php

namespace App\Services\Referral\Commission;

use App\Models\Referral\ReferralRevenueEvent;
use Illuminate\Support\Collection;

/**
 * Recurring subscription revenue — architecturally supported, but NOT YET
 * VERIFIABLE from data the Agency Dashboard can read.
 *
 * The real amount, interval, currency and test flag of a merchant's
 * recurring charge exist only in Shopify (BRIX persists none of them, and
 * `shops.plan_key` alone is not proof of paid revenue). Until a verifiable
 * source exists this reports "unverifiable" and yields no events — it never
 * derives an amount from plan labels or the legacy subscriptions/
 * transactions tables. When a verified source becomes available, only this
 * class changes; the mode configuration, service and ledger already
 * support subscription revenue.
 */
class SubscriptionRevenueSource implements RevenueSource
{
    public const NOT_VERIFIABLE = 'recurring_revenue_not_verifiable';

    public function key(): string
    {
        return ReferralRevenueEvent::TYPE_SUBSCRIPTION;
    }

    public function collect(Collection $leads): RevenueCollection
    {
        return RevenueCollection::unverifiable(self::NOT_VERIFIABLE);
    }
}
