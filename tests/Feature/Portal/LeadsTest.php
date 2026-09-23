<?php

namespace Tests\Feature\Portal;

use App\Models\Partners\ActivityLog;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use Illuminate\Support\Facades\Http;
use Tests\Support\PartnerPortalTestbed;
use Tests\TestCase;

/** Phase 2: the agency-wide Leads page. */
class LeadsTest extends TestCase
{
    use PartnerPortalTestbed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootPortal();
        config()->set('services.shopify.internal_secret', 'test-secret');
        config()->set('services.shopify.backend_url', 'https://backend.test');
        Http::fake([
            'https://backend.test/store_install_status.php*' => Http::response(['success' => true, 'data' => ['installed' => false]]),
        ]);
    }

    private function link(array $tenant, string $name, string $channel = 'Instagram'): TrackingLink
    {
        return TrackingLink::create([
            'agency_id' => $tenant['agency']->id, 'name' => $name, 'channel' => $channel,
            'destination_url' => 'https://apps.shopify.com/thebrix-io',
        ]);
    }

    private function lead(array $tenant, TrackingLink $link, string $shop, array $extra = []): Lead
    {
        return Lead::create(array_merge([
            'agency_id' => $tenant['agency']->id, 'tracking_link_id' => $link->id, 'shop_domain' => $shop,
            'first_clicked_at' => now()->subDay(),
        ], $extra));
    }

    public function test_empty_state_when_no_leads(): void
    {
        $this->actAs($this->makeTenant('Empty Agency'));

        $this->get('/leads')->assertOk()->assertSee('No leads yet');
    }

    public function test_lists_only_own_leads_and_denies_foreign_detail_and_stage_change(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $mine = $this->lead($a, $this->link($a, 'Alpha'), 'a-shop.myshopify.com');
        $theirs = $this->lead($b, $this->link($b, 'Beta'), 'b-shop.myshopify.com');

        $this->actAs($a);

        $this->get('/leads')->assertOk()->assertSee('a-shop.myshopify.com')->assertDontSee('b-shop.myshopify.com');
        $this->get("/leads/{$mine->id}")->assertOk();
        $this->get("/leads/{$theirs->id}")->assertForbidden();
        $this->post("/leads/{$theirs->id}/stage", ['lead_stage' => 'CONTACTED'])->assertForbidden();
        $this->assertSame('NEW', $theirs->fresh()->lead_stage);

        // A forged agency_id / organisation in the request never widens scope.
        $this->get('/leads?agency_id='.$b['agency']->id.'&organisation_id='.$b['org']->id)
            ->assertOk()->assertDontSee('b-shop.myshopify.com');
    }

    public function test_filters_by_stage_link_channel_and_search(): void
    {
        $a = $this->makeTenant('Agency A');
        $insta = $this->link($a, 'Insta link', 'Instagram');
        $mail = $this->link($a, 'Mail link', 'Email');
        $this->lead($a, $insta, 'one.myshopify.com', ['lead_stage' => 'CONTACTED']);
        $this->lead($a, $mail, 'two.myshopify.com', ['lead_stage' => 'ACTIVE']);
        $this->actAs($a);

        $this->get('/leads?stage=ACTIVE')->assertSee('two.myshopify.com')->assertDontSee('one.myshopify.com');
        $this->get('/leads?link='.$insta->id)->assertSee('one.myshopify.com')->assertDontSee('two.myshopify.com');
        $this->get('/leads?channel=Email')->assertSee('two.myshopify.com')->assertDontSee('one.myshopify.com');
        $this->get('/leads?search=two.')->assertSee('two.myshopify.com')->assertDontSee('one.myshopify.com');
    }

    public function test_agency_can_set_outreach_stage_and_it_is_audited(): void
    {
        $a = $this->makeTenant('Agency A');
        $lead = $this->lead($a, $this->link($a, 'Alpha'), 'a-shop.myshopify.com');
        $this->actAs($a);

        $this->post("/leads/{$lead->id}/stage", ['lead_stage' => 'CONTACTED'])->assertSessionHas('success');

        $lead->refresh();
        $this->assertSame('CONTACTED', $lead->lead_stage);
        $this->assertNotNull($lead->contacted_at);
        $this->assertSame(1, ActivityLog::where('action', 'lead.stage_changed')->where('agency_id', $a['agency']->id)->count());
    }

    public function test_agency_cannot_set_brix_owned_stages_or_change_a_matched_lead(): void
    {
        $a = $this->makeTenant('Agency A');
        $lead = $this->lead($a, $this->link($a, 'Alpha'), 'a-shop.myshopify.com');
        $matched = $this->lead($a, $this->link($a, 'Beta'), 'm-shop.myshopify.com', [
            'lead_stage' => 'INSTALLED', 'brix_status' => 'INSTALLED', 'installed_at' => now(),
        ]);
        $this->actAs($a);

        foreach (['ACTIVE', 'INSTALLED', 'INSTALL_STARTED', 'NEW', 'BOGUS'] as $stage) {
            $this->post("/leads/{$lead->id}/stage", ['lead_stage' => $stage])->assertSessionHasErrors('lead_stage');
        }
        $this->assertSame('NEW', $lead->fresh()->lead_stage);

        $this->post("/leads/{$matched->id}/stage", ['lead_stage' => 'LOST'])->assertSessionHas('error');
        $this->assertSame('INSTALLED', $matched->fresh()->lead_stage);
    }

    public function test_revenue_and_commission_come_only_from_referral_tables(): void
    {
        $a = $this->makeTenant('Agency A');
        $lead = $this->lead($a, $this->link($a, 'Alpha'), 'a-shop.myshopify.com', ['lead_stage' => 'ACTIVE', 'brix_status' => 'ACTIVE']);
        $none = $this->lead($a, $this->link($a, 'Gamma'), 'z-shop.myshopify.com');

        $event = ReferralRevenueEvent::create([
            'agency_id' => $a['agency']->id, 'store_id' => 1, 'lead_id' => $lead->id, 'shop_domain' => 'a-shop.myshopify.com',
            'revenue_type' => 'usage', 'source' => 'shopify_usage_record', 'external_event_id' => 'gid://x/1',
            'revenue_amount' => '10.00', 'currency' => 'USD', 'occurred_at' => now(),
        ]);
        ReferralCommission::create([
            'revenue_event_id' => $event->id, 'agency_id' => $a['agency']->id, 'store_id' => 1, 'lead_id' => $lead->id,
            'revenue_type' => 'usage', 'revenue_amount' => '10.00', 'currency' => 'USD', 'commission_rate' => '30.00',
            'rate_source' => 'agency_default', 'commission_amount' => '3.00', 'status' => 'pending',
        ]);
        $this->actAs($a);

        $this->get('/leads')->assertOk()->assertSee('$10.00')->assertSee('$3.00');
        $this->get("/leads/{$lead->id}")->assertOk()->assertSee('$10.00')->assertSee('$3.00');
        // A lead with no events shows no invented figure.
        $this->get("/leads/{$none->id}")->assertOk()->assertSee('No verified BRIX revenue for this store yet.');
    }

    public function test_website_is_required_to_add_a_lead(): void
    {
        $a = $this->makeTenant('Agency A');
        $this->actAs($a);

        $this->post('/leads', [
            'company_name' => 'Acme', 'contact_name' => 'Jo', 'contact_email' => 'jo@acme.test',
        ])->assertSessionHasErrors('website');
    }

    public function test_adding_a_lead_for_a_new_prospect_creates_a_dedicated_referral_link(): void
    {
        $a = $this->makeTenant('Agency A');
        $this->actAs($a);

        $response = $this->post('/leads', [
            'company_name' => 'Acme', 'contact_name' => 'Jo', 'contact_email' => 'jo@acme.test',
            'website' => 'https://acme-gadgets.myshopify.com/admin',
        ]);

        $lead = Lead::where('company_name', 'Acme')->firstOrFail();
        $response->assertRedirect(route('leads.show', $lead))->assertSessionHas('createdLink');

        $this->assertNotNull($lead->tracking_link_id);
        $this->assertNull($lead->brix_status);
        $this->assertSame('acme-gadgets.myshopify.com', $lead->shop_domain);
        $this->assertSame(TrackingLink::class, get_class($lead->trackingLink));
        $this->assertSame($a['agency']->id, $lead->trackingLink->agency_id);
        $this->assertStringContainsString('shop=acme-gadgets.myshopify.com', $lead->trackingLink->destination_url);

        $response->assertSessionHas('createdLink.url', $lead->trackingLink->referral_url);
        $this->withSession(['createdLink' => ['url' => $lead->trackingLink->referral_url]])
            ->get("/leads/{$lead->id}")
            ->assertOk()
            ->assertSee('Referral link ready')
            ->assertSee($lead->trackingLink->referral_url, false);
    }

    public function test_adding_a_lead_already_on_shopify_skips_the_referral_link(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            '*' => Http::response(['success' => true, 'data' => ['installed' => true]]),
        ]);
        $this->assertTrue(\App\Services\Brix\BrixInstallCheck::isInstalled('already-here.myshopify.com'));

        $a = $this->makeTenant('Agency A');
        $this->seedCartninjaShopWithPlan('already-here.myshopify.com', 'pro');
        $this->actAs($a);

        $response = $this->post('/leads', [
            'company_name' => 'Already Here', 'contact_name' => 'Jo', 'contact_email' => 'jo@already.test',
            'website' => 'already-here.myshopify.com',
        ]);

        $lead = Lead::where('company_name', 'Already Here')->firstOrFail();
        $response->assertRedirect(route('leads.show', $lead))->assertSessionMissing('createdLink');

        $this->assertNull($lead->tracking_link_id);
        $this->assertSame('already-here.myshopify.com', $lead->shop_domain);
        $this->assertSame('INSTALLED', $lead->brix_status);
        $this->assertSame('pro', $lead->brix_plan);
        $this->assertSame(Lead::STAGE_INSTALLED, $lead->lead_stage);
    }

    public function test_adding_a_lead_for_a_shop_that_already_has_one_is_rejected(): void
    {
        $a = $this->makeTenant('Agency A');
        $this->seedCartninjaShopWithPlan('dup-shop.myshopify.com', 'starter');
        $this->lead($a, $this->link($a, 'Alpha'), 'dup-shop.myshopify.com');
        $this->actAs($a);

        $this->post('/leads', [
            'company_name' => 'Dup', 'contact_name' => 'Jo', 'contact_email' => 'jo@dup.test',
            'website' => 'dup-shop.myshopify.com',
        ])->assertSessionHasErrors('website');

        $this->assertSame(1, Lead::where('shop_domain', 'dup-shop.myshopify.com')->count());
    }

    public function test_custom_domain_lead_link_skips_the_store_step_and_install_marks_the_lead_installed(): void
    {
        $this->allowStorefrontLookups();
        Http::fake([
            'https://coolgadgets.com/meta.json' => Http::response(['myshopify_domain' => 'cool-gadgets.myshopify.com']),
        ]);
        $a = $this->makeTenant('Agency A');
        $this->actAs($a);

        $this->post('/leads', [
            'company_name' => 'Cool Gadgets', 'contact_name' => 'Jo', 'contact_email' => 'jo@cool.test',
            'website' => 'https://coolgadgets.com/collections/all',
        ])->assertSessionHasNoErrors();

        $lead = Lead::where('company_name', 'Cool Gadgets')->firstOrFail();
        $this->assertSame('cool-gadgets.myshopify.com', $lead->shop_domain);
        $this->assertNull($lead->brix_status);

        // The merchant is never asked for their store — straight to the install for that shop.
        $this->get('/ref/'.$lead->trackingLink->code)
            ->assertRedirect($lead->trackingLink->destination_url);
        $this->assertStringContainsString('shop=cool-gadgets.myshopify.com', $lead->trackingLink->destination_url);

        $this->seedCartninjaShop('cool-gadgets.myshopify.com', now());
        $this->postJson('/internal/shopify/agency/store-installed', ['shop_domain' => 'cool-gadgets.myshopify.com'], ['X-Internal-Secret' => 'test-secret'])
            ->assertOk();

        $lead->refresh();
        $this->assertSame('INSTALLED', $lead->brix_status);
        $this->assertSame(Lead::STAGE_INSTALLED, $lead->lead_stage);
        $this->assertNotNull($lead->store_id);
        $this->assertSame($a['agency']->id, $lead->store->agency_id);
        $this->assertSame(1, Lead::where('shop_domain', 'cool-gadgets.myshopify.com')->count());

        $this->get('/leads')->assertOk()->assertSee('Installed');
    }

    public function test_install_without_clicking_the_leads_link_does_not_credit_the_lead(): void
    {
        $a = $this->makeTenant('Agency A');
        $this->actAs($a);
        $this->post('/leads', [
            'company_name' => 'Direct', 'contact_name' => 'Jo', 'contact_email' => 'jo@direct.test',
            'website' => 'direct-shop.myshopify.com',
        ]);

        $this->seedCartninjaShop('direct-shop.myshopify.com', now());
        $this->postJson('/internal/shopify/agency/store-installed', ['shop_domain' => 'direct-shop.myshopify.com'], ['X-Internal-Secret' => 'test-secret'])
            ->assertOk();

        $this->assertNull(Lead::where('shop_domain', 'direct-shop.myshopify.com')->value('brix_status'));
    }

    public function test_non_shopify_website_is_rejected(): void
    {
        $this->allowStorefrontLookups();
        Http::fake(['https://plain-site.com*' => Http::response('<html>WordPress</html>')]);
        $a = $this->makeTenant('Agency A');
        $this->actAs($a);

        $this->post('/leads', [
            'company_name' => 'Plain', 'contact_name' => 'Jo', 'contact_email' => 'jo@plain.test',
            'website' => 'https://plain-site.com',
        ])->assertSessionHasErrors('website');

        $this->assertSame(0, Lead::count());
    }

    public function test_uninstalled_shop_in_cartdrawer_gets_an_install_link(): void
    {
        \Illuminate\Support\Facades\DB::connection('cartninja')->statement('ALTER TABLE shops ADD COLUMN status TEXT');
        \Illuminate\Support\Facades\DB::connection('cartninja')->table('shops')->insert([
            'shop_domain' => 'gone-shop.myshopify.com', 'plan_key' => 'free', 'status' => 'uninstalled',
        ]);
        $a = $this->makeTenant('Agency A');
        $this->actAs($a);

        $this->post('/leads', [
            'company_name' => 'Gone', 'contact_name' => 'Jo', 'contact_email' => 'jo@gone.test',
            'website' => 'gone-shop.myshopify.com',
        ])->assertSessionHas('createdLink');

        $lead = Lead::where('shop_domain', 'gone-shop.myshopify.com')->firstOrFail();
        $this->assertNull($lead->brix_status);
        $this->assertNotNull($lead->tracking_link_id);
    }

    public function test_storefront_lookup_never_fetches_private_or_local_hosts(): void
    {
        Http::fake();
        $resolver = new \App\Services\Shopify\ShopifyStoreResolver;

        foreach (['http://127.0.0.1/', 'localhost', 'http://10.0.0.5', 'http://169.254.169.254/latest/meta-data', 'file:///etc/passwd'] as $url) {
            $this->assertNull($resolver->resolve($url), $url);
        }

        Http::assertNothingSent();
    }

    private function seedCartninjaShopWithPlan(string $domain, string $plan): void
    {
        \Illuminate\Support\Facades\DB::connection('cartninja')->table('shops')->insert([
            'shop_domain' => $domain, 'plan_key' => $plan, 'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }
}
