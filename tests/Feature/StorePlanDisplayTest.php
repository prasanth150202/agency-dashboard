<?php

namespace Tests\Feature;

use App\Models\Brix\Store as BrixStore;
use App\Models\Organisation;
use App\Models\Store;
use App\Models\User;
use App\Services\Brix\LocalStoreSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end verification that a plan_key mirrored through LocalStoreSync
 * actually renders correctly on the real Stores index and Store detail
 * pages — not just that the model attribute is set (see
 * tests/Unit/LocalStoreSyncPlanTest.php for that). Uses an isolated
 * in-memory `cartninja` connection, the same technique as that unit test;
 * the real local `cartdrawer` MySQL database is never touched.
 */
class StorePlanDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const DOMAIN = 'test-plan-store.myshopify.com';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.cartninja', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('cartninja');

        DB::connection('cartninja')->statement(
            'CREATE TABLE shops (id INTEGER PRIMARY KEY AUTOINCREMENT, shop_domain TEXT, plan_key TEXT)'
        );
    }

    private function actingUserWithOrganisation(): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::create(['name' => 'Plan Verify Agency', 'website' => 'https://example.com']);
        $organisation->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user);

        return [$user, $organisation];
    }

    private function syncPlan(Organisation $organisation, string $planKey): Store
    {
        DB::connection('cartninja')->table('shops')->updateOrInsert(
            ['shop_domain' => self::DOMAIN],
            ['plan_key' => $planKey]
        );

        $brixStore = new BrixStore([
            'shop_domain' => self::DOMAIN,
            'shopify_shop_id' => '424242',
            'store_name' => 'Plan Verify Store',
            'installation_status' => 'INSTALLED',
            'authorization_status' => 'AUTHORIZED',
        ]);

        return LocalStoreSync::sync($organisation, $brixStore, 'ACTIVE');
    }

    /**
     * @return array{0: string, 1: string} [plan_key, expected label]
     */
    public static function plans(): array
    {
        return [
            'free' => ['free', 'Free'],
            'starter' => ['starter', 'Starter'],
            'pro' => ['pro', 'Pro'],
        ];
    }

    #[DataProvider('plans')]
    public function test_plan_sync_produces_the_expected_local_plan_value(string $planKey, string $expectedLabel): void
    {
        [, $organisation] = $this->actingUserWithOrganisation();

        $store = $this->syncPlan($organisation, $planKey);

        $this->assertSame($expectedLabel, $store->fresh()->plan);
    }

    #[DataProvider('plans')]
    public function test_store_card_renders_the_synced_plan(string $planKey, string $expectedLabel): void
    {
        [, $organisation] = $this->actingUserWithOrganisation();
        $this->syncPlan($organisation, $planKey);

        $response = $this->get(route('stores.index'));

        $response->assertOk();
        $response->assertSee("{$expectedLabel} plan");
    }

    #[DataProvider('plans')]
    public function test_store_details_renders_the_synced_plan(string $planKey, string $expectedLabel): void
    {
        [, $organisation] = $this->actingUserWithOrganisation();
        $store = $this->syncPlan($organisation, $planKey);

        $response = $this->get(route('stores.show', $store));

        $response->assertOk();
        $response->assertSee('Plan');
        $response->assertSee($expectedLabel);
    }

    /**
     * Confirms adding the Plan card didn't disturb any of the store detail
     * page's pre-existing stats — status, BRIX installation status, Shopify
     * authorization status, and last-sync time all still render.
     */
    public function test_store_details_still_renders_existing_fields_alongside_plan(): void
    {
        [, $organisation] = $this->actingUserWithOrganisation();
        $store = $this->syncPlan($organisation, 'pro');

        $response = $this->get(route('stores.show', $store));

        $response->assertOk();
        $response->assertSee('Store status');
        $response->assertSee('BRIX');
        $response->assertSee('● Installed');
        $response->assertSee('Shopify');
        $response->assertSee('✓ Connected');
        $response->assertSee('Last sync');
        $response->assertSee('Plan');
        $response->assertSee('Pro');
    }

    /**
     * Confirms the store card's pre-existing module-count line still
     * renders correctly alongside the new plan text.
     */
    public function test_store_card_still_renders_module_count_alongside_plan(): void
    {
        [, $organisation] = $this->actingUserWithOrganisation();
        $store = $this->syncPlan($organisation, 'starter');

        $response = $this->get(route('stores.index'));

        $response->assertOk();
        $totalModules = count(\App\Models\StoreModule::MODULES);
        $response->assertSee("0 / {$totalModules} modules active");
        $response->assertSee('Starter plan');
        $response->assertSee($store->name);
    }
}
