<?php

namespace Tests\Feature\Portal;

use App\Models\Commission;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralRevenueEvent;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use App\Services\Finance\LedgerEntry;
use App\Services\Finance\UnifiedCommissionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\PartnerPortalTestbed;
use Tests\TestCase;

/** Phase 8: one commission view over the legacy and referral ledgers. */
class UnifiedCommissionsTest extends TestCase
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

    private function store(array $t, string $shop): Store
    {
        return Store::create(['agency_id' => $t['agency']->id, 'shop_domain' => $shop, 'store_name' => "Store {$shop}", 'installation_status' => 'INSTALLED']);
    }

    private function legacy(array $t, Store $store, string $commission, string $status = 'available', string $gross = '100.00', ?Carbon $availableAt = null): Commission
    {
        return Commission::create([
            'agency_id' => $t['agency']->id, 'store_id' => $store->id, 'gross_amount' => $gross, 'commission_rate' => '30.00',
            'agency_commission' => $commission, 'brix_revenue' => '0', 'commission_status' => $status,
            'available_at' => $availableAt ?? now()->subDay(), 'status' => 'success', 'created_at' => now()->subDays(3),
        ]);
    }

    private function referral(array $t, Store $store, string $commission, string $status = 'pending', string $revenue = '10.00', string $currency = 'USD', ?Carbon $availableAt = null, ?TrackingLink $link = null): ReferralCommission
    {
        $link ??= TrackingLink::create(['agency_id' => $t['agency']->id, 'name' => 'Poster', 'channel' => 'Other', 'destination_url' => 'https://apps.shopify.com/thebrix-io']);
        $lead = Lead::firstOrCreate(['agency_id' => $t['agency']->id, 'shop_domain' => $store->shop_domain], ['tracking_link_id' => $link->id, 'store_id' => $store->id]);
        $event = ReferralRevenueEvent::create([
            'agency_id' => $t['agency']->id, 'store_id' => $store->id, 'lead_id' => $lead->id, 'shop_domain' => $store->shop_domain,
            'revenue_type' => 'usage', 'source' => 's', 'external_event_id' => uniqid('e'), 'revenue_amount' => $revenue,
            'currency' => $currency, 'occurred_at' => now()->subDays(2),
        ]);

        return ReferralCommission::create([
            'revenue_event_id' => $event->id, 'agency_id' => $t['agency']->id, 'store_id' => $store->id, 'lead_id' => $lead->id,
            'tracking_link_id' => $link->id, 'revenue_type' => 'usage', 'revenue_amount' => $revenue, 'currency' => $currency,
            'commission_rate' => '30.00', 'rate_source' => 'agency_default', 'commission_amount' => $commission,
            'status' => $status, 'available_at' => $availableAt ?? now()->subDay(),
        ]);
    }

    public function test_page_with_only_legacy_commissions_still_works(): void
    {
        $t = $this->makeTenant('Agency A');
        $this->legacy($t, $this->store($t, 'a.myshopify.com'), '30.00');
        $this->actAs($t);

        $this->get('/earnings')->assertOk()->assertSee('TXN-1')->assertSee('Store a.myshopify.com')->assertSee('₹30.00')->assertDontSee('REF-');
    }

    public function test_referral_commissions_appear_next_to_legacy_ones_with_their_source(): void
    {
        $t = $this->makeTenant('Agency A');
        $store = $this->store($t, 'a.myshopify.com');
        $this->legacy($t, $store, '30.00');
        $this->referral($t, $store, '3.00');
        $this->actAs($t);

        $this->get('/earnings')->assertOk()
            ->assertSee('TXN-1')->assertSee('REF-1')
            ->assertSee('Referral')->assertSee('Store')
            ->assertSee('₹30.00')->assertSee('$3.00')
            ->assertSee('Eligible'); // pending + holding period lifted
    }

    public function test_summary_keeps_currencies_apart_and_buckets_each_ledger_correctly(): void
    {
        $t = $this->makeTenant('Agency A');
        $store = $this->store($t, 'a.myshopify.com');
        $this->legacy($t, $store, '100.00', 'available');
        $this->legacy($t, $store, '40.00', 'pending', availableAt: now()->addDays(5));
        $this->legacy($t, $store, '25.00', 'paid');
        $this->referral($t, $store, '3.00', 'pending');                              // eligible (holding lifted)
        $this->referral($t, $store, '2.00', 'pending', availableAt: now()->addDay()); // still pending
        $this->referral($t, $store, '4.00', 'paid');
        $this->referral($t, $store, '9.00', 'reversed');                              // never earned

        $summary = (new UnifiedCommissionService($t['org']->fresh()))->summary();

        $this->assertSame(['INR' => 10000, 'USD' => 300], $summary['available']);
        $this->assertSame(['INR' => 4000, 'USD' => 200], $summary['pending']);
        $this->assertSame(['INR' => 2500, 'USD' => 400], $summary['paid']);
        $this->assertSame(['INR' => 16500, 'USD' => 900], $summary['total_earned']); // reversed 9.00 excluded
    }

    public function test_it_reads_both_ledgers_without_copying_or_changing_either(): void
    {
        $t = $this->makeTenant('Agency A');
        $store = $this->store($t, 'a.myshopify.com');
        $this->legacy($t, $store, '30.00');
        $this->referral($t, $store, '3.00');

        $before = [DB::table('transactions')->get()->toJson(), DB::table('referral_commissions')->get()->toJson()];
        $this->actAs($t);
        $this->get('/earnings')->assertOk();
        $this->get('/earnings/export')->assertOk();

        $this->assertSame($before, [DB::table('transactions')->get()->toJson(), DB::table('referral_commissions')->get()->toJson()]);
        $this->assertSame(1, DB::table('transactions')->count());
        $this->assertSame(1, DB::table('referral_commissions')->count());
    }

    public function test_referral_entries_stay_traceable_to_event_lead_store_agency_and_link(): void
    {
        $t = $this->makeTenant('Agency A');
        $store = $this->store($t, 'a.myshopify.com');
        $rc = $this->referral($t, $store, '3.00');

        $entry = (new UnifiedCommissionService($t['org']->fresh()))->entries()->firstWhere('source', LedgerEntry::SOURCE_REFERRAL);

        $this->assertSame($rc->id, $entry->id);
        $this->assertSame($store->id, $entry->storeId);
        $this->assertSame('Poster', $entry->via);
        $this->assertSame('USD', $entry->currency);
        $this->assertNotNull($rc->revenue_event_id);
        $this->assertSame($t['agency']->id, $rc->agency_id);
        $this->assertNotNull($rc->lead_id);
        $this->assertNotNull($rc->tracking_link_id);
    }

    public function test_filters_by_source_status_store_and_search(): void
    {
        $t = $this->makeTenant('Agency A');
        $one = $this->store($t, 'one.myshopify.com');
        $two = $this->store($t, 'two.myshopify.com');
        $this->legacy($t, $one, '30.00', 'paid');
        $this->referral($t, $two, '3.00');
        $this->actAs($t);

        $this->get('/earnings?source=referral')->assertSee('REF-1')->assertDontSee('TXN-1');
        $this->get('/earnings?source=store')->assertSee('TXN-1')->assertDontSee('REF-1');
        $this->get('/earnings?status=paid')->assertSee('TXN-1')->assertDontSee('REF-1');
        $this->get('/earnings?status=eligible')->assertSee('REF-1')->assertDontSee('TXN-1');
        $this->get('/earnings?store='.$two->id)->assertSee('REF-1')->assertDontSee('TXN-1');
        $this->get('/earnings?search=REF-1')->assertSee('REF-1')->assertDontSee('TXN-1');
        $this->get('/earnings?search=one.myshopify')->assertSee('TXN-1')->assertDontSee('REF-1');
        $this->get('/earnings?source=bogus')->assertOk()->assertSee('TXN-1')->assertSee('REF-1');
    }

    public function test_never_shows_another_agencys_commissions(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $this->referral($a, $this->store($a, 'a.myshopify.com'), '3.00');
        $bStore = $this->store($b, 'b.myshopify.com');
        $this->legacy($b, $bStore, '777.00');
        $this->referral($b, $bStore, '55.00');

        $this->actAs($a);
        $this->get('/earnings?agency_id='.$b['agency']->id)->assertOk()
            ->assertSee('a.myshopify.com')->assertDontSee('b.myshopify.com')->assertDontSee('777.00')->assertDontSee('$55.00');
        $this->get('/earnings/export')->assertOk()->assertDontSee('777.00')->assertDontSee('55.00');
    }

    public function test_export_lists_both_sources_with_currency(): void
    {
        $t = $this->makeTenant('Agency A');
        $store = $this->store($t, 'a.myshopify.com');
        $this->legacy($t, $store, '30.00');
        $this->referral($t, $store, '3.00');
        $this->actAs($t);

        $csv = $this->get('/earnings/export')->assertOk()->streamedContent();

        $this->assertStringContainsString('"Order ID",Source,Store,"Order Amount","Commission Rate",Commission,Currency,Date,Status', $csv);
        $this->assertStringContainsString('TXN-1,Store,', $csv);
        $this->assertStringContainsString('REF-1,Referral,', $csv);
        $this->assertStringContainsString(',3.00,USD,', $csv);
        $this->assertStringContainsString(',30.00,INR,', $csv);
    }

    public function test_empty_state_shows_zero_in_the_organisation_currency(): void
    {
        $this->actAs($this->makeTenant('Empty'));

        $this->get('/earnings')->assertOk()->assertSee('No commissions yet')->assertSee('₹0.00');
    }
}
