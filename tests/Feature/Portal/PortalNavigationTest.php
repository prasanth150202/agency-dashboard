<?php

namespace Tests\Feature\Portal;

use Illuminate\Support\Facades\Route;
use Tests\Support\PartnerPortalTestbed;
use Tests\TestCase;

/** Phase 1: the sidebar is the target navigation and never links to a missing page. */
class PortalNavigationTest extends TestCase
{
    use PartnerPortalTestbed;

    /** Sidebar entries that only render once their route exists. */
    private const GATED = [
        'leads.index' => 'Leads',
        'qr.index' => 'QR Referrals',
        'tracking.index' => 'Tracking',
        'campaigns.index' => 'Campaigns',
        'account-mapping.index' => 'Account Mapping',
        'revenue.index' => 'Revenue',
        'courses.index' => 'Courses',
        'promo-codes.index' => 'Promo Codes',
        'rewards.index' => 'Rewards',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootPortal();
    }

    public function test_sidebar_shows_grouped_navigation_for_existing_modules(): void
    {
        $this->actAs($this->makeTenant('Nav Agency'));

        $this->get('/referral-links')
            ->assertOk()
            ->assertSeeInOrder(['Dashboard', 'Stores', 'Growth', 'Referral Links', 'Finance', 'Commissions', 'Payouts', 'Insights', 'Analytics', 'Settings', 'Bank Details']);
    }

    public function test_entries_appear_only_when_their_route_exists(): void
    {
        $this->actAs($this->makeTenant('Nav Agency'));

        $response = $this->get('/referral-links')->assertOk();

        foreach (self::GATED as $routeName => $label) {
            if (Route::has($routeName)) {
                $response->assertSee(route($routeName), false);
            } else {
                $response->assertDontSee(">{$label}<", false);
            }
        }
    }

    public function test_active_item_is_highlighted(): void
    {
        $this->actAs($this->makeTenant('Nav Agency'));

        $this->get('/referral-links')->assertOk()->assertSee('bg-brix-50 text-brix-700', false);
    }
}
