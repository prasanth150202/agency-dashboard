<?php

namespace App\Services\Referral;

use App\Models\Referral\Lead;
use App\Models\Referral\TrackingLink;
use App\Models\Store;

/**
 * Milestones for one agency's Overview page — every one derived from a real
 * count at read time, never stored or estimated. No monetary reward is
 * attached to any milestone.
 */
class Gamification
{
    private const STORE_COUNT_MILESTONES = [5, 10, 25];

    public function __construct(private readonly int $agencyId) {}

    /** @return list<array{key: string, label: string, achieved: bool}> */
    public function milestones(): array
    {
        $hasLink = TrackingLink::where('agency_id', $this->agencyId)->exists();
        $hasApprovedLead = Lead::forAgency($this->agencyId)->whereNotIn('lead_stage', [Lead::STAGE_NOT_INTERESTED, Lead::STAGE_LOST])->exists();
        $activeStores = Store::where('agency_id', $this->agencyId)->where('status', 'active')->count();

        $milestones = [
            ['key' => 'first_referral', 'label' => 'Created your first referral link', 'achieved' => $hasLink],
            ['key' => 'first_approved_lead', 'label' => 'Landed your first approved lead', 'achieved' => $hasApprovedLead],
            ['key' => 'first_active_store', 'label' => 'Activated your first store', 'achieved' => $activeStores >= 1],
        ];

        foreach (self::STORE_COUNT_MILESTONES as $count) {
            $milestones[] = [
                'key' => "active_stores_{$count}",
                'label' => "{$count} active stores",
                'achieved' => $activeStores >= $count,
            ];
        }

        return $milestones;
    }

    /** How many of the milestones above are achieved, out of the total — for a compact progress indicator. */
    public function progress(): array
    {
        $milestones = $this->milestones();
        $achieved = count(array_filter($milestones, fn (array $m) => $m['achieved']));

        return ['achieved' => $achieved, 'total' => count($milestones)];
    }
}
