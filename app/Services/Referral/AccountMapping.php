<?php

namespace App\Services\Referral;

use App\Models\Referral\Lead;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only view of how one agency's referral leads line up with real BRIX
 * stores and their authorization state.
 *
 * Deliberately has no way to link a lead to a store: that match is made only
 * by the BRIX install callback (ReferralAttribution), which is what enforces
 * the existing-customer protection. A hand-made mapping would let commission
 * be attributed around it.
 */
class AccountMapping
{
    public const AWAITING_STORE = 'awaiting_store';

    public const STORE_MISSING = 'store_missing';

    public const MANAGED_ELSEWHERE = 'managed_elsewhere';

    public const NEEDS_ACTION = 'needs_action';

    public const MAPPED = 'mapped';

    public function __construct(private readonly int $agencyId) {}

    /** Leads that have a store domain or store, scoped to this agency. */
    public function leads(): Builder
    {
        return Lead::query()
            ->where('agency_id', $this->agencyId)
            ->where(fn ($q) => $q->whereNotNull('shop_domain')->orWhereNotNull('store_id'))
            ->with(['trackingLink', 'store.agencyStore']);
    }

    /** Stores this agency manages that no referral lead points at. */
    public function directStores(): Builder
    {
        return Store::query()
            ->where('agency_id', $this->agencyId)
            ->whereNotIn('id', Lead::query()->where('agency_id', $this->agencyId)->whereNotNull('store_id')->select('store_id'))
            ->with('agencyStore');
    }

    /** Where one lead sits between "referred" and "fully connected". */
    public function stateOf(Lead $lead): string
    {
        if ($lead->store_id === null) {
            return self::AWAITING_STORE;
        }

        $store = $lead->store;

        if ($store === null) {
            return self::STORE_MISSING;
        }

        if ((int) $store->agency_id !== $this->agencyId) {
            return self::MANAGED_ELSEWHERE;
        }

        return $this->relationship($store) === 'ACTIVE' && $store->installation_status === 'INSTALLED'
            ? self::MAPPED
            : self::NEEDS_ACTION;
    }

    /** The store's real authorization relationship with its agency, if any. */
    public function relationship(Store $store): ?string
    {
        return $store->agencyStore?->relationship_status;
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        $counts = [
            'referred' => 0,
            self::MAPPED => 0,
            self::NEEDS_ACTION => 0,
            self::AWAITING_STORE => 0,
            self::MANAGED_ELSEWHERE => 0,
            self::STORE_MISSING => 0,
        ];

        $this->leads()->chunkById(200, function ($leads) use (&$counts) {
            foreach ($leads as $lead) {
                $counts['referred']++;
                $counts[$this->stateOf($lead)]++;
            }
        });

        $counts['direct'] = $this->directStores()->count();

        return $counts;
    }

    public static function label(string $state): string
    {
        return match ($state) {
            self::AWAITING_STORE => 'Awaiting install',
            self::STORE_MISSING => 'Store record missing',
            self::MANAGED_ELSEWHERE => 'Managed by another partner',
            self::NEEDS_ACTION => 'Needs authorization',
            self::MAPPED => 'Connected',
            default => ucfirst($state),
        };
    }

    public static function badge(string $state): string
    {
        return match ($state) {
            self::MAPPED => 'active',
            self::NEEDS_ACTION => 'attention',
            self::STORE_MISSING, self::MANAGED_ELSEWHERE => 'offline',
            default => 'inactive',
        };
    }
}
