<?php

namespace Tests\Feature\Portal;

use App\Models\Organisation;
use App\Models\Partners\Partner;
use App\Models\Referral\Lead;
use App\Models\Referral\TrackingLink;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Support\BrixTestSchema;
use Tests\TestCase;

class ReferralLinkEditAndScaffoldsTest extends TestCase
{
    use BrixTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 12:00:00');
        $this->migrateReferralTables();
        $this->createBrixScaffoldTables();
        $this->createTenancyTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function login(): array
    {
        $agency = Partner::create(['name' => 'Agency A', 'slug' => 'agency-a', 'owner_name' => 'o', 'owner_email' => 'a@example.com']);
        $org = Organisation::create(['name' => 'Org A']);
        $org->forceFill(['brix_agency_id' => $agency->id])->save();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'owner']);
        $this->actingAs($user)->withSession(['organisation_id' => $org->id]);

        return [$agency, $org];
    }

    public function test_agency_can_edit_its_own_referral_link(): void
    {
        [$agency] = $this->login();
        $link = TrackingLink::create(['agency_id' => $agency->id, 'name' => 'Old Name', 'channel' => 'Website', 'destination_url' => 'x']);

        $this->put("/referral-links/{$link->id}", [
            'name' => 'New Name', 'campaign_name' => 'Fall Push', 'channel' => 'Instagram', 'notes' => 'Updated',
        ])->assertRedirect();

        $link->refresh();
        $this->assertSame('New Name', $link->name);
        $this->assertSame('Fall Push', $link->campaign_name);
        $this->assertSame('Instagram', $link->channel);
        $this->assertDatabaseHas('activity_logs', ['action' => 'referral_link.updated', 'agency_id' => $agency->id]);
    }

    public function test_agency_cannot_edit_another_agencys_referral_link(): void
    {
        $this->login();
        $other = Partner::create(['name' => 'Agency B', 'slug' => 'agency-b', 'owner_name' => 'o', 'owner_email' => 'b@example.com']);
        $link = TrackingLink::create(['agency_id' => $other->id, 'name' => 'Theirs', 'channel' => 'Website', 'destination_url' => 'x']);

        $this->put("/referral-links/{$link->id}", ['name' => 'Hijacked', 'channel' => 'Instagram'])->assertForbidden();
        $this->assertSame('Theirs', $link->fresh()->name);
    }

    public function test_edit_validates_channel_against_the_fixed_list(): void
    {
        [$agency] = $this->login();
        $link = TrackingLink::create(['agency_id' => $agency->id, 'name' => 'Link', 'channel' => 'Website', 'destination_url' => 'x']);

        $this->put("/referral-links/{$link->id}", ['name' => 'Link', 'channel' => 'NotAChannel'])->assertSessionHasErrors('channel');
        $this->assertSame('Website', $link->fresh()->channel);
    }

    public function test_leads_view_tabs_use_real_stage_and_brix_status_groupings(): void
    {
        [$agency] = $this->login();
        $link = TrackingLink::create(['agency_id' => $agency->id, 'name' => 'L', 'channel' => 'Website', 'destination_url' => 'x']);

        $new = Lead::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'shop_domain' => 'new.myshopify.com', 'lead_stage' => Lead::STAGE_NEW]);
        $installed = Lead::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'shop_domain' => 'installed.myshopify.com', 'lead_stage' => Lead::STAGE_INSTALLED]);
        $active = Lead::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'shop_domain' => 'active.myshopify.com', 'lead_stage' => Lead::STAGE_ACTIVE]);
        $churned = Lead::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'shop_domain' => 'churned.myshopify.com', 'lead_stage' => Lead::STAGE_ACTIVE, 'brix_status' => 'UNINSTALLED']);
        Lead::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'shop_domain' => 'lost.myshopify.com', 'lead_stage' => Lead::STAGE_LOST]);

        $this->get('/leads?view=in_review')->assertOk()->assertSee($new->shop_domain)->assertDontSee($installed->shop_domain);
        $this->get('/leads?view=installed')->assertOk()->assertSee($installed->shop_domain)->assertDontSee($new->shop_domain);
        // "Active" includes the churned lead too (its lead_stage never changes on uninstall — see Phase 3);
        // "Churned" is the brix_status-derived view that isolates it.
        $this->get('/leads?view=active')->assertOk()->assertSee($active->shop_domain)->assertSee($churned->shop_domain);
        $this->get('/leads?view=churned')->assertOk()->assertSee($churned->shop_domain)->assertDontSee($active->shop_domain);
        $this->get('/leads?view=lost')->assertOk()->assertDontSee($active->shop_domain);
        $this->get('/leads')->assertOk()->assertSee('All')->assertSee('(5)');
    }

    public function test_courses_promo_codes_and_rewards_render_real_empty_states(): void
    {
        [$agency] = $this->login();

        $this->get('/courses')->assertOk()->assertSee('No courses available yet');
        $this->get('/promo-codes')->assertOk()->assertSee('No promo codes available yet');
        $this->get('/rewards')->assertOk()->assertSee('No rewards available yet')->assertSee('0 / 6');

        // Achieving a real milestone (first referral link) is reflected, not invented.
        TrackingLink::create(['agency_id' => $agency->id, 'name' => 'L', 'channel' => 'Website', 'destination_url' => 'x']);
        $this->get('/rewards')->assertOk()->assertSee('1 / 6');
    }

    public function test_scaffold_pages_are_isolated_per_organisation(): void
    {
        [$agencyA] = $this->login();
        TrackingLink::create(['agency_id' => $agencyA->id, 'name' => 'L', 'channel' => 'Website', 'destination_url' => 'x']);

        $agencyB = Partner::create(['name' => 'Agency B', 'slug' => 'agency-b', 'owner_name' => 'o', 'owner_email' => 'b@example.com']);
        $orgB = Organisation::create(['name' => 'Org B']);
        $orgB->forceFill(['brix_agency_id' => $agencyB->id])->save();
        $userB = User::factory()->create();
        $orgB->users()->attach($userB->id, ['role' => 'owner']);

        $this->actingAs($userB)->withSession(['organisation_id' => $orgB->id]);
        // Agency B has no links of its own — never agency A's milestone progress.
        $this->get('/rewards')->assertOk()->assertSee('0 / 6');
    }
}
