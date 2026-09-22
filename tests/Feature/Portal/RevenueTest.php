<?php

namespace Tests\Feature\Portal;

use App\Models\Commission;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use App\Services\Referral\ReferralReporting;
use Illuminate\Support\Carbon;
use Tests\Support\PartnerPortalTestbed;
use Tests\TestCase;

/** Phase 7: the Revenue page. */
class RevenueTest extends TestCase
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

    private function event(array $t, TrackingLink $link, string $ext, string $amount, string $type = 'usage', string $currency = 'USD', $at = null, ?string $commission = null): ReferralRevenueEvent
    {
        $lead = Lead::firstOrCreate(
            ['agency_id' => $t['agency']->id, 'shop_domain' => "{$ext}.myshopify.com"],
            ['tracking_link_id' => $link->id, 'first_clicked_at' => now()->subDays(40)]
        );
        $event = ReferralRevenueEvent::create([
            'agency_id' => $t['agency']->id, 'store_id' => $lead->id, 'lead_id' => $lead->id, 'shop_domain' => $lead->shop_domain,
            'revenue_type' => $type, 'source' => 's', 'external_event_id' => $ext, 'revenue_amount' => $amount,
            'currency' => $currency, 'occurred_at' => $at ?? now()->subDays(2),
        ]);

        if ($commission !== null) {
            ReferralCommission::create([
                'revenue_event_id' => $event->id, 'agency_id' => $t['agency']->id, 'store_id' => $lead->id, 'lead_id' => $lead->id,
                'tracking_link_id' => $link->id, 'revenue_type' => $type, 'revenue_amount' => $amount, 'currency' => $currency,
                'commission_rate' => '30.00', 'rate_source' => 'agency_default', 'commission_amount' => $commission, 'status' => 'pending',
            ]);
        }

        return $event;
    }

    private function link(array $t, string $name = 'Poster'): TrackingLink
    {
        return TrackingLink::create(['agency_id' => $t['agency']->id, 'name' => $name, 'channel' => 'Other', 'destination_url' => 'https://apps.shopify.com/thebrix-io']);
    }

    public function test_empty_state(): void
    {
        $this->actAs($this->makeTenant('Empty'));

        $this->get('/revenue')->assertOk()->assertSee('No verified revenue');
    }

    public function test_legacy_transactions_never_count_as_referral_revenue(): void
    {
        $t = $this->makeTenant('Agency A');
        $store = Store::create(['agency_id' => $t['agency']->id, 'shop_domain' => 'a.myshopify.com', 'store_name' => 'A', 'installation_status' => 'INSTALLED']);
        Commission::create([
            'agency_id' => $t['agency']->id, 'store_id' => $store->id, 'gross_amount' => '5000.00', 'commission_rate' => '30.00',
            'agency_commission' => '1500.00', 'brix_revenue' => '3500.00', 'commission_status' => 'available',
            'available_at' => now()->subDay(), 'status' => 'success', 'created_at' => now()->subDay(),
        ]);

        $this->actAs($t);
        $this->get('/revenue?range=all')->assertOk()->assertSee('No verified revenue')->assertDontSee('5,000.00')->assertDontSee('1,500.00');
    }

    public function test_totals_come_from_events_and_commission_matches_the_same_events(): void
    {
        $t = $this->makeTenant('Agency A');
        $link = $this->link($t);
        $this->event($t, $link, 'a', '10.00', 'usage', 'USD', null, '3.00');
        $this->event($t, $link, 'b', '5.50', 'usage', 'USD', null, '1.65');
        $this->event($t, $link, 'c', '999.00', 'usage', 'USD', now()->subYears(2), '299.70'); // outside the 90d default

        $this->actAs($t);
        $this->get('/revenue')->assertOk()->assertSee('$15.50')->assertSee('$4.65')->assertDontSee('$999.00')->assertDontSee('$1,014.50');
        $this->get('/revenue?range=all')->assertOk()->assertSee('$1,014.50');
    }

    public function test_currencies_are_never_added_together(): void
    {
        $t = $this->makeTenant('Agency A');
        $link = $this->link($t);
        $this->event($t, $link, 'a', '10.00', 'usage', 'USD');
        $this->event($t, $link, 'b', '500.00', 'usage', 'INR');

        $reporting = new ReferralReporting($t['agency']->id);
        $this->assertSame(['INR' => 50000, 'USD' => 1000], $reporting->revenueFor($reporting->eventsQuery()));

        $this->actAs($t);
        $this->get('/revenue')->assertOk()->assertSee('$10.00')->assertSee('₹500.00');
    }

    public function test_filters_by_type_and_link(): void
    {
        $t = $this->makeTenant('Agency A');
        $one = $this->link($t, 'Poster One');
        $two = $this->link($t, 'Poster Two');
        $this->event($t, $one, 'a', '10.00', 'usage');
        $this->event($t, $two, 'b', '20.00', 'subscription');

        $this->actAs($t);
        $this->get('/revenue?type=subscription')->assertSee('b.myshopify.com')->assertDontSee('a.myshopify.com');
        $this->get('/revenue?link='.$one->id)->assertSee('a.myshopify.com')->assertDontSee('b.myshopify.com');
        $this->get('/revenue?type=bogus')->assertOk()->assertSee('a.myshopify.com')->assertSee('b.myshopify.com');
    }

    public function test_never_shows_another_agencys_revenue_even_with_forged_params(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $mine = $this->link($a, 'Mine');
        $theirs = $this->link($b, 'Theirs');
        $this->event($a, $mine, 'mine', '10.00', 'usage', 'USD', null, '3.00');
        $this->event($b, $theirs, 'theirs', '777.00', 'usage', 'USD', null, '233.10');

        $this->actAs($a);
        $this->get('/revenue')->assertOk()->assertSee('mine.myshopify.com')->assertDontSee('theirs.myshopify.com')->assertDontSee('$777.00')->assertDontSee('$233.10');
        // Another agency's link id and agency_id in the query narrow to nothing / are ignored.
        $this->get('/revenue?link='.$theirs->id.'&agency_id='.$b['agency']->id)->assertOk()
            ->assertDontSee('theirs.myshopify.com')->assertDontSee('$777.00');
    }

    public function test_monthly_breakdown_is_bucketed_by_month(): void
    {
        $t = $this->makeTenant('Agency A');
        $link = $this->link($t);
        $this->event($t, $link, 'a', '10.00', 'usage', 'USD', Carbon::parse('2026-08-15'), '3.00');
        $this->event($t, $link, 'b', '4.00', 'usage', 'USD', Carbon::parse('2026-09-02'), '1.20');

        $this->actAs($t);
        $html = $this->get('/revenue')->assertOk()->assertSeeInOrder(['Aug 2026', '$10.00', '$3.00', 'Sep 2026', '$4.00', '$1.20'])->getContent();
        $this->assertStringContainsString('Last 6 months', $html);
    }
}
