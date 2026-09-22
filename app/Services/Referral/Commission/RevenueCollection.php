<?php

namespace App\Services\Referral\Commission;

/**
 * What a RevenueSource found. `verifiable = false` means the source could
 * not be trusted or read (and says why) — the caller must accrue nothing
 * from it rather than estimate.
 */
final class RevenueCollection
{
    /** @param  list<RevenueEvent>  $events */
    public function __construct(
        public readonly bool $verifiable,
        public readonly ?string $reason = null,
        public readonly array $events = [],
    ) {}

    public static function unverifiable(string $reason): self
    {
        return new self(false, $reason);
    }

    /** @param  list<RevenueEvent>  $events */
    public static function verified(array $events): self
    {
        return new self(true, null, $events);
    }
}
