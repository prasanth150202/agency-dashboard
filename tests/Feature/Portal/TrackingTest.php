<?php

namespace Tests\Feature\Portal;

use App\Models\Referral\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Services\Referral\ReferralFunnel;
use Illuminate\Support\Carbon;
use Tests\Support\PartnerPortalTestbed;
use Tests\TestCase;

/** Phase 3: referral tracking / funnel. */
class TrackingTest extends TestCase
{
    use PartnerPortalTestbed;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 12:00:00');
        $this->bootPortal();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function link(array $t, string $name, string $channel = 'Instagram'): TrackingLink
    {
        return TrackingLink::create(['agency_id' => $t['agency']->id, 'name' => $name, 'channel' => $channel, 'destination_url' => 'https://apps.shopify.com/thebrix-io']);
    }

    private function click(TrackingLink $l, $at, ?string $shop = null): void
    {
        ReferralClick::create(['agency_id' => $l->agency_id, 'tracking_link_id' => $l->id, 'referral_code' => $l->code,
            'session_id' => uniqid(), 'shop_domain' => $shop, 'created_at' => $at]);
    }

    public function test_empty_state_for_agency_without_links(): void
    {
        $this->actAs($this->makeTenant('Empty'));

        $this->get('/tracking')->assertOk()->assertSee('Nothing to track yet');
    }

    public function test_funnel_counts_are_exact_and_period_bounded(): void
    {
        $t = $this->makeTenant('Agency A');
        $link = $this->link($t, 'Insta');

        $this->click($link, now()->subDays(2), 'a.myshopify.com');
        $this->click($link, now()->subDays(3));
        $this->click($link, now()->subDays(60)); // outside the 30-day default

        $active = Lead::create(['agency_id' => $t['agency']->id, 'tracking_link_id' => $link->id, 'shop_domain' => 'a.myshopify.com',
            'first_clicked_at' => now()->subDays(2), 'installed_at' => now()->subDay(), 'activated_at' => now()]);
        Lead::create(['agency_id' => $t['agency']->id, 'tracking_link_id' => $link->id, 'shop_domain' => 'b.myshopify.com',
            'first_clicked_at' => now()->subDays(3), 'installed_at' => now()->subDay()]);
        Lead::create(['agency_id' => $t['agency']->id, 'tracking_link_id' => $link->id, 'shop_domain' => 'old.myshopify.com',
            'first_clicked_at' => now()->subDays(60), 'installed_at' => now()->subDays(59)]);

        ReferralRevenueEvent::create(['agency_id' => $t['agency']->id, 'store_id' => 1, 'lead_id' => $active->id, 'shop_domain' => 'a.myshopify.com',
            'revenue_type' => 'usage', 'source' => 's', 'external_event_id' => 'e1', 'revenue_amount' => '10.00', 'currency' => 'USD', 'occurred_at' => now()]);

        $rows = (new ReferralFunnel($t['agency']->id))->byLink(now()->subDays(29)->startOfDay());
        $this->assertSame(
            ['clicks' => 2, 'with_store' => 1, 'qr_scans' => 0, 'leads' => 2, 'installed' => 2, 'active' => 1, 'earning' => 1],
            ReferralFunnel::totals($rows)
        );
        $this->assertSame(['USD' => 1000], $rows->first()['revenue']);

        $this->actAs($t);
        $this->get('/tracking?range=30')->assertOk()->assertSee('Insta')->assertSee('$10.00');
    }

    public function test_never_shows_another_agencys_data(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $mine = $this->link($a, 'Alpha Link');
        $theirs = $this->link($b, 'Beta Link');
        $this->click($theirs, now()->subDay());
        $lead = Lead::create(['agency_id' => $b['agency']->id, 'tracking_link_id' => $theirs->id, 'shop_domain' => 'b.myshopify.com', 'first_clicked_at' => now()->subDay()]);
        $event = ReferralRevenueEvent::create(['agency_id' => $b['agency']->id, 'store_id' => 2, 'lead_id' => $lead->id, 'shop_domain' => 'b.myshopify.com',
            'revenue_type' => 'usage', 'source' => 's', 'external_event_id' => 'e2', 'revenue_amount' => '99.00', 'currency' => 'USD', 'occurred_at' => now()]);
        ReferralCommission::create(['revenue_event_id' => $event->id, 'agency_id' => $b['agency']->id, 'store_id' => 2, 'lead_id' => $lead->id, 'tracking_link_id' => $theirs->id,
            'revenue_type' => 'usage', 'revenue_amount' => '99.00', 'currency' => 'USD', 'commission_rate' => '30.00', 'rate_source' => 'agency_default',
            'commission_amount' => '29.70', 'status' => 'pending']);

        $this->actAs($a);
        $this->get('/tracking?agency_id='.$b['agency']->id)->assertOk()
            ->assertSee('Alpha Link')->assertDontSee('Beta Link')->assertDontSee('$99.00')->assertDontSee('$29.70');
        $this->assertSame(0, ReferralFunnel::totals((new ReferralFunnel($a['agency']->id))->byLink(now()->subDays(29)))['clicks']);
    }

    public function test_rates_never_divide_by_zero_and_range_is_whitelisted(): void
    {
        $this->assertNull(ReferralFunnel::rate(0, 0));
        $this->assertSame(50, ReferralFunnel::rate(1, 2));

        $t = $this->makeTenant('Agency A');
        $this->link($t, 'Insta');
        $this->actAs($t);

        $this->get('/tracking?range=9999')->assertOk()->assertSee('last 30 days');
        $this->get('/tracking?range=7')->assertOk()->assertSee('last 7 days');
    }

    public function test_daily_clicks_are_zero_filled_over_the_period(): void
    {
        $t = $this->makeTenant('Agency A');
        $link = $this->link($t, 'Insta');
        $this->click($link, now()->subDays(1));
        $this->click($link, now()->subDays(1));

        $series = (new ReferralFunnel($t['agency']->id))->dailyClicks(now()->subDays(6)->startOfDay());

        $this->assertCount(7, $series);
        $this->assertSame(2, $series['2026-09-20']);
        $this->assertSame(0, $series['2026-09-21']);
    }
}
