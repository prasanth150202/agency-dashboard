<?php

namespace Tests\Feature\Portal;

use App\Models\Referral\Lead;
use App\Models\Referral\LeadEvent;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use App\Services\Analytics\AgencyAnalytics;
use App\Services\Referral\ReferralQr;
use App\Support\AnalyticsPeriod;
use Illuminate\Support\Carbon;
use Tests\Support\PartnerPortalTestbed;
use Tests\TestCase;

/** The interactive analytics layer: every number traceable to a real row, per agency. */
class AnalyticsDashboardTest extends TestCase
{
    use PartnerPortalTestbed;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-22 12:00:00');
        $this->bootPortal();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function link(array $t, string $name = 'Insta'): TrackingLink
    {
        return TrackingLink::create(['agency_id' => $t['agency']->id, 'name' => $name, 'channel' => 'Instagram', 'destination_url' => 'https://apps.shopify.com/x']);
    }

    private function click(TrackingLink $link, $at, ?string $shop = null, ?string $source = null): ReferralClick
    {
        $click = ReferralClick::create(['agency_id' => $link->agency_id, 'tracking_link_id' => $link->id, 'referral_code' => $link->code,
            'session_id' => uniqid(), 'shop_domain' => $shop, 'source' => $source]);
        $click->forceFill(['created_at' => $at])->save();

        return $click;
    }

    private function lead(array $t, ?TrackingLink $link, string $shop, array $extra = []): Lead
    {
        $lead = Lead::create(array_merge(['agency_id' => $t['agency']->id, 'tracking_link_id' => $link?->id, 'shop_domain' => $shop], $extra));

        if (isset($extra['created_at'])) {
            $lead->forceFill(['created_at' => $extra['created_at']])->save();
        }

        return $lead;
    }

    private function revenue(array $t, Lead $lead, string $amount, $at, string $currency = 'USD', ?string $commission = null): ReferralRevenueEvent
    {
        $event = ReferralRevenueEvent::create(['agency_id' => $t['agency']->id, 'store_id' => 1, 'lead_id' => $lead->id, 'shop_domain' => $lead->shop_domain,
            'revenue_type' => 'usage', 'source' => 'shopify_usage_record', 'external_event_id' => uniqid(), 'revenue_amount' => $amount,
            'currency' => $currency, 'occurred_at' => $at]);

        if ($commission) {
            $c = ReferralCommission::create(['revenue_event_id' => $event->id, 'agency_id' => $t['agency']->id, 'store_id' => 1, 'lead_id' => $lead->id,
                'tracking_link_id' => $lead->tracking_link_id, 'revenue_type' => 'usage', 'revenue_amount' => $amount, 'currency' => $currency,
                'commission_rate' => '30.00', 'rate_source' => 'agency_default', 'commission_amount' => $commission, 'status' => 'pending']);
            $c->forceFill(['created_at' => $at])->save();
        }

        return $event;
    }

    /** A realistic agency: clicks, leads at each stage, revenue and commission. */
    private function seedAgency(array $t): TrackingLink
    {
        $link = $this->link($t);

        $this->click($link, now()->subDays(2), 'a.myshopify.com');
        $this->click($link, now()->subDays(3));
        $this->click($link, now()->subDays(4), null, ReferralClick::SOURCE_QR);
        $this->click($link, now()->subDays(45)); // previous 30-day window

        $active = $this->lead($t, $link, 'a.myshopify.com', ['lead_stage' => Lead::STAGE_ACTIVE, 'brix_status' => 'ACTIVE',
            'installed_at' => now()->subDays(2), 'activated_at' => now()->subDay(), 'created_at' => now()->subDays(2)]);
        $this->lead($t, $link, 'b.myshopify.com', ['lead_stage' => Lead::STAGE_INSTALLED, 'brix_status' => 'INSTALLED',
            'installed_at' => now()->subDays(3), 'created_at' => now()->subDays(3)]);
        $this->lead($t, null, 'c.myshopify.com', ['source' => Lead::SOURCE_MANUAL, 'created_at' => now()->subDays(5)]);
        $this->lead($t, $link, 'old.myshopify.com', ['created_at' => now()->subDays(40)]); // previous window

        Store::create(['agency_id' => $t['agency']->id, 'shop_domain' => 'a.myshopify.com', 'store_name' => 'A', 'status' => 'active',
            'installation_status' => 'INSTALLED', 'installed_at' => now()->subDays(2)]);

        $this->revenue($t, $active, '100.00', now()->subDay(), 'USD', '30.00');
        $this->revenue($t, $active, '50.00', now()->subDays(40), 'USD', '15.00'); // previous window

        return $link;
    }

