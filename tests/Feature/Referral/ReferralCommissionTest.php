<?php

namespace Tests\Feature\Referral;

use App\Models\Organisation;
use App\Models\OrganisationSettings;
use App\Models\Partners\Partner;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use App\Models\User;
use App\Services\Referral\Commission\CommissionSourceMode;
use App\Services\Referral\Commission\ReferralCommissionService;
use App\Services\Referral\Commission\RevenueCollection;
use App\Services\Referral\Commission\RevenueEvent;
use App\Services\Referral\Commission\RevenueSource;
use App\Support\DecimalMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BrixTestSchema;
use Tests\TestCase;

/**
 * Phase 4.2. In-memory sqlite only: the BRIX usage tables live on a faked
 * `cartninja` connection and agencies/stores/app_settings are test
 * scaffolding (see BrixTestSchema). Nothing here touches MySQL.
 */
class ReferralCommissionTest extends TestCase
{
    use BrixTestSchema;

    private const SHOP = 'new-shop.myshopify.com';

    /** @var array<int, Organisation> */
    private array $orgs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-21 12:00:00');
        config()->set('services.shopify.internal_secret', 'test-secret');

        $this->migrateReferralTables();
        $this->createBrixScaffoldTables();
        $this->createTenancyTables();
        $this->createAppSettingsTable();
        $this->fakeCartninjaShops();
        $this->createCartninjaUsageTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function agency(float $rate = 30.0, array $overrides = []): Partner
    {
        static $n = 0;
        $n++;

        $agency = Partner::create(array_merge([
            'name' => "Agency {$n}", 'slug' => "agency-{$n}", 'owner_name' => 'Owner', 'owner_email' => "a{$n}@example.com",
            'commission_enabled' => true, 'commission_type' => 'percentage', 'commission_rate' => $rate,
        ], $overrides));

        $org = Organisation::create(['name' => "Org {$n}"]);
        $org->forceFill(['brix_agency_id' => $agency->id])->save();
        $this->orgs[$agency->id] = $org;

        return $agency;
    }

    private function org(Partner $agency): Organisation
    {
        return $this->orgs[$agency->id];
    }

    /** Set the agency's own commission revenue source (a missing settings row means 'both'). */
    private function setMode(Partner $agency, string $mode): void
    {
        OrganisationSettings::updateOrCreate(['organisation_id' => $this->org($agency)->id], ['commission_revenue_source' => $mode]);
    }

    /** A Phase 3 style referred lead, installed one day ago, with a store owned by the agency. */
    private function lead(Partner $agency, string $shop = self::SHOP, bool $withStore = true): Lead
    {
        $link = TrackingLink::create([
            'agency_id' => $agency->id, 'name' => 'Link', 'channel' => 'Website', 'destination_url' => 'https://apps.shopify.com/thebrix-io',
        ]);

        $store = $withStore ? $this->store($agency, $shop) : null;

        return Lead::create([
            'agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'store_id' => $store?->id, 'shop_domain' => $shop,
            'lead_stage' => Lead::STAGE_INSTALLED, 'brix_status' => 'INSTALLED',
            'first_clicked_at' => now()->subDays(1)->subHour(), 'installed_at' => now()->subDays(1),
        ]);
    }

    private function store(Partner $agency, string $shop = self::SHOP, array $overrides = []): Store
    {
        return Store::create(array_merge([
            'agency_id' => $agency->id, 'shop_domain' => $shop, 'store_name' => "Store {$shop}",
            'installation_status' => 'INSTALLED', 'installed_at' => now()->subDays(1),
        ], $overrides));
    }

    private function accrue(bool $write = true): array
    {
        return app(ReferralCommissionService::class)->accrue($write);
    }

    private function charge(float $amount = 3.00, string $ref = 'gid://shopify/AppUsageRecord/100', string $shop = self::SHOP, $at = null): int
    {
        return $this->seedOrderOverage($shop, $amount, 'charged', $ref, $at ?? now()->subHours(2));
    }

    private function login(Partner $agency, string $role = 'owner'): User
    {
        $user = User::factory()->create();
        $this->org($agency)->users()->attach($user->id, ['role' => $role]);
        $this->actingAs($user)->withSession(['organisation_id' => $this->org($agency)->id]);

        return $user;
    }

