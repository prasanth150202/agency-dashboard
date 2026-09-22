<?php

namespace Tests\Feature\Portal;

use App\Models\Partners\AgencyStore;
use App\Models\Referral\Lead;
use App\Models\Referral\TrackingLink;
use App\Models\Store;
use App\Services\Referral\AccountMapping;
use Tests\Support\PartnerPortalTestbed;
use Tests\TestCase;

/** Phase 6: read-only account mapping. */
class AccountMappingTest extends TestCase
{
    use PartnerPortalTestbed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootPortal();
    }

    private function link(array $t): TrackingLink
    {
        return TrackingLink::create(['agency_id' => $t['agency']->id, 'name' => 'Poster', 'channel' => 'Other', 'destination_url' => 'https://apps.shopify.com/thebrix-io']);
    }

    private function store(int $agencyId, string $shop, string $install = 'INSTALLED'): Store
    {
        return Store::create(['agency_id' => $agencyId, 'shop_domain' => $shop, 'store_name' => "Store {$shop}", 'installation_status' => $install]);
    }

    public function test_empty_state(): void
    {
        $this->actAs($this->makeTenant('Empty'));

        $this->get('/account-mapping')->assertOk()->assertSee('No referred merchants yet');
    }

    public function test_each_lead_lands_in_the_right_mapping_state(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $link = $this->link($a);
        $id = $a['agency']->id;

        $awaiting = Lead::create(['agency_id' => $id, 'tracking_link_id' => $link->id, 'shop_domain' => 'wait.myshopify.com']);

        $connectedStore = $this->store($id, 'ok.myshopify.com');
        AgencyStore::create(['agency_id' => $id, 'store_id' => $connectedStore->id, 'relationship_status' => 'ACTIVE']);
        $connected = Lead::create(['agency_id' => $id, 'tracking_link_id' => $link->id, 'shop_domain' => 'ok.myshopify.com', 'store_id' => $connectedStore->id]);

        $pendingStore = $this->store($id, 'pend.myshopify.com');
        AgencyStore::create(['agency_id' => $id, 'store_id' => $pendingStore->id, 'relationship_status' => 'PENDING']);
        $needs = Lead::create(['agency_id' => $id, 'tracking_link_id' => $link->id, 'shop_domain' => 'pend.myshopify.com', 'store_id' => $pendingStore->id]);

        $foreignStore = $this->store($b['agency']->id, 'theirs.myshopify.com');
        $elsewhere = Lead::create(['agency_id' => $id, 'tracking_link_id' => $link->id, 'shop_domain' => 'theirs.myshopify.com', 'store_id' => $foreignStore->id]);

        $mapping = new AccountMapping($id);
        $this->assertSame(AccountMapping::AWAITING_STORE, $mapping->stateOf($awaiting->load('store')));
        $this->assertSame(AccountMapping::MAPPED, $mapping->stateOf($connected->load('store.agencyStore')));
        $this->assertSame(AccountMapping::NEEDS_ACTION, $mapping->stateOf($needs->load('store.agencyStore')));
        $this->assertSame(AccountMapping::MANAGED_ELSEWHERE, $mapping->stateOf($elsewhere->load('store.agencyStore')));

        $summary = $mapping->summary();
        $this->assertSame(4, $summary['referred']);
        $this->assertSame(1, $summary[AccountMapping::MAPPED]);
        $this->assertSame(1, $summary[AccountMapping::NEEDS_ACTION]);
        $this->assertSame(1, $summary[AccountMapping::AWAITING_STORE]);
        $this->assertSame(1, $summary[AccountMapping::MANAGED_ELSEWHERE]);
    }

    public function test_another_partners_store_is_never_revealed(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $foreign = $this->store($b['agency']->id, 'theirs.myshopify.com');
        $this->store($b['agency']->id, 'other-direct.myshopify.com');
        Lead::create(['agency_id' => $a['agency']->id, 'tracking_link_id' => $this->link($a)->id, 'shop_domain' => 'theirs.myshopify.com', 'store_id' => $foreign->id]);

        $this->actAs($a);
        $this->get('/account-mapping?agency_id='.$b['agency']->id)->assertOk()
            ->assertSee('Managed by another partner')
            ->assertDontSee('Store theirs.myshopify.com')      // the other partner's store name
            ->assertDontSee('other-direct.myshopify.com')       // their direct stores never listed
            ->assertDontSee(route('stores.show', $foreign));    // and never linked
    }

    public function test_direct_stores_exclude_referred_ones_and_are_own_only(): void
    {
        $a = $this->makeTenant('Agency A');
        $b = $this->makeTenant('Agency B');
        $referred = $this->store($a['agency']->id, 'ref.myshopify.com');
        $direct = $this->store($a['agency']->id, 'direct.myshopify.com');
        $this->store($b['agency']->id, 'b-direct.myshopify.com');
        Lead::create(['agency_id' => $a['agency']->id, 'tracking_link_id' => $this->link($a)->id, 'shop_domain' => 'ref.myshopify.com', 'store_id' => $referred->id]);

        $this->assertSame([$direct->id], (new AccountMapping($a['agency']->id))->directStores()->pluck('id')->all());

        $this->actAs($a);
        $this->get('/account-mapping')->assertOk()->assertSee('direct.myshopify.com')->assertDontSee('b-direct.myshopify.com');
    }

    public function test_page_is_read_only(): void
    {
        $this->actAs($this->makeTenant('Agency A'));

        $this->post('/account-mapping', ['lead_id' => 1, 'store_id' => 1])->assertStatus(405);
        $this->put('/account-mapping', [])->assertStatus(405);
    }
}