    public function test_dashboard_kpis_come_from_real_rows_with_honest_trends(): void
    {
        $t = $this->makeTenant('Agency A');
        $this->seedAgency($t);
        $this->actAs($t);

        $response = $this->get('/dashboard')->assertOk();
        $kpis = $response->viewData('kpis');

        $this->assertSame(3, $kpis['leads']['value']);            // created in the last 30 days
        $this->assertSame(200.0, $kpis['leads']['change']);         // 3 vs 1 in the previous 30 days
        $this->assertSame(1, $kpis['installed']['value']);
        $this->assertNull($kpis['installed']['change']);            // no base to compare against — never "+100%"
        $this->assertSame(1, $kpis['active']['value']);
        $this->assertNull($kpis['active']['change']);               // snapshot, no history
        $this->assertSame(['USD' => 10000], $kpis['revenue']['value']);
        $this->assertSame(100.0, $kpis['revenue']['change']);       // $100 vs $50
        $this->assertSame(['USD' => 3000], $kpis['commission']['value']);

        $response->assertSee('$100.00')->assertSee('+100%')->assertSee('vs previous 30 days');
    }

    public function test_funnel_counts_what_happened_in_the_period(): void
    {
        $t = $this->makeTenant('Agency A');
        $link = $this->seedAgency($t);
        $analytics = new AgencyAnalytics($t['org']->fresh());

        $this->assertSame(
            ['clicks' => 3, 'qr_scans' => 1, 'install_started' => 1, 'leads' => 3, 'installed' => 2, 'active' => 1],
            $analytics->funnel(AnalyticsPeriod::make('30d'))
        );
        $this->assertSame(4, $analytics->funnel(AnalyticsPeriod::make('3m'))['clicks']);
        $this->assertSame(2, $analytics->funnel(AnalyticsPeriod::make('30d'), $link->id)['leads']); // manual lead has no link
    }

    public function test_revenue_and_commission_series_sum_to_real_amounts_per_currency(): void
    {
        $t = $this->makeTenant('Agency A');
        $link = $this->seedAgency($t);
        $inr = $this->lead($t, $link, 'inr.myshopify.com', ['created_at' => now()->subDays(6)]);
        $this->revenue($t, $inr, '500.00', now()->subDays(6), 'INR', '150.00');

        $series = (new AgencyAnalytics($t['org']->fresh()))->series(AnalyticsPeriod::make('30d'));
        $sum = fn (string $metric, string $cur) => array_sum(array_map(fn ($b) => $b[$metric][$cur] ?? 0, $series['buckets']));

        $this->assertCount(30, $series['buckets']);
        $this->assertSame(['INR', 'USD'], $series['currencies']);
        $this->assertSame(10000, $sum('revenue', 'USD'));
        $this->assertSame(50000, $sum('revenue', 'INR'));   // never folded into USD
        $this->assertSame(3000, $sum('commission', 'USD'));
        $this->assertSame(4, array_sum(array_column($series['buckets'], 'leads')));

        $this->actAs($t);
        $this->get('/earnings')->assertOk()->assertViewHas('commissionChart', fn ($c) => ! $c['empty']);
    }