    // ============================================================ configuration

    public function test_default_source_is_both(): void
    {
        $agency = $this->agency();

        $this->assertSame('both', CommissionSourceMode::forAgency($agency->id));
        $this->assertSame('both', CommissionSourceMode::forAgency(999999)); // no organisation at all
        $this->assertNull(OrganisationSettings::first()); // no settings row required
    }

    public function test_source_can_be_changed_between_subscription_usage_and_both(): void
    {
        $agency = $this->agency();

        foreach (['subscription', 'usage', 'both'] as $mode) {
            $this->setMode($agency, $mode);
            $this->assertSame($mode, CommissionSourceMode::forAgency($agency->id));
        }

        $this->assertDatabaseCount('organisation_settings', 1); // updated in place, not duplicated
    }

    public function test_the_owner_changes_the_source_from_the_settings_page_and_it_is_per_agency(): void
    {
        $a = $this->agency();
        $b = $this->agency();
        $this->login($a, 'owner');

        $this->get('/settings')->assertOk()->assertSee('Commission revenue source');

        foreach (['subscription', 'usage', 'both'] as $mode) {
            $this->put('/settings/commission-source', ['commission_revenue_source' => $mode])->assertRedirect();
            $this->assertSame($mode, CommissionSourceMode::forAgency($a->id));
        }

        $this->put('/settings/commission-source', ['commission_revenue_source' => 'usage']);
        $this->assertSame('usage', CommissionSourceMode::forAgency($a->id));
        $this->assertSame('both', CommissionSourceMode::forAgency($b->id)); // other agency untouched
        $this->assertDatabaseHas('activity_logs', ['action' => 'COMMISSION_REVENUE_SOURCE_CHANGED', 'agency_id' => $a->id]);
    }

    public function test_invalid_values_and_non_owners_cannot_change_the_source(): void
    {
        $agency = $this->agency();
        $this->login($agency, 'member');

        $this->put('/settings/commission-source', ['commission_revenue_source' => 'usage'])->assertForbidden();
        $this->assertSame('both', CommissionSourceMode::forAgency($agency->id));

        $this->login($agency, 'owner');
        $this->put('/settings/commission-source', ['commission_revenue_source' => 'everything'])->assertSessionHasErrors('commission_revenue_source');
        $this->assertSame('both', CommissionSourceMode::forAgency($agency->id));
    }

    public function test_a_referral_link_can_never_set_the_commission_source(): void
    {
        $agency = $this->agency();
        $this->login($agency);

        $this->post('/referral-links', [
            'name' => 'Sneaky', 'channel' => 'Website', 'commission_revenue_source' => 'usage', 'commission_rate' => 99,
        ])->assertRedirect();

        $this->assertDatabaseCount('tracking_links', 1);
        $this->assertSame('both', CommissionSourceMode::forAgency($agency->id));
        $this->assertFalse(Schema::hasColumn('tracking_links', 'commission_revenue_source'));
    }

    // ============================================================= usage revenue

    public function test_charged_usage_creates_a_revenue_event_and_a_commission(): void
    {
        $agency = $this->agency(30);
        $lead = $this->lead($agency);
        $rowId = $this->charge(3.00, 'gid://shopify/AppUsageRecord/100');

        $summary = $this->accrue();

        $this->assertSame(1, $summary['events_created']);
        $this->assertSame(1, $summary['commissions_created']);
        $this->assertSame('0.90', $summary['commission_total']);

        $event = ReferralRevenueEvent::first();
        $this->assertSame($agency->id, $event->agency_id);
        $this->assertSame(Store::first()->id, $event->store_id);
        $this->assertSame($lead->id, $event->lead_id);
        $this->assertSame(self::SHOP, $event->shop_domain);
        $this->assertSame('usage', $event->revenue_type);
        $this->assertSame('shopify_usage_record', $event->source);
        $this->assertSame('gid://shopify/AppUsageRecord/100', $event->external_event_id);
        $this->assertSame('3.00', $event->revenue_amount);
        $this->assertSame('USD', $event->currency);
        $this->assertSame('verified', $event->status);
        $this->assertSame('unknown', $event->metadata['test_charge']);
        $this->assertSame("order_overage_charges", $event->metadata['source_table']);
        $this->assertSame($rowId, $event->metadata['source_row_id']);

        $commission = ReferralCommission::first();
        $this->assertSame($event->id, $commission->revenue_event_id);
        $this->assertSame('3.00', $commission->revenue_amount);
        $this->assertSame('30.00', $commission->commission_rate);
        $this->assertSame('agency_default', $commission->rate_source);
        $this->assertSame('0.90', $commission->commission_amount);
        $this->assertSame('pending', $commission->status);
        $this->assertTrue($commission->available_at->equalTo($event->occurred_at->copy()->addDays(7)));
        $this->assertSame('both', $commission->rule['revenue_source_mode']);
    }

