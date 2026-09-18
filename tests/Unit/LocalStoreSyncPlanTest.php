<?php

namespace Tests\Unit;

use App\Models\Brix\Store as BrixStore;
use App\Models\Organisation;
use App\Models\Store;
use App\Services\Brix\LocalStoreSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocalStoreSyncPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The real `cartninja` connection is MySQL and unreachable in
        // tests — swap it for an in-memory sqlite one carrying just the
        // `shops` table shape LocalStoreSync actually reads, so these
        // tests exercise the real query path without touching the real
        // BRIX database. Purely test-time config; config/database.php's
        // checked-in connection definition is untouched.
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

    private function organisation(): Organisation
    {
        return Organisation::create(['name' => 'Test Agency', 'website' => 'https://example.com']);
    }

    private function brixStore(string $domain): BrixStore
    {
        return new BrixStore([
            'shop_domain' => $domain,
            'shopify_shop_id' => '999',
            'store_name' => 'Test Store',
            'installation_status' => 'INSTALLED',
            'authorization_status' => 'AUTHORIZED',
        ]);
    }

    public function test_free_plan_key_syncs_to_free_label(): void
    {
        DB::connection('cartninja')->table('shops')->insert([
            'shop_domain' => 'freeshop.myshopify.com', 'plan_key' => 'free',
        ]);

        $local = LocalStoreSync::sync($this->organisation(), $this->brixStore('freeshop.myshopify.com'), 'ACTIVE');

        $this->assertSame('Free', $local->plan);
    }

    public function test_starter_plan_key_syncs_to_starter_label(): void
    {
        DB::connection('cartninja')->table('shops')->insert([
            'shop_domain' => 'starter-shop.myshopify.com', 'plan_key' => 'starter',
        ]);

        $local = LocalStoreSync::sync($this->organisation(), $this->brixStore('starter-shop.myshopify.com'), 'ACTIVE');

        $this->assertSame('Starter', $local->plan);
    }

    public function test_pro_plan_key_syncs_to_pro_label(): void
    {
        DB::connection('cartninja')->table('shops')->insert([
            'shop_domain' => 'pro-shop.myshopify.com', 'plan_key' => 'pro',
        ]);

        $local = LocalStoreSync::sync($this->organisation(), $this->brixStore('pro-shop.myshopify.com'), 'ACTIVE');

        $this->assertSame('Pro', $local->plan);
    }

    public function test_missing_brix_shop_row_falls_back_to_column_default_on_first_sync(): void
    {
        // No row inserted into the fake `shops` table for this domain at all.
        $local = LocalStoreSync::sync($this->organisation(), $this->brixStore('unknown.myshopify.com'), 'ACTIVE');

        // Nothing was fabricated — this is simply the 'plan' column's own
        // migration default, exactly as it behaved before plan mirroring
        // existed for a store LocalStoreSync had no plan signal for. The
        // in-memory model from create() doesn't reflect a DB-applied
        // default until refreshed.
        $this->assertSame('Starter', $local->fresh()->plan);
    }

    public function test_missing_brix_shop_row_never_overwrites_an_already_known_plan(): void
    {
        $organisation = $this->organisation();
        $brixStore = $this->brixStore('reconnect.myshopify.com');

        $local = LocalStoreSync::sync($organisation, $brixStore, 'ACTIVE');
        $local->update(['plan' => 'Pro']);

        // Re-sync (e.g. a later webhook/poll) with the cartninja row still
        // missing — must leave the previously-resolved 'Pro' alone rather
        // than resetting it to the column default.
        $local = LocalStoreSync::sync($organisation, $brixStore, 'ACTIVE');

        $this->assertSame('Pro', $local->fresh()->plan);
    }

    public function test_unrecognized_plan_key_never_overwrites_an_already_known_plan(): void
    {
        $organisation = $this->organisation();
        $brixStore = $this->brixStore('weird-plan.myshopify.com');

        $local = LocalStoreSync::sync($organisation, $brixStore, 'ACTIVE');
        $local->update(['plan' => 'Pro']);

        DB::connection('cartninja')->table('shops')->insert([
            'shop_domain' => 'weird-plan.myshopify.com', 'plan_key' => 'enterprise',
        ]);

        $local = LocalStoreSync::sync($organisation, $brixStore, 'ACTIVE');

        $this->assertSame('Pro', $local->fresh()->plan);
    }

    public function test_null_plan_key_never_overwrites_an_already_known_plan(): void
    {
        $organisation = $this->organisation();
        $brixStore = $this->brixStore('null-plan.myshopify.com');

        $local = LocalStoreSync::sync($organisation, $brixStore, 'ACTIVE');
        $local->update(['plan' => 'Pro']);

        DB::connection('cartninja')->table('shops')->insert([
            'shop_domain' => 'null-plan.myshopify.com', 'plan_key' => null,
        ]);

        $local = LocalStoreSync::sync($organisation, $brixStore, 'ACTIVE');

        $this->assertSame('Pro', $local->fresh()->plan);
    }

    public function test_unreachable_cartninja_connection_never_overwrites_an_already_known_plan(): void
    {
        $organisation = $this->organisation();
        $brixStore = $this->brixStore('offline-backend.myshopify.com');

        $local = LocalStoreSync::sync($organisation, $brixStore, 'ACTIVE');
        $local->update(['plan' => 'Pro']);

        // Simulate the real cartninja MySQL host being unreachable by
        // pointing the connection at a database file that doesn't exist
        // and can't be created (an invalid path), forcing a real PDO
        // exception through the exact same code path a network failure
        // would hit.
        config()->set('database.connections.cartninja.database', '/nonexistent/path/does-not-exist.sqlite');
        DB::purge('cartninja');

        $local = LocalStoreSync::sync($organisation, $brixStore, 'ACTIVE');

        $this->assertSame('Pro', $local->fresh()->plan);
    }

    public function test_existing_sync_fields_still_update_regardless_of_plan_resolution(): void
    {
        $organisation = $this->organisation();
        $brixStore = $this->brixStore('sync-fields.myshopify.com');

        LocalStoreSync::sync($organisation, $brixStore, 'PENDING');

        $updatedBrixStore = $this->brixStore('sync-fields.myshopify.com');
        $updatedBrixStore->installation_status = 'INSTALLED';
        $updatedBrixStore->authorization_status = 'AUTHORIZED';

        $local = LocalStoreSync::sync($organisation, $updatedBrixStore, 'ACTIVE');

        $this->assertSame('active', $local->status);
        $this->assertSame('ACTIVE', $local->agency_relationship_status);
        $this->assertSame('INSTALLED', $local->installation_status);
        $this->assertDatabaseCount('stores', 1);
    }
}
