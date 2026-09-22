<?php

namespace Tests\Feature\Referral;

use App\Models\Referral\Lead;
use App\Models\Referral\LeadEvent;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\TrackingLink;
use Tests\TestCase;

/**
 * Deliberately touches only the four new referral tables. agencies/stores/
 * transactions have no create-migration in this repo (they only exist in
 * the real MySQL DB), so RefreshDatabase's full migration chain fails on
 * sqlite :memory: (pre-existing). This test therefore migrates just the
 * referral migrations into the fresh in-memory DB, and agency_id/store_id
 * are plain integers with no dependency on agencies/stores rows.
 */
class ReferralModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/2026_09_21_135019_create_tracking_links_table.php',
                'database/migrations/2026_09_21_135020_create_referral_clicks_table.php',
                'database/migrations/2026_09_21_135021_create_leads_table.php',
                'database/migrations/2026_09_21_135022_create_lead_events_table.php',
                'database/migrations/2026_09_23_100000_add_source_to_referral_clicks_table.php',
            ],
        ])->run();
    }

    private function makeLink(int $agencyId = 1, array $overrides = []): TrackingLink
    {
        return TrackingLink::create(array_merge([
            'agency_id' => $agencyId,
            'name' => 'Instagram Campaign',
            'campaign_name' => 'September Shopify Outreach',
            'channel' => 'Instagram',
            'destination_url' => 'https://apps.shopify.com/thebrix-io',
        ], $overrides));
    }

    public function test_tracking_link_is_created_with_defaults(): void
    {
        $link = $this->makeLink(7, ['notes' => 'Bio link']);

        $this->assertDatabaseHas('tracking_links', [
            'id' => $link->id,
            'agency_id' => 7,
            'name' => 'Instagram Campaign',
            'campaign_name' => 'September Shopify Outreach',
            'channel' => 'Instagram',
            'destination_url' => 'https://apps.shopify.com/thebrix-io',
            'status' => 'ACTIVE',
        ]);
        $this->assertMatchesRegularExpression('/^BRIX-[A-Z0-9]{4}$/', $link->code);
        $this->assertSame(url('/ref/'.$link->code), $link->referral_url);
    }

    public function test_referral_codes_are_unique(): void
    {
        $codes = [];
        for ($i = 0; $i < 25; $i++) {
            $codes[] = $this->makeLink()->code;
        }

        $this->assertCount(25, array_unique($codes));

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->makeLink(1, ['code' => $codes[0]]);
    }

    public function test_generate_unique_code_skips_existing_codes(): void
    {
        $existing = $this->makeLink();

        $this->assertNotSame($existing->code, TrackingLink::generateUniqueCode());
    }

    public function test_status_can_be_toggled_and_filtered(): void
    {
        $active = $this->makeLink();
        $inactive = $this->makeLink(1, ['status' => TrackingLink::STATUS_INACTIVE]);

        $this->assertTrue($active->is_active);
        $this->assertFalse($inactive->is_active);

        $active->update(['status' => TrackingLink::STATUS_INACTIVE]);
        $this->assertFalse($active->fresh()->is_active);

        $inactive->update(['status' => TrackingLink::STATUS_ACTIVE]);
        $this->assertSame([$inactive->id], TrackingLink::status('ACTIVE')->pluck('id')->all());
        $this->assertSame([$active->id], TrackingLink::status('INACTIVE')->pluck('id')->all());
        $this->assertSame(['ACTIVE', 'INACTIVE'], TrackingLink::STATUSES);
    }

    public function test_agencies_only_see_their_own_links_and_leads(): void
    {
        $mine = $this->makeLink(1);
        $theirs = $this->makeLink(2);
        Lead::create(['agency_id' => 1, 'tracking_link_id' => $mine->id]);
        Lead::create(['agency_id' => 2, 'tracking_link_id' => $theirs->id]);

        $this->assertSame([$mine->id], TrackingLink::where('agency_id', 1)->pluck('id')->all());
        $this->assertSame([$theirs->id], TrackingLink::where('agency_id', 2)->pluck('id')->all());
        $this->assertCount(1, Lead::forAgency(1)->get());
        $this->assertCount(1, Lead::forAgency(2)->get());
    }

    public function test_referral_click_supports_nullable_shop_domain(): void
    {
        $link = $this->makeLink(3);

        $click = ReferralClick::create([
            'agency_id' => 3,
            'tracking_link_id' => $link->id,
            'referral_code' => $link->code,
            'session_id' => 'sess-123',
            'referrer' => 'https://instagram.com',
        ]);

        $this->assertNull($click->shop_domain);
        $this->assertNotNull($click->created_at);
        $this->assertTrue($click->trackingLink->is($link));
        $this->assertSame(1, $link->clicks()->count());

        $click->update(['shop_domain' => 'demo.myshopify.com']);
        $this->assertSame('demo.myshopify.com', $click->fresh()->shop_domain);
    }

    public function test_lead_model_keeps_brix_status_separate_from_lead_stage(): void
    {
        $link = $this->makeLink(4);

        $lead = Lead::create([
            'agency_id' => 4,
            'tracking_link_id' => $link->id,
            'store_id' => 99,
            'shop_domain' => 'demo.myshopify.com',
            'brix_status' => 'INSTALLED',
            'first_clicked_at' => now(),
        ]);

        $this->assertSame(Lead::STAGE_NEW, $lead->fresh()->lead_stage);
        $this->assertSame('INSTALLED', $lead->brix_status);

        $lead->update(['lead_stage' => Lead::STAGE_CONTACTED, 'contacted_at' => now()]);
        $this->assertSame('INSTALLED', $lead->fresh()->brix_status);
        $this->assertNotNull($lead->fresh()->contacted_at);
        $this->assertTrue($lead->trackingLink->is($link));
    }

    public function test_one_lead_per_shop_domain(): void
    {
        Lead::create(['agency_id' => 1, 'shop_domain' => 'demo.myshopify.com']);
        Lead::create(['agency_id' => 1]);
        Lead::create(['agency_id' => 1]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Lead::create(['agency_id' => 2, 'shop_domain' => 'demo.myshopify.com']);
    }

    public function test_lead_stage_values(): void
    {
        $this->assertSame([
            'NEW', 'CONTACTED', 'INTERESTED', 'INSTALL_STARTED',
            'INSTALLED', 'ACTIVE', 'NOT_INTERESTED', 'LOST',
        ], Lead::STAGES);
    }

    public function test_lead_event_creation(): void
    {
        $lead = Lead::create(['agency_id' => 1]);

        $event = $lead->events()->create([
            'event_type' => LeadEvent::CLICKED,
            'metadata' => ['session_id' => 'sess-123'],
        ]);

        $this->assertNotNull($event->created_at);
        $this->assertSame(['session_id' => 'sess-123'], $event->fresh()->metadata);
        $this->assertTrue($event->lead->is($lead));
        $this->assertSame(
            ['CLICKED', 'INSTALL_STARTED', 'INSTALLED', 'ACTIVATED', 'REVENUE_GENERATED', 'CHURNED'],
            LeadEvent::TYPES
        );
    }

    public function test_public_referral_url_records_click_and_continues_to_the_store_step(): void
    {
        $link = $this->makeLink(5, ['destination_url' => 'https://apps.shopify.com/thebrix-io']);

        $location = $this->get('/ref/'.$link->code, ['referer' => 'https://instagram.com'])
            ->assertRedirectContains('/referral/store/')
            ->headers->get('Location');

        $this->post($location, ['shop_domain' => 'demo.myshopify.com'])
            ->assertRedirect('https://apps.shopify.com/thebrix-io');

        $this->assertDatabaseHas('referral_clicks', [
            'agency_id' => 5,
            'tracking_link_id' => $link->id,
            'referral_code' => $link->code,
            'referrer' => 'https://instagram.com',
            'shop_domain' => 'demo.myshopify.com',
        ]);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_inactive_or_unknown_referral_code_is_not_tracked(): void
    {
        $link = $this->makeLink(5, ['status' => TrackingLink::STATUS_INACTIVE]);

        $this->get('/ref/'.$link->code)->assertNotFound();
        $this->get('/ref/BRIX-NOPE')->assertNotFound();
        $this->assertDatabaseCount('referral_clicks', 0);
    }
}
