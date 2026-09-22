<?php

namespace Tests\Feature\Portal;

use App\Models\Organisation;
use App\Models\Partners\Partner;
use App\Models\Payout;
use App\Models\PayoutAccount;
use App\Models\Referral\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Store;
use App\Models\User;
use App\Services\Referral\Commission\ReferralCommissionService;
use Illuminate\Support\Carbon;
use Tests\Support\BrixTestSchema;
use Tests\TestCase;

/**
 * Section 12/13: the exact payout lifecycle, and that a commission can
 * never be double-claimed, never turns into a payout by itself, and never
 * moves to paid except through Super Admin's explicit action.
 */
class PayoutFinancialSafetyTest extends TestCase
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
        $this->createAppSettingsTable(['commission_holding_period_days' => '7', 'minimum_payout_amount' => '10']);
        $this->fakeCartninjaShops();
        $this->createCartninjaUsageTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setUpAgencyWithEligibleCommission(float $rate = 30.0, float $chargeAmount = 100.00): array
    {
        $agency = Partner::create(['name' => 'Agency A', 'slug' => 'agency-a'.uniqid(), 'owner_name' => 'o', 'owner_email' => uniqid().'@example.com', 'commission_rate' => $rate]);
        $org = Organisation::create(['name' => 'Org A']);
        $org->forceFill(['brix_agency_id' => $agency->id])->save();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'owner']);

        $link = \App\Models\Referral\TrackingLink::create(['agency_id' => $agency->id, 'name' => 'L', 'channel' => 'Website', 'destination_url' => 'x']);
        $store = Store::create(['agency_id' => $agency->id, 'shop_domain' => 'shop.myshopify.com', 'store_name' => 'Shop', 'installation_status' => 'INSTALLED', 'installed_at' => now()->subDays(10)]);
        Lead::create(['agency_id' => $agency->id, 'tracking_link_id' => $link->id, 'store_id' => $store->id, 'shop_domain' => 'shop.myshopify.com', 'lead_stage' => Lead::STAGE_ACTIVE, 'installed_at' => now()->subDays(10)]);

        $this->seedOrderOverage('shop.myshopify.com', $chargeAmount, 'charged', 'gid://x/1', now()->subDays(9));
        app(ReferralCommissionService::class)->accrue(true);

        // Past the holding period, so it's genuinely eligible now.
        Carbon::setTestNow(now()->addDays(8));

        $account = PayoutAccount::create([
            'agency_id' => $agency->id, 'type' => 'bank', 'account_holder_name' => 'Agency A',
            'account_last4' => '1234', 'verification_status' => PayoutAccount::STATUS_VERIFIED, 'is_default' => true,
        ]);

        // Currency safety: the org's own currency must match the referral
        // commission's (USD) for it to be claimable — see UnifiedCommissionService::claim().
        \App\Models\OrganisationSettings::updateOrCreate(['organisation_id' => $org->id], ['currency' => 'USD']);

        return [$agency, $org, $user, $account];
    }

    private function login(Organisation $org, User $user): void
    {
        $this->actingAs($user)->withSession(['organisation_id' => $org->id]);
    }

    public function test_an_eligible_commission_creates_no_payout_by_itself(): void
    {
        $this->setUpAgencyWithEligibleCommission();

        $commission = ReferralCommission::first();
        $this->assertSame('eligible', $commission->effective_status);
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_agency_explicit_request_creates_the_payout_and_claims_the_commission(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);

        $this->postJson('/payouts/request', ['amount' => 30.00])->assertOk();

        $this->assertDatabaseCount('payouts', 1);
        $payout = Payout::first();
        $this->assertSame(Payout::STATUS_PENDING, $payout->status);

        $commission = ReferralCommission::first();
        $this->assertSame(ReferralCommission::STATUS_IN_PAYOUT, $commission->status);
        $this->assertSame($payout->id, $commission->payouts->first()->id);
    }

    public function test_a_second_request_cannot_claim_the_same_already_claimed_commission(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);

        $this->postJson('/payouts/request', ['amount' => 30.00])->assertOk();
        $this->assertDatabaseCount('payouts', 1);

        // The commission is now in_payout, so a second request has nothing left to claim.
        $second = $this->postJson('/payouts/request', ['amount' => 30.00]);
        $second->assertStatus(422);

        $this->assertDatabaseCount('payouts', 1); // no second payout created
        $this->assertSame(1, ReferralCommission::where('status', ReferralCommission::STATUS_IN_PAYOUT)->count());
    }

    public function test_rejection_releases_the_commission_back_to_pending_and_it_becomes_claimable_again(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00]);
        $payout = Payout::first();

        $admin = \App\Models\Admin\AdminUser::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('x'), 'role' => 'SUPER_ADMIN', 'status' => 'active']);
        $this->actingAs($admin, 'admin');
        $this->post("/admin/payouts/{$payout->id}/reject", ['reason' => 'Bank details invalid'])->assertRedirect();

        $payout->refresh();
        $this->assertSame(Payout::STATUS_REJECTED, $payout->status);

        $commission = ReferralCommission::first();
        $this->assertSame(ReferralCommission::STATUS_PENDING, $commission->status);
        $this->assertSame('eligible', $commission->fresh()->effective_status); // holding period already lapsed

        // Claimable by a new request now.
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00])->assertOk();
        $this->assertDatabaseCount('payouts', 2);
        $this->assertSame(ReferralCommission::STATUS_IN_PAYOUT, $commission->fresh()->status);
    }

    public function test_super_admin_approval_and_mark_paid_is_the_only_path_to_paid(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00]);
        $payout = Payout::first();
        $commission = ReferralCommission::first();

        $admin = \App\Models\Admin\AdminUser::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('x'), 'role' => 'SUPER_ADMIN', 'status' => 'active']);
        $this->actingAs($admin, 'admin');

        // Still in_payout, not paid, until Super Admin explicitly acts.
        $this->assertSame(ReferralCommission::STATUS_IN_PAYOUT, $commission->fresh()->status);

        $this->post("/admin/payouts/{$payout->id}/approve")->assertRedirect();
        $this->assertSame(Payout::STATUS_APPROVED, $payout->fresh()->status);
        $this->assertSame(ReferralCommission::STATUS_IN_PAYOUT, $commission->fresh()->status); // still not paid

        $this->post("/admin/payouts/{$payout->id}/mark-paid", [
            'transfer_reference' => 'UTR123456',
            'transfer_date' => '2026-10-01',
            'paid_amount' => (float) $payout->amount,
            'currency' => $payout->currency,
            'confirm' => '1',
        ])->assertRedirect();

        $this->assertSame(Payout::STATUS_PAID, $payout->fresh()->status);
        $this->assertSame(ReferralCommission::STATUS_PAID, $commission->fresh()->status);
        $this->assertSame('UTR123456', $payout->fresh()->transfer_reference);

        // The agency ledger was credited for the referral portion (see settleReferralCommissions()).
        $this->assertDatabaseHas('agency_ledger', ['payout_id' => $payout->id, 'type' => 'COMMISSION']);
    }

    public function test_admin_can_move_a_pending_request_to_under_review(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00]);
        $payout = Payout::first();

        $admin = $this->loginAsAdmin();
        $this->post("/admin/payouts/{$payout->id}/review")->assertRedirect();

        $payout->refresh();
        $this->assertSame(Payout::STATUS_UNDER_REVIEW, $payout->status);
        $this->assertSame($admin->id, $payout->reviewed_by);
        $this->assertNotNull($payout->reviewed_at);

        // Still approvable straight from under_review.
        $this->post("/admin/payouts/{$payout->id}/approve")->assertRedirect();
        $this->assertSame(Payout::STATUS_APPROVED, $payout->fresh()->status);
        $this->assertSame($admin->id, $payout->fresh()->approved_by);
    }

    public function test_mark_paid_rejects_a_currency_mismatch(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00]);
        $payout = Payout::first();

        $this->loginAsAdmin();
        $this->post("/admin/payouts/{$payout->id}/approve");

        $this->post("/admin/payouts/{$payout->id}/mark-paid", [
            'transfer_reference' => 'UTR1',
            'transfer_date' => '2026-10-01',
            'paid_amount' => (float) $payout->amount,
            'currency' => 'EUR',
            'confirm' => '1',
        ])->assertSessionHasErrors('currency');

        $this->assertSame(Payout::STATUS_APPROVED, $payout->fresh()->status);
    }

    public function test_mark_paid_rejects_an_amount_that_does_not_match_the_approved_amount(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00]);
        $payout = Payout::first();

        $this->loginAsAdmin();
        $this->post("/admin/payouts/{$payout->id}/approve");

        $this->post("/admin/payouts/{$payout->id}/mark-paid", [
            'transfer_reference' => 'UTR1',
            'transfer_date' => '2026-10-01',
            'paid_amount' => (float) $payout->amount - 5,
            'currency' => $payout->currency,
            'confirm' => '1',
        ])->assertSessionHasErrors('paid_amount');

        $this->assertSame(Payout::STATUS_APPROVED, $payout->fresh()->status);
    }

    public function test_a_paid_payout_can_never_be_marked_paid_again(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00]);
        $payout = Payout::first();

        $this->loginAsAdmin();
        $this->post("/admin/payouts/{$payout->id}/approve");
        $payload = [
            'transfer_reference' => 'UTR1',
            'transfer_date' => '2026-10-01',
            'paid_amount' => (float) $payout->amount,
            'currency' => $payout->currency,
            'confirm' => '1',
        ];
        $this->post("/admin/payouts/{$payout->id}/mark-paid", $payload)->assertRedirect();
        $this->assertSame(Payout::STATUS_PAID, $payout->fresh()->status);

        // Second attempt: the model guard is a no-op, and the route itself
        // now 422s since the payout is no longer approved.
        $this->post("/admin/payouts/{$payout->id}/mark-paid", $payload)->assertStatus(422);
        $this->assertSame(Payout::STATUS_PAID, $payout->fresh()->status);
        $this->assertSame('UTR1', $payout->fresh()->transfer_reference);
    }

    public function test_a_rejected_payout_cannot_be_marked_paid(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00]);
        $payout = Payout::first();

        $this->loginAsAdmin();
        $this->post("/admin/payouts/{$payout->id}/reject", ['reason' => 'Bad details']);
        $this->assertSame(Payout::STATUS_REJECTED, $payout->fresh()->status);

        $this->post("/admin/payouts/{$payout->id}/mark-paid", [
            'transfer_reference' => 'UTR1',
            'transfer_date' => '2026-10-01',
            'paid_amount' => (float) $payout->amount,
            'currency' => $payout->currency,
            'confirm' => '1',
        ])->assertStatus(422);

        $this->assertSame(Payout::STATUS_REJECTED, $payout->fresh()->status);
    }

    public function test_payout_detail_shows_the_underlying_referral_commission(): void
    {
        [$agency, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00]);
        $payout = Payout::first();

        $this->get("/payouts/{$payout->id}")->assertOk()->assertSee('30.00');

        $admin = \App\Models\Admin\AdminUser::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('x'), 'role' => 'SUPER_ADMIN', 'status' => 'active']);
        $this->actingAs($admin, 'admin');
        $this->get("/admin/payouts/{$payout->id}")->assertOk()
            ->assertSee('Claimed Referral Commissions')
            ->assertSee('REF-'.ReferralCommission::first()->id)
            ->assertSee($agency->name);
    }

    public function test_agency_payout_index_renders_with_totals_and_filters(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00])->assertOk();

        $payout = Payout::first();

        $this->get('/payouts')->assertOk()->assertSee('Total Earned')->assertSee($payout->payout_code);
        $this->get('/payouts?filter=pending')->assertOk()->assertSee($payout->payout_code);
        // No paid payouts yet — the notification bell still legitimately
        // mentions the code, so assert against the table's own empty state.
        $this->get('/payouts?filter=paid')->assertOk()->assertSee('No payouts yet');
    }

    public function test_admin_payout_index_renders_kpis_and_filters(): void
    {
        [, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        $this->login($org, $user);
        $this->postJson('/payouts/request', ['amount' => 30.00])->assertOk();
        $payout = Payout::first();

        $this->loginAsAdmin();

        $this->get('/admin/payouts')->assertOk()
            ->assertSee('Pending Requests')
            ->assertSee('Total Requested')
            ->assertSee($payout->payout_code);

        $this->get('/admin/payouts?status=paid')->assertOk()->assertDontSee($payout->payout_code);
        $this->get('/admin/payouts?search='.$payout->payout_code)->assertOk()->assertSee($payout->payout_code);
    }

    public function test_a_currency_mismatch_is_surfaced_not_silently_converted(): void
    {
        [$agency, $org, $user] = $this->setUpAgencyWithEligibleCommission();
        // Switch the organisation's payout currency away from the commission's (USD).
        \App\Models\OrganisationSettings::updateOrCreate(['organisation_id' => $org->id], ['currency' => 'INR']);
        $this->login($org, $user);

        $this->postJson('/payouts/request', ['amount' => 30.00])->assertStatus(422);

        $this->assertDatabaseCount('payouts', 0);
        $unified = new \App\Services\Finance\UnifiedCommissionService($org->fresh());
        $this->assertArrayHasKey('USD', $unified->unpayable()); // surfaced, not converted or lost
    }

    public function test_agency_cannot_request_a_payout_using_another_agencys_commission(): void
    {
        [$agencyA, $orgA, $userA] = $this->setUpAgencyWithEligibleCommission();

        $agencyB = Partner::create(['name' => 'Agency B', 'slug' => 'agency-b'.uniqid(), 'owner_name' => 'o', 'owner_email' => uniqid().'@example.com']);
        $orgB = Organisation::create(['name' => 'Org B']);
        $orgB->forceFill(['brix_agency_id' => $agencyB->id])->save();
        $userB = User::factory()->create();
        $orgB->users()->attach($userB->id, ['role' => 'owner']);
        \App\Models\OrganisationSettings::updateOrCreate(['organisation_id' => $orgB->id], ['currency' => 'USD']);

        $this->login($orgB, $userB);
        $response = $this->postJson('/payouts/request', ['amount' => 30.00]);

        // Agency B has no eligible commissions of its own — A's commission is never reachable.
        $response->assertStatus(422);
        $this->assertDatabaseCount('payouts', 0);
        $this->assertSame($agencyA->id, ReferralCommission::first()->agency_id);
    }
}
