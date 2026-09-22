<?php

namespace App\Services\Referral\Commission;

use Carbon\CarbonInterface;

/** One verified piece of BRIX billing for one shop, as reported by a RevenueSource. */
final class RevenueEvent
{
    public function __construct(
        public readonly string $revenueType,
        public readonly string $source,
        public readonly string $externalEventId,
        public readonly string $shopDomain,
        public readonly string $amount,
        public readonly string $currency,
        public readonly CarbonInterface $occurredAt,
        public readonly array $metadata = [],
    ) {}
}
