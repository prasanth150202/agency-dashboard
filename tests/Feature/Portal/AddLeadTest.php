<?php

namespace Tests\Feature\Portal;

use App\Models\Organisation;
use App\Models\Partners\Partner;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\BrixTestSchema;
use Tests\TestCase;

/**
 * The manual "Add Lead" flow (Section 2), and that it produces exactly the
 * same Lead record both the Agency and Super Admin sides read (Section 4) —
 * no second lead table, no bypass of tenant scoping.
 */
class AddLeadTest extends TestCase
{
    use BrixTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-25 12:00:00');
        $this->migrateReferralTables();
        $this->createBrixScaffoldTables();
        $this->createFinanceScaffoldTables();
        $this->createTenancyTables();
        config()->set('services.shopify.internal_secret', 'test-secret');
        config()->set('services.shopify.backend_url', 'https://backend.test');
        Http::fake([
            'https://backend.test/store_install_status.php*' => Http::response([
                'success' => true,
                'data' => ['shop_domain' => 'acme.myshopify.com', 'installed' => false],
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function login(string $agencyName = 'Agency A'): array
    {
        $agency = Partner::create(['name' => $agencyName, 'slug' => \Illuminate\Support\Str::slug($agencyName).uniqid(), 'owner_name' => 'o', 'owner_email' => uniqid().'@example.com']);
        $org = Organisation::create(['name' => $agencyName.' Org']);
        $org->forceFill(['brix_agency_id' => $agency->id])->save();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'owner']);
        $this->actingAs($user)->withSession(['organisation_id' => $org->id]);

        return [$agency, $org, $user];
    }

    private function loginAsAdmin(): \App\Models\Admin\AdminUser
    {
        $admin = \App\Models\Admin\AdminUser::create(['name' => 'Admin', 'email' => uniqid().'@example.com', 'password_hash' => bcrypt('x'), 'role' => 'SUPER_ADMIN', 'status' => 'active']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_add_lead_form_has_only_the_specified_fields(): void
    {
        $this->login();

        $response = $this->get('/leads')->assertOk()
            ->assertSee('Company Name')->assertSee('Contact Name')->assertSee('Contact Email')
            ->assertSee('Contact Phone')->assertSee('Website');

        // The Add Lead <form> itself carries no campaign/referral-source/link field
        // (the rest of the page legitimately shows "Campaign" for referred leads).
        preg_match('/<form method="POST" action="[^"]*\/leads"[^>]*>.*?<\/form>/s', $response->getContent(), $m);
        $this->assertNotEmpty($m, 'Add Lead form not found on the page.');
        $formHtml = $m[0];
        $this->assertStringNotContainsStringIgnoringCase('campaign', $formHtml);
        $this->assertStringNotContainsString('name="tracking_link_id"', $formHtml);
        $this->assertStringNotContainsString('name="referral_source"', $formHtml);
    }

    /**
     * Superseded: website is now mandatory (checked against the read-only
     * `cartninja` connection), and a manual lead for a genuinely new
     * prospect now gets its own dedicated referral link — see LeadsTest.
     */
    public function test_creating_a_lead_sets_the_exact_required_fields(): void
    {
        [$agency, , $user] = $this->login();

        $this->post('/leads', [
            'company_name' => 'Acme Co', 'contact_name' => 'Jane Doe', 'contact_email' => 'jane@acme.test',
            'contact_phone' => '555-0100', 'website' => 'https://acme.myshopify.com/admin', 'notes' => 'Met at a conference.',
        ])->assertRedirect();

        $lead = Lead::first();
        $this->assertSame($agency->id, $lead->agency_id);
        $this->assertSame($user->id, $lead->created_by);
        $this->assertSame(Lead::STAGE_NEW, $lead->lead_stage);
        $this->assertSame(Lead::SOURCE_MANUAL, $lead->source);
        $this->assertSame('Acme Co', $lead->company_name);
        $this->assertSame('Jane Doe', $lead->contact_name);
        $this->assertSame('jane@acme.test', $lead->contact_email);
        $this->assertSame('555-0100', $lead->contact_phone);
        $this->assertSame('https://acme.myshopify.com/admin', $lead->website);
        $this->assertSame('Met at a conference.', $lead->notes);
        $this->assertNotNull($lead->tracking_link_id);
        $this->assertSame($agency->id, $lead->trackingLink->agency_id);
        $this->assertSame('acme.myshopify.com', $lead->shop_domain);
        $this->assertStringContainsString('shop=acme.myshopify.com', $lead->trackingLink->destination_url);
        $this->assertDatabaseHas('activity_logs', ['action' => 'lead.created_manually', 'agency_id' => $agency->id]);
    }

    public function test_company_name_contact_name_email_and_website_are_required(): void
    {
        $this->login();

        $this->post('/leads', [])->assertSessionHasErrors(['company_name', 'contact_name', 'contact_email', 'website']);
        $this->assertDatabaseCount('leads', 0);

        $this->post('/leads', ['company_name' => 'Acme', 'contact_name' => 'Jane', 'contact_email' => 'not-an-email', 'website' => 'https://acme.myshopify.com'])
            ->assertSessionHasErrors('contact_email');
    }

    public function test_manual_lead_requires_a_shopify_store_url(): void
    {
        $this->login();

        $this->post('/leads', [
            'company_name' => 'Acme Co', 'contact_name' => 'Jane', 'contact_email' => 'jane@acme.test',
            'website' => 'https://acme.test',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_custom_shopify_storefront_url_is_resolved_to_myshopify_domain(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'https://coreeat.in*' => Http::response('<script>Shopify.shop = "coreeat.myshopify.com";</script>'),
            'https://backend.test/store_install_status.php*' => Http::response(['success' => true, 'data' => ['installed' => false]]),
        ]);

        $this->login();

        $this->post('/leads', [
            'company_name' => 'CoreEats', 'contact_name' => 'Jane', 'contact_email' => 'jane@coreeat.test',
            'website' => 'https://coreeat.in/',
        ])->assertRedirect();

        $lead = Lead::where('company_name', 'CoreEats')->firstOrFail();
        $this->assertSame('coreeat.myshopify.com', $lead->shop_domain);
        $this->assertNotNull($lead->tracking_link_id);
        $this->assertStringContainsString('shop=coreeat.myshopify.com', $lead->trackingLink->destination_url);
    }

    public function test_a_request_cannot_assign_a_lead_to_another_agency(): void
    {
        $this->login('Agency A');
        $other = Partner::create(['name' => 'Agency B', 'slug' => 'agency-b'.uniqid(), 'owner_name' => 'o', 'owner_email' => uniqid().'@example.com']);

        // agency_id is never accepted from the request, even if supplied.
        $this->post('/leads', [
            'company_name' => 'Acme Co', 'contact_name' => 'Jane', 'contact_email' => 'jane@acme.test',
            'website' => 'https://acme.myshopify.com', 'agency_id' => $other->id,
        ])->assertRedirect();

        $lead = Lead::first();
        $this->assertNotEquals($other->id, $lead->agency_id);
    }

    public function test_manual_lead_never_collides_with_the_shop_domain_unique_constraint(): void
    {
        [$agency] = $this->login();

        Http::fake([
            'https://backend.test/store_install_status.php*' => Http::response(['success' => true, 'data' => ['installed' => false]]),
        ]);

        $this->post('/leads', ['company_name' => 'A', 'contact_name' => 'A', 'contact_email' => 'a@a.test', 'website' => 'https://a.myshopify.com']);
        $this->post('/leads', ['company_name' => 'B', 'contact_name' => 'B', 'contact_email' => 'b@b.test', 'website' => 'https://b.myshopify.com']);

        $this->assertDatabaseCount('leads', 2);
        $this->assertTrue(Lead::whereNotNull('shop_domain')->count() === 2);
    }

    public function test_installed_shopify_store_is_approved_without_referral_link(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            '*' => Http::response(['success' => true, 'data' => ['installed' => true]]),
        ]);

        [$agency] = $this->login();

        $this->post('/leads', [
            'company_name' => 'Installed Acme Co', 'contact_name' => 'Jane Doe', 'contact_email' => 'jane@acme.test',
            'website' => 'https://acme.myshopify.com',
        ])->assertRedirect();

        $lead = Lead::where('company_name', 'Installed Acme Co')->firstOrFail();
        $this->assertSame($agency->id, $lead->agency_id);
        $this->assertSame('acme.myshopify.com', $lead->shop_domain);
        $this->assertSame(Lead::STAGE_INSTALLED, $lead->lead_stage);
        $this->assertSame('INSTALLED', $lead->brix_status);
        $this->assertNull($lead->tracking_link_id);
    }

    public function test_agency_and_super_admin_see_the_exact_same_manually_created_lead(): void
    {
        [$agency] = $this->login();

        $this->post('/leads', ['company_name' => 'Acme Co', 'contact_name' => 'Jane Doe', 'contact_email' => 'jane@acme.test', 'website' => 'https://acme.myshopify.com']);
        $lead = Lead::first();

        $this->get('/leads')->assertOk()->assertSee('Acme Co');
        $this->get("/leads/{$lead->id}")->assertOk()->assertSee('Acme Co')->assertSee('Jane Doe')->assertSee('jane@acme.test');

        $this->loginAsAdmin();
        $this->get('/admin/leads')->assertOk()->assertSee('Acme Co')->assertSee($agency->name);
        $this->get("/admin/leads/{$lead->id}")->assertOk()->assertSee('Acme Co')->assertSee('Jane Doe')->assertSee('jane@acme.test');

        $this->assertDatabaseCount('leads', 1); // one table, one row, both sides read it
    }

    public function test_referral_attributed_lead_is_also_visible_on_both_sides(): void
    {
        [$agency] = $this->login('Agency A');
        $link = TrackingLink::create(['agency_id' => $agency->id, 'name' => 'L', 'channel' => 'Website', 'destination_url' => 'x']);
        ReferralClick::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'referral_code' => $link->code, 'session_id' => 's', 'shop_domain' => 'new-shop.myshopify.com', 'created_at' => now()->subMinutes(10)]);

        $this->fakeCartninjaShops();
        $this->seedCartninjaShop('new-shop.myshopify.com', now()->subMinutes(2));
        $this->postJson('/internal/shopify/agency/store-installed', ['shop_domain' => 'new-shop.myshopify.com'], ['X-Internal-Secret' => 'test-secret'])->assertOk();

        $referredLead = Lead::where('shop_domain', 'new-shop.myshopify.com')->first();
        $this->assertNotNull($referredLead);
        $this->assertSame(Lead::SOURCE_REFERRAL, $referredLead->source); // column default, never set by ReferralAttribution
        $this->assertNull($referredLead->created_by);

        // A genuinely new referred install creates a real Store row (Phase 3),
        // so the page shows the resolved store name, not the bare domain.
        $referredLead->refresh();
        $this->assertNotNull($referredLead->store_id);
        $storeName = $referredLead->store->store_name;

        $this->get('/leads')->assertOk()->assertSee($storeName);

        $this->loginAsAdmin();
        $this->get('/admin/leads')->assertOk()->assertSee($storeName)->assertSee($agency->name);
    }

    public function test_existing_brix_customer_click_still_never_becomes_a_referred_lead(): void
    {
        [$agency] = $this->login();
        $link = TrackingLink::create(['agency_id' => $agency->id, 'name' => 'L', 'channel' => 'Website', 'destination_url' => 'x']);
        ReferralClick::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'referral_code' => $link->code, 'session_id' => 's', 'shop_domain' => 'old-shop.myshopify.com', 'created_at' => now()->subMinutes(10)]);

        $this->fakeCartninjaShops();
        $this->seedCartninjaShop('old-shop.myshopify.com', now()->subDays(90)); // long-standing BRIX customer
        $this->postJson('/internal/shopify/agency/store-installed', ['shop_domain' => 'old-shop.myshopify.com'], ['X-Internal-Secret' => 'test-secret'])->assertOk();

        $this->assertDatabaseCount('leads', 0);
        $this->get('/leads')->assertOk()->assertDontSee('old-shop.myshopify.com');

        $this->loginAsAdmin();
        $this->get('/admin/leads')->assertOk()->assertDontSee('old-shop.myshopify.com');
    }
}