    public function test_agency_isolation_across_every_analytics_page(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $this->link($a, 'Alpha Link');
        $theirs = $this->seedAgency($b);
        $theirs->update(['name' => 'Beta Link']);

        $this->actAs($a);

        $kpis = $this->get('/dashboard?agency_id='.$b['agency']->id)->assertOk()->assertDontSee('$100.00')->viewData('kpis');
        $this->assertSame(0, $kpis['leads']['value']);
        $this->assertSame([], $kpis['revenue']['value']);
        $this->assertSame(0, array_sum($this->get('/dashboard')->viewData('funnel')));

        $this->get('/tracking')->assertOk()->assertSee('Alpha Link')->assertDontSee('Beta Link');
        $this->get('/leads?source=referral')->assertOk()->assertDontSee('a.myshopify.com');
        $this->get('/referral-links/'.$theirs->id)->assertForbidden();
        $this->get('/qr?link='.$theirs->id)->assertOk()->assertDontSee('Beta Link');
    }

    public function test_kpis_and_funnel_link_to_matching_filtered_pages(): void
    {
        $t = $this->makeTenant('Agency A');
        $this->seedAgency($t);
        $this->actAs($t);

        $html = $this->get('/dashboard?range=7d')->assertOk()->getContent();

        foreach ([
            route('leads.index', ['range' => '7d']),
            route('stores.index', ['status' => 'active']),
            route('revenue.index', ['range' => '7d']),
            route('leads.index', ['range' => '7d', 'reached' => 'installed']),
            route('leads.index', ['range' => '7d', 'reached' => 'active']),
            route('tracking.index', ['range' => '7d']),
        ] as $href) {
            $this->assertStringContainsString('href="'.e($href).'"', $html, $href);
        }

        // The drill-down lists exactly what the funnel counted.
        $this->get('/leads?range=30d&reached=installed')->assertOk()
            ->assertSee('a.myshopify.com')->assertSee('b.myshopify.com')->assertDontSee('c.myshopify.com')->assertDontSee('old.myshopify.com');
        $this->get('/leads?range=30d&reached=active')->assertOk()->assertSee('a.myshopify.com')->assertDontSee('b.myshopify.com');
    }

    public function test_lead_stage_source_and_date_filters(): void
    {
        $t = $this->makeTenant('Agency A');
        $link = $this->seedAgency($t);
        $this->click($link, now()->subDays(8), 'qr.myshopify.com', ReferralClick::SOURCE_QR);
        $this->lead($t, $link, 'qr.myshopify.com', ['created_at' => now()->subDays(8)]);
        $this->actAs($t);

        $this->get('/leads?stage=ACTIVE')->assertOk()->assertSee('a.myshopify.com')->assertDontSee('b.myshopify.com');
        $this->get('/leads?source=manual')->assertOk()->assertSee('c.myshopify.com')->assertDontSee('a.myshopify.com');
        $this->get('/leads?source=qr')->assertOk()->assertSee('qr.myshopify.com')->assertDontSee('a.myshopify.com');
        $this->get('/leads?source=referral')->assertOk()->assertSee('a.myshopify.com')->assertDontSee('qr.myshopify.com')->assertDontSee('c.myshopify.com');
        $this->get('/leads?range=7d')->assertOk()->assertSee('c.myshopify.com')->assertDontSee('old.myshopify.com')->assertDontSee('qr.myshopify.com');
        $this->get('/leads?range=custom&from=2026-08-01&to=2026-08-20')->assertOk()->assertSee('old.myshopify.com')->assertDontSee('a.myshopify.com');

        $counts = $this->get('/leads?range=7d')->viewData('stageCounts');
        $this->assertSame(3, (int) $counts->sum()); // stage header follows the period
    }

