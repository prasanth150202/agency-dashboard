<?php

namespace App\Services\Referral\Commission;

use App\Models\Referral\ReferralRevenueEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Recurring subscription revenue, read-only from Shopify's own Partner API
 * (config('services.shopify_partner') — a separate org-level credential
 * from the per-shop 'shopify' config, needs the Partner API client's "View
 * financials" permission). Unlike cartninja's shops.plan_key, this is
 * Shopify's own record of what BRIX was actually paid: AppSubscriptionSale
 * transactions' netAmount, already net of Shopify's revenue-share fee.
 *
 * A reversal/credit isn't a separate lookup — Shopify reports it as its
 * own transaction in the same feed, with a negative netAmount. This never
 * derives an amount from a plan label; if the Partner API can't be read,
 * this reports "unverifiable" and creates nothing, same as before.
 */
class SubscriptionRevenueSource implements RevenueSource
{
    /** The `types:` filter argument's enum value — NOT the same spelling as the GraphQL object type name below. */
    private const TRANSACTION_TYPE_FILTER = 'APP_SUBSCRIPTION_SALE';

    /** __typename on the returned node — GraphQL's PascalCase object type name, distinct from the enum above. */
    private const TRANSACTION_TYPENAME = 'AppSubscriptionSale';

    /** Hard stop on pagination so a misbehaving API can't loop forever. */
    private const MAX_PAGES = 40;

    private const PAGE_SIZE = 100;

    public function key(): string
    {
        return ReferralRevenueEvent::TYPE_SUBSCRIPTION;
    }

    public function collect(Collection $leads): RevenueCollection
    {
        $config = config('services.shopify_partner');

        if (blank($config['api_token']) || blank($config['org_id']) || blank($config['app_id'])) {
            return RevenueCollection::unverifiable('partner_api_not_configured');
        }

        $shops = $leads->pluck('shop_domain')->filter()->map(fn ($s) => strtolower($s))->unique()->flip();

        if ($shops->isEmpty()) {
            return RevenueCollection::verified([]);
        }

        try {
            $events = [];
            $cursor = null;
            $page = 0;

            do {
                $page++;
                $response = $this->fetchPage($config, $cursor);

                if ($response === null) {
                    return RevenueCollection::unverifiable('billing_source_unavailable');
                }

                $lastCursor = null;

                foreach ($response['edges'] as $edge) {
                    $lastCursor = $edge['cursor'];
                    $node = $edge['node'];

                    if (($node['__typename'] ?? null) !== self::TRANSACTION_TYPENAME) {
                        continue;
                    }

                    $shopDomain = strtolower((string) ($node['shop']['myshopifyDomain'] ?? ''));

                    if ($shopDomain === '' || ! $shops->has($shopDomain)) {
                        continue;
                    }

                    $events[] = $this->toEvent($node, $shopDomain);
                }

                // Relay-style connection: PageInfo here carries no
                // start/endCursor, so the next page's `after` comes from
                // the last edge's own cursor instead.
                $cursor = ($response['pageInfo']['hasNextPage'] && $lastCursor !== null) ? $lastCursor : null;
            } while ($cursor !== null && $page < self::MAX_PAGES);
        } catch (\Throwable $e) {
            report($e);

            return RevenueCollection::unverifiable('billing_source_unavailable');
        }

        return RevenueCollection::verified($events);
    }

    /** @return array{edges: list<array{cursor: string, node: array}>, pageInfo: array{hasNextPage: bool}}|null */
    private function fetchPage(array $config, ?string $cursor): ?array
    {
        $endpoint = "https://partners.shopify.com/{$config['org_id']}/api/{$config['api_version']}/graphql.json";

        $query = <<<'GQL'
            query($appId: ID!, $createdAtMin: DateTime!, $first: Int!, $after: String) {
                transactions(appId: $appId, createdAtMin: $createdAtMin, types: [__TYPE_FILTER__], first: $first, after: $after) {
                    edges {
                        cursor
                        node {
                            __typename
                            ... on AppSubscriptionSale {
                                id
                                createdAt
                                chargeId
                                billingInterval
                                netAmount { amount currencyCode }
                                grossAmount { amount currencyCode }
                                shopifyFee { amount currencyCode }
                                shop { myshopifyDomain }
                            }
                        }
                    }
                    pageInfo { hasNextPage }
                }
            }
            GQL;
        $query = str_replace('__TYPE_FILTER__', self::TRANSACTION_TYPE_FILTER, $query);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $config['api_token'],
            'Content-Type' => 'application/json',
        ])
            ->connectTimeout(5)
            ->timeout(15)
            ->post($endpoint, [
                'query' => $query,
                'variables' => [
                    'appId' => "gid://partners/App/{$config['app_id']}",
                    'createdAtMin' => now()->subDays($config['lookback_days'])->toIso8601String(),
                    'first' => self::PAGE_SIZE,
                    'after' => $cursor,
                ],
            ]);

        if (! $response->successful() || isset($response->json()['errors'])) {
            report(new \RuntimeException('Shopify Partner API transactions query failed: '.$response->body()));

            return null;
        }

        return $response->json('data.transactions');
    }

    private function toEvent(array $node, string $shopDomain): RevenueEvent
    {
        return new RevenueEvent(
            revenueType: ReferralRevenueEvent::TYPE_SUBSCRIPTION,
            source: 'shopify_partner_api',
            externalEventId: (string) $node['id'],
            shopDomain: $shopDomain,
            amount: (string) $node['netAmount']['amount'],
            currency: (string) $node['netAmount']['currencyCode'],
            occurredAt: Carbon::parse($node['createdAt']),
            metadata: [
                'verification' => 'SHOPIFY_PARTNER_API',
                'gross_amount' => $node['grossAmount']['amount'] ?? null,
                'shopify_fee' => $node['shopifyFee']['amount'] ?? null,
                // Not resolved to a reversal_of_id here — a negative
                // netAmount is itself Shopify's own reversal/credit
                // record, but linking it back to the original sale would
                // need matching on charge_id, which isn't attempted yet.
                'charge_id' => $node['chargeId'] ?? null,
                'billing_interval' => $node['billingInterval'] ?? null,
            ],
        );
    }
}
