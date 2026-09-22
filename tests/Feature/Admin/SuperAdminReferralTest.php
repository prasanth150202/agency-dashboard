<?php

namespace Tests\Feature\Admin;

use App\Models\Partners\Partner;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Tests\Support\BrixTestSchema;
use Tests\TestCase;

/**
 * Super Admin's global referral visibility: the same tables the Agency
 * Dashboard reads, with no per-agency scope, and never trusting a browser-
 * supplied agency id for authorization (a filter value only).
 */
class SuperAdminReferralTest extends TestCase
{
    use BrixTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 12:00:00');
        $this->migrateReferralTables();
        $this->createBrixScaffoldTables();
        $this->createFinanceScaffoldTables();
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

    private function agency(string $name, float $rate = 30.0): Partner
    {
        return Partner::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name).uniqid(), 'owner_name' => 'O', 'owner_email' => uniqid().'@example.com', 'commission_rate' => $rate]);
    }

    private function referredLeadWithRevenue(Partner $agency, string $shop, float $amount, string $ref): Lead
    {
        $link = TrackingLink::create(['agency_id' => $agency->id, 'name' => "{$agency->name} link", 'campaign_name' => 'Fall Push', 'channel' => 'Website', 'destination_url' => 'x']);
        $store = Store::create(['agency_id' => $agency->id, 'shop_domain' => $shop, 'store_name' => $shop, 'installation_status' => 'INSTALLED', 'installed_at' => now()->subDay(), 'plan' => 'Pro']);
        $lead = Lead::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'store_id' => $store->id, 'shop_domain' => $shop, 'lead_stage' => Lead::STAGE_ACTIVE, 'brix_status' => 'ACTIVE', 'installed_at' => now()->subDay(), 'activated_at' => now()]);

        $this->seedOrderOverage($shop, $amount, 'charged', $ref, now()->subHours(2));
        app(\App\Services\Referral\Commission\ReferralCommissionService::class)->accrue(true);

        return $lead;
    }

    public function test_super_admin_sees_leads_from_every_agency_and_can_filter_by_one(): void
    {
        $a = $this->agency('Agency Alpha');
        $b = $this->agency('Agency Beta');
        $this->referredLeadWithRevenue($a, 'alpha-shop.myshopify.com', 10.00, 'gid://x/a');
        $this->referredLeadWithRevenue($b, 'beta-shop.myshopify.com', 20.00, 'gid://x/b');

        $this->loginAsAdmin();

        $this->get('/admin/leads')->assertOk()->assertSee('alpha-shop.myshopify.com')->assertSee('beta-shop.myshopify.com')
            ->assertSee('Agency Alpha')->assertSee('Agency Beta');

        $this->get('/admin/leads?agency='.$a->id)->assertOk()->assertSee('alpha-shop.myshopify.com')->assertDontSee('beta-shop.myshopify.com');
    }

    public function test_super_admin_lead_detail_shows_revenue_and_commission(): void
    {
        $agency = $this->agency('Agency Alpha', 25);
        $lead = $this->referredLeadWithRevenue($agency, 'alpha-shop.myshopify.com', 10.00, 'gid://x/a');

        $this->loginAsAdmin();

        $this->get("/admin/leads/{$lead->id}")->assertOk()
            ->assertSee('alpha-shop.myshopify.com')
            ->assertSee('$10.00')
            ->assertSee('$2.50');
    }

    public function test_super_admin_revenue_and_commissions_pages_show_every_agency(): void
    {
        $a = $this->agency('Agency Alpha');
        $b = $this->agency('Agency Beta');
        $this->referredLeadWithRevenue($a, 'alpha-shop.myshopify.com', 10.00, 'gid://x/a');
        $this->referredLeadWithRevenue($b, 'beta-shop.myshopify.com', 20.00, 'gid://x/b');

        $this->loginAsAdmin();

        $this->get('/admin/revenue')->assertOk()->assertSee('Agency Alpha')->assertSee('Agency Beta');
        $this->get('/admin/commissions')->assertOk()->assertSee('Agency Alpha')->assertSee('Agency Beta');
        $this->get('/admin/referral-links')->assertOk()->assertSee('Agency Alpha')->assertSee('Agency Beta');
        $this->get('/admin/tracking')->assertOk();
    }

    public function test_revenue_and_commissions_never_double_count_across_agencies(): void
    {
        $a = $this->agency('Agency Alpha', 30);
        $b = $this->agency('Agency Beta', 30);
        $this->referredLeadWithRevenue($a, 'alpha-shop.myshopify.com', 10.00, 'gid://x/a');
        $this->referredLeadWithRevenue($b, 'beta-shop.myshopify.com', 20.00, 'gid://x/b');

        $this->assertSame(30.0, round((float) ReferralRevenueEvent::sum('revenue_amount'), 2));
        $this->assertSame(9.0, round((float) ReferralCommission::sum('commission_amount'), 2));
    }

    public function test_no_referral_data_exists_before_any_agency_activity_admin_pages_render_empty(): void
    {
        $this->loginAsAdmin();

        $this->get('/admin/leads')->assertOk()->assertSee('No leads found');
        $this->get('/admin/revenue')->assertOk()->assertSee('No revenue events found');
        $this->get('/admin/commissions')->assertOk()->assertSee('No commissions found');
        $this->get('/admin/referral-links')->assertOk()->assertSee('No referral links found');
    }

    public function test_admin_payout_show_lists_claimed_referral_commissions(): void
    {
        $agency = $this->agency('Agency Alpha', 20);
        $this->referredLeadWithRevenue($agency, 'alpha-shop.myshopify.com', 100.00, 'gid://x/a');

        $payout = \App\Models\Payout::create([
            'agency_id' => $agency->id, 'amount' => 20.00, 'currency' => 'USD',
            'status' => \App\Models\Payout::STATUS_PENDING, 'requested_at' => now(),
        ]);
        $commission = ReferralCommission::first();
        $payout->referralCommissions()->attach($commission->id, ['amount' => $commission->commission_amount]);
        $commission->update(['status' => ReferralCommission::STATUS_IN_PAYOUT]);

        $this->loginAsAdmin();

        $this->get("/admin/payouts/{$payout->id}")->assertOk()
            ->assertSee('Claimed Referral Commissions')
            ->assertSee('REF-'.$commission->id);
    }

    public function test_unauthenticated_requests_are_redirected_to_admin_login(): void
    {
        $this->get('/admin/leads')->assertRedirect(route('admin.login'));
        $this->get('/admin/revenue')->assertRedirect(route('admin.login'));
    }
}