    public function test_empty_agency_shows_empty_states_not_fake_numbers(): void
    {
        $t = $this->makeTenant('Empty Agency');
        $this->actAs($t);

        $response = $this->get('/dashboard')->assertOk()
            ->assertSee('No referral activity yet')->assertSee('Create Referral Link')->assertSee('Add Lead')
            ->assertSee('No revenue or referral activity yet')
            ->assertDontSee('+100%')->assertDontSee('<canvas', false);

        foreach ($response->viewData('kpis') as $kpi) {
            $this->assertNull($kpi['change']);
        }

        $this->get('/tracking')->assertOk()->assertSee('Nothing to track yet');
        $this->get('/revenue')->assertOk()->assertSee('No verified revenue');
    }

    public function test_referral_link_metrics_and_detail_page(): void
    {
        $t = $this->makeTenant('Agency A');
        $link = $this->seedAgency($t);
        $this->actAs($t);

        $row = $this->get('/referral-links')->assertOk()->viewData('links')->getCollection()->firstWhere('id', $link->id);
        $this->assertSame(4, $row->clicks_count);
        $this->assertSame(3, $row->leads_count);
        $this->assertSame(2, $row->installed_count);
        $this->assertSame(['USD' => 15000], $row->revenue);

        $show = $this->get('/referral-links/'.$link->id)->assertOk()->assertSee($link->code)->assertSee('$150.00');
        $this->assertSame(1, $show->viewData('totals')['qr_scans']);
        $this->assertSame(3, $show->viewData('funnel')['clicks']);
    }

    public function test_qr_is_just_the_referral_link_and_campaign_is_never_required(): void
    {
        $t = $this->makeTenant('Agency A');
        $this->actAs($t);

        $this->post('/referral-links', ['name' => 'Poster', 'channel' => 'Other'])->assertSessionHasNoErrors();
        $link = TrackingLink::firstOrFail();
        $this->assertNull($link->campaign_name);

        $this->get('/qr?link='.$link->id)->assertOk()->assertSee(e(ReferralQr::url($link)), false);
        $this->assertSame(url('/ref/'.$link->code).'?src=qr', ReferralQr::url($link));

        $this->get('/ref/'.$link->code.'?src=qr');
        $this->assertSame($link->id, ReferralClick::firstOrFail()->tracking_link_id);
        $this->assertSame(1, $this->get('/referral-links/'.$link->id)->viewData('totals')['qr_scans']);
    }

    public function test_super_admin_sees_leads_across_agencies(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $this->lead($a, $this->link($a), 'alpha.myshopify.com');
        $this->lead($b, $this->link($b), 'beta.myshopify.com');

        $this->loginAsAdmin();
        $this->get('/admin/leads')->assertOk()->assertSee('alpha.myshopify.com')->assertSee('beta.myshopify.com');
    }

    public function test_period_parsing_and_trend_rules(): void
    {
        $this->assertNull(AnalyticsPeriod::change(5, 0));
        $this->assertNull(AnalyticsPeriod::change(5, null));
        $this->assertSame(-50.0, AnalyticsPeriod::change(5, 10));
        $this->assertNull(AgencyAnalytics::moneyChange(['USD' => 100], ['INR' => 100]));

        $request = fn (array $q) => \Illuminate\Http\Request::create('/x', 'GET', $q);
        $this->assertSame('3m', AnalyticsPeriod::fromRequest($request(['range' => '90']))->key);           // legacy alias
        $this->assertSame('30d', AnalyticsPeriod::fromRequest($request(['range' => 'bogus']))->key);
        $this->assertSame('30d', AnalyticsPeriod::fromRequest($request(['range' => 'custom', 'from' => '2026-09-10', 'to' => '2026-09-01']))->key);
        $custom = AnalyticsPeriod::fromRequest($request(['range' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-10']));
        $this->assertSame('2026-08-22', $custom->previous()->from->toDateString());
        $this->assertCount(10, $custom->buckets());
    }
}