    public function test_usage_creates_no_commission_when_usage_is_not_enabled_but_the_revenue_is_still_recorded(): void
    {
        $agency = $this->agency();
        $this->setMode($agency, 'subscription');
        $this->lead($agency);
        $this->charge();

        $summary = $this->accrue();

        $this->assertSame(1, $summary['events_created']);
        $this->assertSame(0, $summary['commissions_created']);
        $this->assertSame(1, $summary['not_commissioned']['source_excluded_by_mode']);
        $this->assertDatabaseCount('referral_revenue_events', 1);
        $this->assertDatabaseCount('referral_commissions', 0);
        $this->assertSame('not_commissioned', ReferralRevenueEvent::first()->metadata['commission_decision']);
    }

    public function test_pending_failed_and_unverified_usage_creates_nothing(): void
    {
        $this->lead($this->agency());
        $at = now()->subHours(2);
        $this->seedOrderOverage(self::SHOP, 3.00, 'pending', 'gid://x/1', $at);
        $this->seedOrderOverage(self::SHOP, 3.00, 'failed', 'gid://x/2', $at);
        $this->seedOrderOverage(self::SHOP, 3.00, 'charged', null, $at);
        $this->seedOrderOverage(self::SHOP, 3.00, 'charged', '', $at);
        $this->seedOrderOverage(self::SHOP, 0.00, 'charged', 'gid://x/5', $at);
        $this->seedAiOverage(self::SHOP, 0.10, 1, 'pending', 'gid://x/6', $at);
        $this->seedAiOverage(self::SHOP, 0.10, 2, 'failed', 'gid://x/7', $at);

        $summary = $this->accrue();

        $this->assertSame(0, $summary['events_created']);
        $this->assertDatabaseCount('referral_revenue_events', 0);
        $this->assertDatabaseCount('referral_commissions', 0);
    }

    public function test_ai_credit_overage_is_recorded_as_usage_revenue(): void
    {
        $this->lead($this->agency(10));
        $this->seedAiOverage(self::SHOP, 0.10, 1, 'charged', 'gid://shopify/AppUsageRecord/ai-1', now()->subHour());

        $this->accrue();

        $event = ReferralRevenueEvent::first();
        $this->assertSame('usage', $event->revenue_type);
        $this->assertSame('ai_credit_overage', $event->metadata['usage_kind']);
        $this->assertSame('0.01', ReferralCommission::first()->commission_amount);
    }

    public function test_a_duplicate_usage_record_creates_no_duplicate_revenue_or_commission(): void
    {
        $this->lead($this->agency());
        $this->charge();

        $first = $this->accrue();
        $second = $this->accrue();

        $this->assertSame(1, $first['events_created']);
        $this->assertSame(0, $second['events_created']);
        $this->assertSame(0, $second['commissions_created']);
        $this->assertSame(1, $second['events_existing']);
        $this->assertDatabaseCount('referral_revenue_events', 1);
        $this->assertDatabaseCount('referral_commissions', 1);
    }

    public function test_the_same_usage_record_across_the_order_and_ai_tables_counts_once(): void
    {
        $this->lead($this->agency());
        $this->seedOrderOverage(self::SHOP, 3.00, 'charged', 'gid://shopify/AppUsageRecord/shared', now()->subHours(2));
        $this->seedAiOverage(self::SHOP, 3.00, 1, 'charged', 'gid://shopify/AppUsageRecord/shared', now()->subHours(2));

        $summary = $this->accrue();

        $this->assertSame(1, $summary['events_created']);
        $this->assertSame(1, $summary['events_existing']);
        $this->assertDatabaseCount('referral_revenue_events', 1);
        $this->assertDatabaseCount('referral_commissions', 1);
    }

