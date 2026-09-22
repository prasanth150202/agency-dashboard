<?php

namespace Tests\Feature\Referral;

use App\Models\Organisation;
use App\Models\Partners\AgencyStore;
use App\Models\Partners\AgencyStoreOnboarding;
use App\Models\Partners\Partner;
use App\Models\Referral\Lead;
use App\Models\Referral\LeadEvent;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use App\Models\User;
use App\Services\Referral\ReferralAttribution;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Support\BrixTestSchema;
use Tests\TestCase;

/**
 * Phase 3: referral click -> temporary attribution -> install callback ->
 * lead -> activation. Runs against per-test in-memory sqlite only (see
 * BrixTestSchema); nothing here touches MySQL or any real database.
 */
class ReferralAttributionTest extends TestCase
{
    use BrixTestSchema;

    private const SECRET = 'test-secret';

    private const SHOP = 'new-shop.myshopify.com';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-21 12:00:00');
        config()->set('services.shopify.internal_secret', self::SECRET);

        $this->migrateReferralTables();
        $this->createBrixScaffoldTables();
        $this->fakeCartninjaShops();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function makeLink(int $agencyId = 1, array $overrides = []): TrackingLink
    {
        return TrackingLink::create(array_merge([
            'agency_id' => $agencyId,
            'name' => "Link for agency {$agencyId}",
            'channel' => 'Instagram',
            'destination_url' => 'https://apps.shopify.com/thebrix-io',
        ], $overrides));
    }

    /** A click already bound to a shop, made 10 minutes ago. */
    private function boundClick(TrackingLink $link, string $shop = self::SHOP, ?Carbon $at = null): ReferralClick
    {
        return ReferralClick::create([
            'agency_id' => $link->agency_id,
            'tracking_link_id' => $link->id,
            'referral_code' => $link->code,
            'session_id' => 'sess-'.$link->id,
            'shop_domain' => $shop,
            'created_at' => $at ?? now()->subMinutes(10),
        ]);
    }

    /** BRIX first saw this shop 2 minutes ago, i.e. after the click. */
    private function newToBrix(string $shop = self::SHOP): void
    {
        $this->seedCartninjaShop($shop, now()->subMinutes(2));
    }

    private function hook(string $path, string $shop = self::SHOP)
    {
        return $this->postJson("/internal/shopify/agency/{$path}", ['shop_domain' => $shop], ['X-Internal-Secret' => self::SECRET]);
    }

    private function install(string $shop = self::SHOP)
    {
        return $this->hook('store-installed', $shop);
    }

    private function eventTypes(Lead $lead): array
    {
        return $lead->events()->orderBy('id')->pluck('event_type')->all();
    }

    private function createStore(int $agencyId, string $shop = self::SHOP, array $overrides = []): Store
    {
        return Store::create(array_merge([
            'agency_id' => $agencyId,
            'shop_domain' => $shop,
            'store_name' => 'Existing Store',
            'status' => 'active',
            'installation_status' => 'INSTALLED',
            'authorization_status' => 'AUTHORIZED',
            'installed_at' => now()->subDays(90),
        ], $overrides));
    }

    // -------------------------------------------------- 1-2 temporary attribution

