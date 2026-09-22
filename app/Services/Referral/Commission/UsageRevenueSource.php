<?php

namespace App\Services\Referral\Commission;

use App\Models\Referral\Lead;
use App\Models\Referral\ReferralRevenueEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Usage/overage revenue, read-only from the BRIX Shopify app's own
 * database through the existing `cartninja` connection.
 *
 * A row counts only when it is `charged` AND carries a
 * `shopify_usage_record_id` — i.e. Shopify accepted an appUsageRecordCreate
 * for it. pending/failed rows and rows without a usage record id are never
 * revenue. The Shopify usage record GID is the event's external id (the idempotency key).
 *
 * Not knowable from this data: whether the charge belonged to a Shopify
 * *test* subscription (the flag is not persisted by BRIX), and the
 * currency per row (BRIX hardcodes USD when creating usage records).
 * Both are recorded in each event's metadata rather than assumed away.
 */
class UsageRevenueSource implements RevenueSource
{
    private const TABLES = [
        'order_overage' => [
            'table' => 'order_overage_charges',
            'columns' => ['id', 'shop_domain', 'date', 'plan_key', 'overage_orders', 'charge_amount', 'shopify_usage_record_id', 'created_at', 'updated_at'],
        ],
        'ai_credit_overage' => [
            'table' => 'ai_brix_overage_charges',
            'columns' => ['id', 'shop_domain', 'period_key', 'credit_number', 'plan_key', 'charge_amount', 'shopify_usage_record_id', 'created_at', 'updated_at'],
        ],
    ];

    public function key(): string
    {
        return ReferralRevenueEvent::TYPE_USAGE;
    }

    public function collect(Collection $leads): RevenueCollection
    {
        $shops = $leads->pluck('shop_domain')->filter()->map(fn ($s) => strtolower($s))->unique()->values();

        if ($shops->isEmpty()) {
            return RevenueCollection::verified([]);
        }

        try {
            $events = [];

            foreach (self::TABLES as $type => $definition) {
                foreach ($shops->chunk(500) as $chunk) {
                    $rows = DB::connection('cartninja')
                        ->table($definition['table'])
                        ->select($definition['columns'])
                        ->whereIn('shop_domain', $chunk->all())
                        ->where('status', 'charged')
                        ->whereNotNull('shopify_usage_record_id')
                        ->where('shopify_usage_record_id', '!=', '')
                        ->where('charge_amount', '>', 0)
                        ->get();

                    foreach ($rows as $row) {
                        $events[] = $this->toEvent($type, $definition['table'], $row);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return RevenueCollection::unverifiable('billing_source_unavailable');
        }

        return RevenueCollection::verified($events);
    }

    private function toEvent(string $type, string $table, object $row): RevenueEvent
    {
        $timezone = config('app.timezone');
        $chargedAt = Carbon::parse($row->updated_at ?? $row->created_at, $timezone);

        $metadata = [
            'usage_kind' => $type,
            'source_table' => $table,
            'source_row_id' => (int) $row->id,
            'verification' => 'SHOPIFY_USAGE_RECORD',
            // Not persisted by BRIX, so genuinely unknown — never assumed.
            'test_charge' => 'unknown',
            'currency_basis' => 'main_app_hardcoded_usd',
            'plan_key' => $row->plan_key,
            'source_created_at' => $row->created_at,
        ];

        if ($type === 'order_overage') {
            $metadata += ['usage_date' => (string) $row->date, 'overage_orders' => (int) $row->overage_orders];
        } else {
            $metadata += ['period_key' => $row->period_key, 'credit_number' => (int) $row->credit_number];
        }

        return new RevenueEvent(
            revenueType: ReferralRevenueEvent::TYPE_USAGE,
            source: 'shopify_usage_record',
            externalEventId: (string) $row->shopify_usage_record_id,
            shopDomain: strtolower($row->shop_domain),
            amount: (string) $row->charge_amount,
            currency: ReferralRevenueEvent::USAGE_CURRENCY,
            occurredAt: $chargedAt,
            metadata: $metadata,
        );
    }
}