    public function test_the_database_itself_refuses_a_duplicate_event_or_commission(): void
    {
        $this->lead($this->agency());
        $this->charge();
        $this->accrue();

        $event = ReferralRevenueEvent::first();

        try {
            ReferralRevenueEvent::create($event->only(['agency_id', 'store_id', 'shop_domain', 'revenue_type', 'source', 'external_event_id', 'revenue_amount', 'currency', 'occurred_at']));
            $this->fail('Duplicate revenue event was accepted.');
        } catch (UniqueConstraintViolationException) {
            $this->assertDatabaseCount('referral_revenue_events', 1);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        ReferralCommission::create(ReferralCommission::first()->only([
            'revenue_event_id', 'agency_id', 'store_id', 'revenue_type', 'revenue_amount', 'currency', 'commission_rate', 'rate_source', 'commission_amount',
        ]));
    }

    public function test_dry_run_writes_nothing_but_reports_what_would_happen(): void
    {
        $this->lead($this->agency());
        $this->charge();

        $summary = $this->accrue(false);

        $this->assertTrue($summary['dry_run']);
        $this->assertSame(1, $summary['events_created']);
        $this->assertSame('0.90', $summary['commission_total']);
        $this->assertDatabaseCount('referral_revenue_events', 0);
        $this->assertDatabaseCount('referral_commissions', 0);
    }

    // ============================================================== subscription

    public function test_unavailable_recurring_billing_creates_no_fake_subscription_revenue(): void
    {
        $agency = $this->agency();
        $this->setMode($agency, 'subscription');
        $this->lead($agency);
        // Plan/subscription labels and even a paid-looking store must not become revenue.
        Store::first()->update(['plan' => 'Pro']);
        $this->charge(); // usage only

        $summary = $this->accrue();

        $this->assertSame('recurring_revenue_not_verifiable', $summary['sources']['subscription']);
        $this->assertSame(0, ReferralRevenueEvent::where('revenue_type', 'subscription')->count());
        $this->assertSame(0, ReferralCommission::where('revenue_type', 'subscription')->count());
    }

    public function test_subscription_revenue_is_architecturally_supported_by_the_same_pipeline(): void
    {
        $agency = $this->agency(20);
        $this->lead($agency);
        $subscription = $this->subscriptionSource('sub-charge-1', '29.00');

        (new ReferralCommissionService([$subscription]))->accrue(true);

        $event = ReferralRevenueEvent::first();
        $this->assertSame('subscription', $event->revenue_type);
        $this->assertSame('shopify_subscription', $event->source);
        $this->assertSame('5.80', ReferralCommission::first()->commission_amount);

        // With the source set to usage the subscription revenue is still
        // tracked, but earns no new commission.
        $this->setMode($agency, 'usage');
        (new ReferralCommissionService([$this->subscriptionSource('sub-charge-2', '29.00')]))->accrue(true);

        $this->assertSame(2, ReferralRevenueEvent::where('revenue_type', 'subscription')->count());
        $this->assertSame(1, ReferralCommission::where('revenue_type', 'subscription')->count());
    }

    public function test_no_fabricated_subscription_commission_from_the_default_sources(): void
    {
        $this->lead($this->agency());
        Store::first()->update(['plan' => 'Pro', 'status' => 'active']);
        DbShop::seedPaidLooking();

        $this->accrue();

        $this->assertDatabaseCount('referral_revenue_events', 0);
        $this->assertDatabaseCount('referral_commissions', 0);
    }

    private function subscriptionSource(string $id, string $amount): RevenueSource
    {
        return new class($id, $amount) implements RevenueSource
        {
            public function __construct(private string $id, private string $amount) {}

            public function key(): string
            {
                return 'subscription';
            }

            public function collect(Collection $leads): RevenueCollection
            {
                return RevenueCollection::verified([new RevenueEvent(
                    revenueType: 'subscription', source: 'shopify_subscription', externalEventId: $this->id,
                    shopDomain: 'new-shop.myshopify.com', amount: $this->amount, currency: 'USD', occurredAt: now()->subHour(),
                )]);
            }
        };
    }

    // ==================================================================== rate

    public function test_commission_uses_the_configured_agency_rate(): void
    {
        $this->lead($this->agency(25));
        $this->charge(10.00);

        $this->accrue();

        $commission = ReferralCommission::first();
        $this->assertSame('25.00', $commission->commission_rate);
        $this->assertSame('2.50', $commission->commission_amount);
        $this->assertSame('agency_default', $commission->rate_source);
    }

    public function test_the_existing_store_override_hierarchy_is_reused_and_never_invented(): void
    {
        $agency = $this->agency(30);
        $lead = $this->lead($agency);
        $this->charge(10.00, 'gid://x/default');
        $this->accrue();

        Store::first()->update(['commission_override_enabled' => true, 'commission_override_rate' => 20]);
        $this->charge(10.00, 'gid://x/override');
        $this->accrue();

        $rows = ReferralCommission::orderBy('id')->get();
        $this->assertSame(['agency_default', 'store_override'], $rows->pluck('rate_source')->all());
        $this->assertSame(['30.00', '20.00'], $rows->pluck('commission_rate')->all());
        $this->assertSame(['3.00', '2.00'], $rows->pluck('commission_amount')->all());
        $this->assertSame('store_override > agency_default', $rows[1]->rule['rate_hierarchy']);
        $this->assertNotNull($lead);
    }

    public function test_a_historical_commission_keeps_its_original_rate_after_the_rate_changes(): void
    {
        $agency = $this->agency(30);
        $this->lead($agency);
        $this->charge(100.00, 'gid://x/june');
        $this->accrue();

        $agency->update(['commission_rate' => 20]);
        $this->charge(100.00, 'gid://x/july');
        $this->accrue();

        $rows = ReferralCommission::orderBy('id')->get();
        $this->assertSame(['30.00', '20.00'], $rows->pluck('commission_rate')->all());
        $this->assertSame(['30.00', '20.00'], $rows->pluck('commission_amount')->all());
        $this->assertSame('30.00', $rows[0]->fresh()->commission_amount); // June never rewritten
    }

    public function test_commission_is_calculated_with_decimal_safe_integer_math(): void
    {
        $this->assertSame(29, DecimalMoney::toCents('0.29')); // 0.29 * 100 is 28.999... in floats
        $this->assertSame(1005, DecimalMoney::toCents('10.05'));
        $this->assertSame(350, DecimalMoney::toCents(3.5));
        $this->assertSame(335, DecimalMoney::percentOf(1005, DecimalMoney::percentToHundredths('33.33'))); // 3.349665 -> 3.35
        $this->assertSame(4, DecimalMoney::percentOf(7, DecimalMoney::percentToHundredths('50')));       // 0.035 -> 0.04 half up
        $this->assertSame('3.35', DecimalMoney::format(335));
        $this->assertSame('-0.04', DecimalMoney::format(-4));

        $this->lead($this->agency(33.33));
        $this->charge(10.05);
        $this->accrue();

        $this->assertSame('3.35', ReferralCommission::first()->commission_amount);
    }

    public function test_agencies_that_cannot_earn_commission_record_revenue_but_no_commission(): void
    {
        $this->lead($this->agency(30, ['commission_enabled' => false]), 'a.myshopify.com');
        $this->lead($this->agency(30, ['commission_type' => 'fixed']), 'b.myshopify.com');
        $this->lead($this->agency(0), 'c.myshopify.com');
        foreach (['a', 'b', 'c'] as $i => $prefix) {
            $this->seedOrderOverage("{$prefix}.myshopify.com", 10.00, 'charged', "gid://x/{$i}", now()->subHour());
        }

        $summary = $this->accrue();

        $this->assertSame(3, $summary['events_created']);
        $this->assertSame(0, $summary['commissions_created']);
        $this->assertSame(1, $summary['not_commissioned']['commission_disabled']);
        $this->assertSame(1, $summary['not_commissioned']['unsupported_commission_type']);
        $this->assertSame(1, $summary['not_commissioned']['zero_commission']);
    }

    // =========================================================== attribution

    public function test_a_referred_new_store_generates_commission_end_to_end(): void
    {
        $agency = $this->agency(30);
        $link = TrackingLink::create(['agency_id' => $agency->id, 'name' => 'L', 'channel' => 'Website', 'destination_url' => 'https://apps.shopify.com/thebrix-io']);
        ReferralClick::create([
            'agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'referral_code' => $link->code,
            'session_id' => 's', 'shop_domain' => self::SHOP, 'created_at' => now()->subMinutes(30),
        ]);
        $this->seedCartninjaShop(self::SHOP, now()->subMinutes(5)); // BRIX first saw it after the click

        $this->postJson('/internal/shopify/agency/store-installed', ['shop_domain' => self::SHOP], ['X-Internal-Secret' => 'test-secret'])->assertOk();
        $this->assertDatabaseCount('leads', 1);

        Carbon::setTestNow(now()->addHour());
        $this->charge(3.00, 'gid://shopify/AppUsageRecord/e2e', self::SHOP, now()->subMinutes(10));
        $this->accrue();

        $this->assertDatabaseCount('referral_commissions', 1);
        $this->assertSame($agency->id, ReferralCommission::first()->agency_id);
    }

    public function test_an_existing_brix_customer_who_clicks_a_referral_link_can_never_generate_commission(): void
    {
        $agency = $this->agency(30);
        $link = TrackingLink::create(['agency_id' => $agency->id, 'name' => 'L', 'channel' => 'Website', 'destination_url' => 'https://apps.shopify.com/thebrix-io']);
        ReferralClick::create([
            'agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'referral_code' => $link->code,
            'session_id' => 's', 'shop_domain' => self::SHOP, 'created_at' => now()->subMinutes(30),
        ]);
        $this->seedCartninjaShop(self::SHOP, now()->subDays(90)); // long-standing BRIX customer

        $this->postJson('/internal/shopify/agency/store-installed', ['shop_domain' => self::SHOP], ['X-Internal-Secret' => 'test-secret'])->assertOk();
        $this->assertDatabaseCount('leads', 0); // Phase 3 protection

        $this->charge(50.00, 'gid://x/existing', self::SHOP, now()->subMinutes(10));
        $summary = $this->accrue();

        $this->assertSame(0, $summary['events_created']);
        $this->assertDatabaseCount('referral_revenue_events', 0);
        $this->assertDatabaseCount('referral_commissions', 0);
    }

    public function test_a_click_a_store_or_a_plan_alone_is_never_enough(): void
    {
        $agency = $this->agency();
        $link = TrackingLink::create(['agency_id' => $agency->id, 'name' => 'L', 'channel' => 'Website', 'destination_url' => 'x']);
        ReferralClick::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'referral_code' => $link->code, 'session_id' => 's', 'shop_domain' => self::SHOP]);
        $this->store($agency, self::SHOP, ['plan' => 'Pro']);
        $this->charge();