    public function test_referral_click_establishes_temporary_attribution(): void
    {
        $link = $this->makeLink(1);

        $response = $this->get('/ref/'.$link->code, ['referer' => 'https://instagram.com']);

        $response->assertRedirectContains('/referral/store/');
        $this->assertStringContainsString('signature=', $response->headers->get('Location'));

        $click = ReferralClick::first();
        $this->assertSame($link->id, $click->tracking_link_id);
        $this->assertSame($link->code, $click->referral_code);
        $this->assertSame(1, $click->agency_id);
        $this->assertNull($click->shop_domain);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_attribution_survives_the_redirect_flow_and_binds_the_shop(): void
    {
        $link = $this->makeLink(1);

        $location = $this->get('/ref/'.$link->code)->headers->get('Location');

        $this->get($location)->assertOk()->assertSee('Shopify store domain');

        $this->post($location, ['shop_domain' => 'https://New-Shop.myshopify.com/'])
            ->assertRedirect('https://apps.shopify.com/thebrix-io');

        $this->assertSame('new-shop.myshopify.com', ReferralClick::first()->shop_domain);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_store_step_rejects_tampered_expired_and_invalid_input(): void
    {
        $link = $this->makeLink(1);
        $location = $this->get('/ref/'.$link->code)->headers->get('Location');

        $this->get(preg_replace('/signature=\w+/', 'signature=forged', $location))->assertForbidden();

        $this->post($location, ['shop_domain' => 'not-a-shop.example.com'])->assertSessionHasErrors('shop_domain');
        $this->assertNull(ReferralClick::first()->shop_domain);

        Carbon::setTestNow(now()->addHours(2));
        $this->get($location)->assertForbidden();
    }

    public function test_click_bound_to_one_shop_cannot_be_rebound_to_another(): void
    {
        $click = $this->boundClick($this->makeLink(1));

        $this->assertFalse(ReferralAttribution::bindShop($click, 'other.myshopify.com'));
        $this->assertSame(self::SHOP, $click->fresh()->shop_domain);
    }

    // ------------------------------------------------ 3-7 new install -> lead

    public function test_new_install_with_valid_referral_creates_exactly_one_lead(): void
    {
        $link = $this->makeLink(7);
        $click = $this->boundClick($link);
        $this->newToBrix();

        $this->install()->assertOk();

        $this->assertDatabaseCount('leads', 1);
        $lead = Lead::first();
        $this->assertSame(7, $lead->agency_id);
        $this->assertSame($link->id, $lead->tracking_link_id);
        $this->assertSame(self::SHOP, $lead->shop_domain);
        $this->assertSame(Lead::STAGE_INSTALLED, $lead->lead_stage);
        $this->assertSame('INSTALLED', $lead->brix_status);
        $this->assertEquals($click->created_at, $lead->first_clicked_at);
        $this->assertNotNull($lead->installed_at);
        $this->assertNull($lead->activated_at);
        $this->assertSame([LeadEvent::CLICKED, LeadEvent::INSTALLED], $this->eventTypes($lead));
    }

    public function test_new_referred_install_is_recorded_through_the_existing_store_mirror_once(): void
    {
        $this->boundClick($this->makeLink(7));
        $this->newToBrix();

        $this->install();
        $this->install(); // callback re-fires on reinstall/retry

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('stores', 1);
        $this->assertDatabaseCount('agency_stores', 1);

        $store = Store::first();
        $this->assertSame(7, $store->agency_id);
        $this->assertSame('INSTALLED', $store->installation_status);
        $this->assertSame('PENDING', AgencyStore::first()->relationship_status);
        $this->assertSame($store->id, Lead::first()->store_id);
    }

    public function test_install_through_the_same_agencys_onboarding_links_the_created_store(): void
    {
        $this->boundClick($this->makeLink(7));
        $this->newToBrix();
        AgencyStoreOnboarding::create([
            'agency_id' => 7, 'state_token' => str_repeat('a', 64), 'shop_domain' => self::SHOP,
            'status' => 'STARTED', 'expires_at' => now()->addMinutes(15),
        ]);

        $this->install();

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('stores', 1);
        $this->assertSame(Store::first()->id, Lead::first()->store_id);
    }

    public function test_another_agencys_pending_onboarding_wins_over_a_referral(): void
    {
        $this->boundClick($this->makeLink(7));
        $this->newToBrix();
        AgencyStoreOnboarding::create([
            'agency_id' => 9, 'state_token' => str_repeat('b', 64), 'shop_domain' => self::SHOP,
            'status' => 'STARTED', 'expires_at' => now()->addMinutes(15),
        ]);

        $this->install();

        $this->assertDatabaseCount('leads', 0);
        $this->assertSame(9, Store::first()->agency_id);
    }

    // ------------------------------------------- 8 existing BRIX customer rule

    public function test_existing_brix_customer_never_becomes_a_lead(): void
    {
        $this->boundClick($this->makeLink(7));
        // BRIX first saw this shop long before the referral click.
        $this->seedCartninjaShop(self::SHOP, now()->subDays(60));

        $this->install()->assertOk();

        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('stores', 0);
        $this->assertDatabaseCount('agency_stores', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'REFERRAL_ATTRIBUTION_SKIPPED', 'agency_id' => 7]);
    }

    public function test_existing_customer_with_a_pre_referral_store_row_keeps_its_owner(): void
    {
        $this->boundClick($this->makeLink(7));
        $this->newToBrix(); // even if the shops row looks fresh...
        $this->createStore(7, self::SHOP, ['installed_at' => now()->subDays(30)]); // ...the store predates the click

        $this->install();

        $this->assertDatabaseCount('leads', 0);
        $this->assertSame(7, Store::first()->agency_id);
    }

    public function test_undeterminable_customer_history_fails_closed(): void
    {
        $this->boundClick($this->makeLink(7)); // no cartninja shops row at all

        $this->install();

        $this->assertDatabaseCount('leads', 0);
    }

    // ------------------------------------------------- 9-10 attribution preserved

    public function test_existing_lead_is_not_overwritten_by_a_new_referral(): void
    {
        $first = $this->makeLink(1);
        $existing = Lead::create([
            'agency_id' => 1, 'tracking_link_id' => $first->id, 'shop_domain' => self::SHOP,
            'lead_stage' => Lead::STAGE_CONTACTED, 'first_clicked_at' => now()->subDays(3),
        ]);
        $this->boundClick($this->makeLink(2));
        $this->newToBrix();

        $this->install();

        $this->assertDatabaseCount('leads', 1);
        $lead = $existing->fresh();
        $this->assertSame(1, $lead->agency_id);
        $this->assertSame($first->id, $lead->tracking_link_id);
        $this->assertSame(Lead::STAGE_CONTACTED, $lead->lead_stage);
    }

    public function test_existing_agency_attribution_on_the_store_is_not_overwritten(): void
    {
        $this->boundClick($this->makeLink(2));
        $this->newToBrix();
        $this->createStore(1, self::SHOP, ['installed_at' => now()->subMinutes(1)]);

        $this->install();

        $this->assertDatabaseCount('leads', 0);
        $this->assertSame(1, Store::first()->agency_id);
        $this->assertDatabaseCount('agency_stores', 0);
    }

    // ------------------------------------------ 11-12 invalid / inactive referral

    public function test_invalid_referral_creates_no_attribution_lead_or_event(): void
    {
        $this->get('/ref/BRIX-NOPE')->assertNotFound();
        $this->assertDatabaseCount('referral_clicks', 0);

        // No click bound to this shop, or a click older than the window.
        $this->newToBrix();
        $this->install();
        $this->assertDatabaseCount('leads', 0);

        $this->boundClick($this->makeLink(1), self::SHOP, now()->subDays(30));
        $this->install();

        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('lead_events', 0);
    }

    public function test_inactive_referral_creates_no_lead_or_event(): void
    {
        $inactive = $this->makeLink(1, ['status' => TrackingLink::STATUS_INACTIVE]);
        $this->get('/ref/'.$inactive->code)->assertNotFound();
        $this->assertDatabaseCount('referral_clicks', 0);

        // Link deactivated after the click was bound but before install.
        $link = $this->makeLink(1);
        $this->boundClick($link);
        $link->update(['status' => TrackingLink::STATUS_INACTIVE]);
        $this->newToBrix();

        $this->install();

        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('lead_events', 0);
    }

    // ------------------------------------------------------- 13 one lead per shop

    public function test_same_shop_cannot_receive_a_second_lead(): void
    {
        $this->boundClick($this->makeLink(1), self::SHOP, now()->subMinutes(20));
        $this->boundClick($this->makeLink(2), self::SHOP, now()->subMinutes(10));
        $this->newToBrix();

        $this->install();
        $this->install();

        $this->assertDatabaseCount('leads', 1);
        $this->assertSame(2, Lead::first()->agency_id); // last valid click wins, once

        $this->expectException(UniqueConstraintViolationException::class);
        Lead::create(['agency_id' => 3, 'shop_domain' => self::SHOP]);
    }

    // --------------------------------------------- 14 install updates right lead

    public function test_store_sync_updates_only_the_matching_lead(): void
    {
        $mine = Lead::create(['agency_id' => 1, 'shop_domain' => self::SHOP, 'lead_stage' => Lead::STAGE_CONTACTED]);
        $other = Lead::create(['agency_id' => 1, 'shop_domain' => 'other.myshopify.com', 'lead_stage' => Lead::STAGE_CONTACTED]);
        $store = $this->createStore(1, self::SHOP, ['installed_at' => now()]);

        ReferralAttribution::syncStore($store);

        $mine = $mine->fresh();
        $this->assertSame($store->id, $mine->store_id);
        $this->assertSame('INSTALLED', $mine->brix_status);
        $this->assertSame(Lead::STAGE_INSTALLED, $mine->lead_stage);

        $other = $other->fresh();
        $this->assertNull($other->store_id);
        $this->assertNull($other->brix_status);
        $this->assertSame(Lead::STAGE_CONTACTED, $other->lead_stage);
    }

    public function test_another_agencys_store_state_is_never_mirrored_onto_a_lead(): void
    {
        $lead = Lead::create(['agency_id' => 1, 'shop_domain' => self::SHOP]);

        ReferralAttribution::syncStore($this->createStore(2));

        $this->assertNull($lead->fresh()->store_id);
        $this->assertNull($lead->fresh()->brix_status);
    }

    // -------------------------------------------------- 15-16 activation + events

    public function test_activation_moves_the_lead_to_active_only_when_the_store_really_activates(): void
    {
        $this->boundClick($this->makeLink(7));
        $this->boundClick($this->makeLink(8), 'bystander.myshopify.com');
        $this->newToBrix();
        $this->newToBrix('bystander.myshopify.com');
        $this->install();
        $this->install('bystander.myshopify.com');

        $lead = Lead::where('shop_domain', self::SHOP)->first();
        $this->assertSame(Lead::STAGE_INSTALLED, $lead->lead_stage);

        $this->hook('store-authorize')->assertOk();
        $lead = $lead->fresh();
        $this->assertSame('AUTHORIZED', $lead->brix_status);
        $this->assertSame(Lead::STAGE_INSTALLED, $lead->lead_stage);
        $this->assertNull($lead->activated_at);

        $this->hook('store-activate')->assertOk();

        $lead = $lead->fresh();
        $this->assertSame(Lead::STAGE_ACTIVE, $lead->lead_stage);
        $this->assertSame('ACTIVE', $lead->brix_status);
        $this->assertNotNull($lead->activated_at);
        $this->assertSame([LeadEvent::CLICKED, LeadEvent::INSTALLED, LeadEvent::ACTIVATED], $this->eventTypes($lead));

        $bystander = Lead::where('shop_domain', 'bystander.myshopify.com')->first();
        $this->assertSame(Lead::STAGE_INSTALLED, $bystander->lead_stage);
        $this->assertNull($bystander->activated_at);

        $this->hook('store-activate')->assertOk(); // idempotent
        $this->assertCount(3, $lead->events);
    }

    // ---------------------------------------------------------- 18 direct install

    public function test_direct_installation_without_a_referral_creates_no_lead_or_store(): void
    {
        $this->newToBrix();

        $this->install()->assertOk();

        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('lead_events', 0);
        $this->assertDatabaseCount('stores', 0);
    }

    // ------------------------------------------------------------ 19 uninstall

    public function test_uninstall_preserves_referral_history(): void
    {
        $this->boundClick($this->makeLink(7));
        $this->newToBrix();
        $this->install();
        $this->hook('store-authorize');
        $this->hook('store-activate');

        $this->hook('store-uninstalled')->assertOk();

        $this->assertDatabaseCount('leads', 1);
        $lead = Lead::first();
        $this->assertSame('UNINSTALLED', $lead->brix_status);
        $this->assertSame(7, $lead->agency_id);
        $this->assertSame(Lead::STAGE_ACTIVE, $lead->lead_stage);
        $this->assertNotNull($lead->activated_at);
        $this->assertNotNull($lead->installed_at);
        $this->assertSame(
            [LeadEvent::CLICKED, LeadEvent::INSTALLED, LeadEvent::ACTIVATED, LeadEvent::CHURNED],
            $this->eventTypes($lead)
        );
        $this->assertDatabaseCount('stores', 1);

        // The existing install callback only records a reinstall through a
        // pending onboarding, so without one the store (and therefore the
        // lead's mirrored brix_status) truthfully stays UNINSTALLED.
        $this->install();
        $this->assertDatabaseCount('leads', 1);
        $this->assertSame('UNINSTALLED', Lead::first()->brix_status);

        // Reinstalled through the agency's onboarding: same lead, real
        // status back to INSTALLED, and the return is recorded.
        AgencyStoreOnboarding::create([
            'agency_id' => 7, 'state_token' => str_repeat('c', 64), 'shop_domain' => self::SHOP,
            'status' => 'STARTED', 'expires_at' => now()->addMinutes(15),
        ]);
        $this->install();

        $this->assertDatabaseCount('leads', 1);
        $lead = Lead::first();
        $this->assertSame('INSTALLED', $lead->brix_status);
        $this->assertSame(Lead::STAGE_ACTIVE, $lead->lead_stage);
        $this->assertSame(LeadEvent::INSTALLED, $lead->events()->orderByDesc('id')->value('event_type'));
    }

    // ------------------------------------------------------------- 17 isolation

    public function test_agency_cannot_access_another_agencys_referral_link_or_leads(): void
    {
        $this->createTenancyTables();

        $agencyA = Partner::create(['name' => 'Agency A', 'slug' => 'agency-a', 'owner_name' => 'A', 'owner_email' => 'a@example.com']);
        $agencyB = Partner::create(['name' => 'Agency B', 'slug' => 'agency-b', 'owner_name' => 'B', 'owner_email' => 'b@example.com']);

        $orgA = Organisation::create(['name' => 'Org A']);
        $orgA->forceFill(['brix_agency_id' => $agencyA->id])->save();
        $orgB = Organisation::create(['name' => 'Org B']);
        $orgB->forceFill(['brix_agency_id' => $agencyB->id])->save();

        $userA = User::factory()->create();
        $orgA->users()->attach($userA->id, ['role' => 'owner']);

        $linkA = $this->makeLink($agencyA->id, ['name' => 'Alpha Link']);
        $linkB = $this->makeLink($agencyB->id, ['name' => 'Beta Link']);
        Lead::create(['agency_id' => $agencyA->id, 'tracking_link_id' => $linkA->id, 'shop_domain' => 'a-shop.myshopify.com']);
        Lead::create(['agency_id' => $agencyB->id, 'tracking_link_id' => $linkB->id, 'shop_domain' => 'b-shop.myshopify.com']);

        $this->assertSame(['a-shop.myshopify.com'], $orgA->leads()->pluck('shop_domain')->all());
        $this->assertSame(['b-shop.myshopify.com'], $orgB->leads()->pluck('shop_domain')->all());

        $this->actingAs($userA)->withSession(['organisation_id' => $orgA->id]);

        $this->get("/referral-links/{$linkB->id}/leads")->assertForbidden();
        $this->post("/referral-links/{$linkB->id}/deactivate")->assertForbidden();
        $this->assertSame(TrackingLink::STATUS_ACTIVE, $linkB->fresh()->status);

        $this->get("/referral-links/{$linkA->id}/leads")->assertOk()->assertSee('a-shop.myshopify.com')->assertDontSee('b-shop.myshopify.com');
        $this->get('/referral-links')->assertOk()->assertSee('Alpha Link')->assertDontSee('Beta Link');

        $this->assertTrue(Gate::forUser($userA)->allows('view', $linkA));
        $this->assertTrue(Gate::forUser($userA)->denies('view', $linkB));
    }
}