        $this->accrue();

        $this->assertDatabaseCount('referral_revenue_events', 0);
        $this->assertDatabaseCount('referral_commissions', 0);
    }

    public function test_a_store_owned_by_a_different_agency_never_earns_the_lead_agency_commission(): void
    {
        $leadAgency = $this->agency(30);
        $otherAgency = $this->agency(30);
        $this->lead($leadAgency, self::SHOP, false);
        $this->store($otherAgency);
        $this->charge();

        $summary = $this->accrue();

        $this->assertSame(0, $summary['events_created']);
        $this->assertSame(1, $summary['skipped']['store_owned_by_other_agency']);
        $this->assertDatabaseCount('referral_commissions', 0);
    }

    public function test_a_lead_without_a_linked_store_earns_nothing_until_the_store_exists(): void
    {
        $agency = $this->agency();
        $this->lead($agency, self::SHOP, false);
        $this->charge();

        $this->assertSame(1, $this->accrue()['skipped']['store_not_linked']);
        $this->assertDatabaseCount('referral_commissions', 0);

        $this->store($agency);
        $this->accrue();
        $this->assertDatabaseCount('referral_commissions', 1);
    }

    public function test_a_lead_of_another_agency_generates_commission_only_for_its_own_agency(): void
    {
        $a = $this->agency(10);
        $b = $this->agency(40);
        $this->lead($a, 'a-shop.myshopify.com');
        $this->lead($b, 'b-shop.myshopify.com');
        $this->seedOrderOverage('b-shop.myshopify.com', 10.00, 'charged', 'gid://x/b', now()->subHour());

        $this->accrue();

        $this->assertSame(0, ReferralCommission::where('agency_id', $a->id)->count());
        $this->assertSame('4.00', ReferralCommission::where('agency_id', $b->id)->value('commission_amount'));
    }

    public function test_revenue_from_shops_with_no_referred_lead_and_before_the_install_is_ignored(): void
    {
        $agency = $this->agency();
        $this->lead($agency);
        $this->seedOrderOverage('existing-customer.myshopify.com', 50.00, 'charged', 'gid://x/existing', now()->subHour());
        $this->seedOrderOverage(self::SHOP, 3.00, 'charged', 'gid://x/old', now()->subDays(3)); // before the referred install

        $summary = $this->accrue();

        $this->assertSame(0, $summary['events_created']);
        $this->assertSame(1, $summary['skipped']['before_referral_install']);
    }

    // ============================================================== isolation

    public function test_an_agency_cannot_read_another_agencys_revenue_or_commissions(): void
    {
        $a = $this->agency(10);
        $b = $this->agency(40);
        $leadA = $this->lead($a, 'a-shop.myshopify.com');
        $leadB = $this->lead($b, 'b-shop.myshopify.com');
        $this->seedOrderOverage('a-shop.myshopify.com', 10.00, 'charged', 'gid://x/a', now()->subHour());
        $this->seedOrderOverage('b-shop.myshopify.com', 20.00, 'charged', 'gid://x/b', now()->subHour());
        $this->accrue();

        // Scoped relations.
        $this->assertSame(['10.00'], $this->org($a)->referralRevenueEvents()->get()->pluck('revenue_amount')->all());
        $this->assertSame(['1.00'], $this->org($a)->referralCommissions()->get()->pluck('commission_amount')->all());
        $this->assertSame(['20.00'], $this->org($b)->referralRevenueEvents()->get()->pluck('revenue_amount')->all());

        // Pages, logged in as agency A.
        $this->login($a);
        $this->get("/referral-links/{$leadB->tracking_link_id}/leads")->assertForbidden();
        $this->get("/referral-links/{$leadA->tracking_link_id}/leads")
            ->assertOk()->assertSee('Store a-shop.myshopify.com')->assertSee('$10.00')->assertSee('$1.00')
            ->assertDontSee('b-shop.myshopify.com')->assertDontSee('$20.00')->assertDontSee('$8.00');
        $this->get('/referral-links')->assertOk()->assertSee('$10.00')->assertDontSee('$20.00')->assertDontSee('$8.00');
    }

    // ================================================== configuration changes

    public function test_switching_both_to_usage_keeps_history_and_still_commissions_usage(): void
    {
        $agency = $this->agency();
        $this->lead($agency);
        $this->charge(3.00, 'gid://x/1');
        $this->accrue();
        $before = [ReferralRevenueEvent::count(), ReferralCommission::count(), ReferralCommission::first()->commission_amount];

        $this->setMode($agency, 'usage');
        $this->charge(3.00, 'gid://x/2');
        $this->accrue();

        $this->assertSame([1, 1, '0.90'], $before);
        $this->assertSame(2, ReferralRevenueEvent::count());
        $this->assertSame(2, ReferralCommission::count());
        $this->assertSame(['both', 'usage'], ReferralCommission::orderBy('id')->get()->pluck('rule')->map(fn ($r) => $r['revenue_source_mode'])->all());
    }

    public function test_switching_usage_to_subscription_keeps_history_and_stops_new_usage_commission(): void
    {
        $agency = $this->agency();
        $this->setMode($agency, 'usage');
        $this->lead($agency);
        $this->charge(3.00, 'gid://x/1');
        $this->accrue();

        $this->setMode($agency, 'subscription');
        $this->charge(3.00, 'gid://x/2');
        $this->accrue();

        $this->assertSame(2, ReferralRevenueEvent::count()); // both tracked
        $this->assertSame(1, ReferralCommission::count()); // history kept, no new usage commission
        $this->assertSame('0.90', ReferralCommission::first()->commission_amount);
        $this->assertSame('source_excluded_by_mode', ReferralRevenueEvent::orderBy('id')->get()[1]->metadata['commission_skip_reason']);
    }

    public function test_switching_subscription_to_both_does_not_duplicate_or_retrofit_old_events(): void
    {
        $agency = $this->agency();
        $this->setMode($agency, 'subscription');
        $this->lead($agency);
        $this->charge(3.00, 'gid://x/1');
        $this->accrue(); // tracked, not commissioned

        $this->setMode($agency, 'both');
        $summary = $this->accrue();

        $this->assertSame(0, $summary['events_created']);
        $this->assertSame(1, $summary['events_existing']);
        $this->assertSame(1, ReferralRevenueEvent::count());
        $this->assertSame(0, ReferralCommission::count()); // not retroactively commissioned

        $this->charge(3.00, 'gid://x/2'); // new revenue under 'both'
        $this->accrue();

        $this->assertSame(2, ReferralRevenueEvent::count());
        $this->assertSame(1, ReferralCommission::count());
    }

    public function test_an_invalid_stored_source_fails_closed_but_still_records_the_revenue(): void
    {
        $agency = $this->agency();
        $this->setMode($agency, 'everything');
        $this->lead($agency);
        $this->charge();

        $summary = $this->accrue();

        $this->assertSame(1, $summary['not_commissioned']['invalid_source_mode']);
        $this->assertDatabaseCount('referral_revenue_events', 1);
        $this->assertDatabaseCount('referral_commissions', 0);
    }

    // ============================================================== boundaries

    public function test_an_unreachable_billing_source_fails_closed_without_throwing(): void
    {
        $this->lead($this->agency());
        config()->set('database.connections.cartninja.database', '/nonexistent/dir/none.sqlite');
        \DB::purge('cartninja');

        $summary = $this->accrue();

        $this->assertSame('billing_source_unavailable', $summary['sources']['usage']);
        $this->assertSame(0, $summary['events_created']);
    }

    public function test_legacy_tables_and_payouts_are_never_touched(): void
    {
        $this->lead($this->agency());
        $this->charge();

        $this->accrue();

        // None exist in this test database: any read or write would have failed.
        $this->assertFalse(Schema::hasTable('transactions'));
        $this->assertFalse(Schema::hasTable('payouts'));
        $this->assertFalse(Schema::hasTable('transaction_payout'));
        $this->assertSame('pending', ReferralCommission::first()->status); // and never paid
    }

    public function test_the_artisan_command_is_a_dry_run_unless_write_is_passed(): void
    {
        $this->lead($this->agency());
        $this->charge();

        $this->artisan('referrals:accrue-commissions')
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('would create commissions: 1')
            ->assertSuccessful();
        $this->assertDatabaseCount('referral_revenue_events', 0);

        $this->artisan('referrals:accrue-commissions', ['--write' => true])->assertSuccessful();
        $this->assertDatabaseCount('referral_revenue_events', 1);
        $this->assertDatabaseCount('referral_commissions', 1);
    }

    public function test_referral_links_page_shows_ledger_values_and_the_subscription_note(): void
    {
        $agency = $this->agency(30);
        $this->lead($agency);
        $this->charge(30.00);
        $this->accrue();
        $this->login($agency);

        // No `transactions` table exists here, so any legacy query would error.
        $this->get('/referral-links')->assertOk()->assertSee('$30.00')->assertSee('$9.00')
            ->assertSee('Recurring subscription revenue is not yet verifiable');

        $this->setMode($agency, 'usage');
        $this->get('/referral-links')->assertOk()->assertDontSee('Recurring subscription revenue is not yet verifiable');
    }
}

/** Seeds a shops row that merely *looks* paid, to prove it is never turned into revenue. */
final class DbShop
{
    public static function seedPaidLooking(): void
    {
        \DB::connection('cartninja')->table('shops')->insert(['shop_domain' => 'new-shop.myshopify.com', 'plan_key' => 'pro', 'created_at' => '2026-09-01 00:00:00']);
    }
}
