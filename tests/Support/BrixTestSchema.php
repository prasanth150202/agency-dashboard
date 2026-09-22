<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only, in-memory scaffolding. agencies/stores/agency_stores/etc. are
 * owned by the real MySQL database and have no create-migration in this
 * repo (which is why RefreshDatabase's full migration chain fails on
 * sqlite). These helpers build just enough of them inside the per-test
 * :memory: database to exercise the referral code paths — they are never
 * migrations and never touch any real database.
 */
trait BrixTestSchema
{
    protected function migrateReferralTables(): void
    {
        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/2026_09_21_135019_create_tracking_links_table.php',
                'database/migrations/2026_09_21_135020_create_referral_clicks_table.php',
                'database/migrations/2026_09_21_135021_create_leads_table.php',
                'database/migrations/2026_09_21_135022_create_lead_events_table.php',
                'database/migrations/2026_09_22_100000_create_referral_revenue_events_table.php',
                'database/migrations/2026_09_22_100100_create_referral_commissions_table.php',
                'database/migrations/2026_09_23_100000_add_source_to_referral_clicks_table.php',
                'database/migrations/2026_09_23_110000_create_referral_commission_payout_table.php',
                'database/migrations/2026_09_25_100000_add_manual_fields_to_leads_table.php',
                'database/migrations/2026_09_26_100000_add_brix_plan_to_leads_table.php',
            ],
        ])->run();
    }

    /** In-memory stand-in for the real admin-controlled `app_settings` table. */
    protected function createAppSettingsTable(array $settings = ['commission_holding_period_days' => '7', 'minimum_payout_amount' => '1000']): void
    {
        Schema::create('app_settings', function (Blueprint $t) {
            $t->string('setting_key', 80)->primary();
            $t->text('value');
            $t->dateTime('updated_at')->nullable();
        });

        foreach ($settings as $key => $value) {
            DB::table('app_settings')->insert(['setting_key' => $key, 'value' => $value]);
        }
    }

    /** The two BRIX usage-billing tables, on the faked read-only `cartninja` connection. */
    protected function createCartninjaUsageTables(): void
    {
        $cartninja = DB::connection('cartninja');

        $cartninja->statement(
            'CREATE TABLE order_overage_charges (id INTEGER PRIMARY KEY AUTOINCREMENT, shop_domain TEXT, date TEXT, plan_key TEXT, '
            .'order_count INTEGER, order_cap INTEGER, overage_orders INTEGER, overage_rate REAL, charge_amount REAL, status TEXT, '
            .'shopify_usage_record_id TEXT, error_message TEXT, created_at TEXT, updated_at TEXT)'
        );
        $cartninja->statement(
            'CREATE TABLE ai_brix_overage_charges (id INTEGER PRIMARY KEY AUTOINCREMENT, shop_domain TEXT, period_key TEXT, credit_number INTEGER, '
            .'plan_key TEXT, overage_rate REAL, charge_amount REAL, status TEXT, shopify_usage_record_id TEXT, error_message TEXT, created_at TEXT, updated_at TEXT)'
        );
    }

    protected function seedOrderOverage(string $shop, float $amount, string $status = 'charged', ?string $usageRecordId = 'gid://shopify/AppUsageRecord/1', $chargedAt = null): int
    {
        $at = ($chargedAt ?? now())->format('Y-m-d H:i:s');

        return DB::connection('cartninja')->table('order_overage_charges')->insertGetId([
            'shop_domain' => $shop, 'date' => substr($at, 0, 10), 'plan_key' => 'starter', 'order_count' => 600, 'order_cap' => 500,
            'overage_orders' => 100, 'overage_rate' => 0.03, 'charge_amount' => $amount, 'status' => $status,
            'shopify_usage_record_id' => $usageRecordId, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    protected function seedAiOverage(string $shop, float $amount, int $credit, string $status = 'charged', ?string $usageRecordId = null, $chargedAt = null): int
    {
        $at = ($chargedAt ?? now())->format('Y-m-d H:i:s');

        return DB::connection('cartninja')->table('ai_brix_overage_charges')->insertGetId([
            'shop_domain' => $shop, 'period_key' => substr($at, 0, 7), 'credit_number' => $credit, 'plan_key' => 'starter',
            'overage_rate' => $amount, 'charge_amount' => $amount, 'status' => $status,
            'shopify_usage_record_id' => $usageRecordId ?? "gid://shopify/AppUsageRecord/ai-{$credit}", 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    protected function createBrixScaffoldTables(): void
    {
        Schema::create('agencies', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('owner_name')->nullable();
            $t->string('owner_email')->nullable();
            $t->string('phone')->nullable();
            $t->string('status')->default('active');
            $t->boolean('commission_enabled')->default(true);
            $t->string('commission_type')->default('percentage');
            $t->decimal('commission_rate', 5, 2)->default(30);
            $t->string('country')->default('India');
            $t->timestamps();
        });

        Schema::create('stores', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('agency_id');
            $t->string('shop_domain')->unique();
            $t->string('shopify_shop_id')->nullable();
            $t->string('store_name');
            $t->string('status')->default('trial');
            $t->string('installation_status')->default('NOT_INSTALLED');
            $t->string('authorization_status')->default('NOT_AUTHORIZED');
            $t->string('plan')->default('Starter');
            $t->boolean('commission_override_enabled')->default(false);
            $t->decimal('commission_override_rate', 5, 2)->nullable();
            $t->dateTime('installed_at')->nullable();
            $t->dateTime('uninstalled_at')->nullable();
            $t->dateTime('last_active_at')->nullable();
            $t->timestamps();
        });

        Schema::create('agency_stores', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('agency_id');
            $t->unsignedInteger('store_id');
            $t->string('relationship_status')->default('PENDING');
            $t->dateTime('authorized_at')->nullable();
            $t->dateTime('activated_at')->nullable();
            $t->dateTime('disconnected_at')->nullable();
            $t->timestamps();
            $t->unique(['agency_id', 'store_id']);
        });

        Schema::create('agency_store_onboarding', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('agency_id');
            $t->string('state_token')->unique();
            $t->string('shop_domain');
            $t->string('status')->default('STARTED');
            $t->string('failure_reason')->nullable();
            $t->unsignedInteger('created_store_id')->nullable();
            $t->dateTime('expires_at');
            $t->dateTime('created_at')->nullable();
            $t->dateTime('updated_at')->nullable();
        });

        Schema::create('store_modules', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('store_id');
            $t->string('module_name');
            $t->string('module_key')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_updated_at')->nullable();
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('agency_id')->nullable();
            $t->unsignedInteger('store_id')->nullable();
            $t->string('action');
            $t->text('metadata')->nullable();
            $t->string('ip_address')->nullable();
            $t->dateTime('created_at')->nullable();
        });

        Schema::create('admin_users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password_hash');
            $t->string('role')->default('SUPER_ADMIN');
            $t->string('status')->default('active');
            $t->string('avatar_color')->nullable();
            $t->dateTime('last_login_at')->nullable();
            $t->timestamps();
        });

        Schema::create('partner_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('organisation_id');
            $t->unsignedInteger('store_id')->nullable();
            $t->string('type')->default('info');
            $t->string('title');
            $t->string('message');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    /**
     * In-memory stand-ins for the real, migration-less finance tables
     * (transactions, payouts, agency_ledger, payout_accounts) plus the
     * repo-owned transaction_payout pivot. Columns mirror the models'
     * fillable lists; they exist only inside the per-test :memory: database.
     */
    protected function createFinanceScaffoldTables(): void
    {
        Schema::create('transactions', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('agency_id');
            $t->unsignedInteger('store_id');
            $t->unsignedInteger('subscription_id')->nullable();
            $t->decimal('gross_amount', 12, 2)->default(0);
            $t->decimal('commission_rate', 5, 2)->default(0);
            $t->string('commission_source')->default('agency_default');
            $t->decimal('agency_commission', 12, 2)->default(0);
            $t->decimal('brix_revenue', 12, 2)->default(0);
            $t->string('commission_status')->default('pending');
            $t->dateTime('available_at')->nullable();
            $t->string('type')->nullable();
            $t->string('status')->default('success');
            $t->dateTime('created_at')->nullable();
        });

        Schema::create('payout_accounts', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('agency_id');
            $t->string('type')->default('bank');
            $t->string('account_holder_name')->nullable();
            $t->string('bank_name')->nullable();
            $t->string('bank_account_number')->nullable();
            $t->text('bank_account_number_encrypted')->nullable();
            $t->string('account_last4', 4)->nullable();
            $t->string('bank_ifsc')->nullable();
            $t->string('account_type')->nullable();
            $t->string('upi_vpa')->nullable();
            $t->string('verification_status')->default('verified');
            $t->boolean('is_default')->default(true);
            $t->timestamps();
        });

        Schema::create('payouts', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('agency_id');
            $t->string('payout_code', 20)->nullable()->unique();
            $t->unsignedInteger('payout_account_id')->nullable();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3)->default('INR');
            $t->date('period_start')->nullable();
            $t->date('period_end')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('status')->default('pending');
            $t->string('provider')->nullable();
            $t->string('provider_payout_id')->nullable();
            $t->string('idempotency_key')->nullable();
            $t->dateTime('requested_at')->nullable();
            $t->unsignedBigInteger('requested_by')->nullable();
            $t->dateTime('reviewed_at')->nullable();
            $t->unsignedInteger('reviewed_by')->nullable();
            $t->dateTime('approved_at')->nullable();
            $t->unsignedInteger('approved_by')->nullable();
            $t->dateTime('processing_at')->nullable();
            $t->dateTime('paid_at')->nullable();
            $t->string('transfer_reference', 100)->nullable();
            $t->date('transfer_date')->nullable();
            $t->decimal('paid_amount', 12, 2)->nullable();
            $t->string('paid_currency', 3)->nullable();
            $t->unsignedInteger('paid_by')->nullable();
            $t->text('payment_notes')->nullable();
            $t->dateTime('rejected_at')->nullable();
            $t->string('rejection_reason')->nullable();
            $t->unsignedInteger('rejected_by')->nullable();
            $t->dateTime('cancelled_at')->nullable();
            $t->text('notes')->nullable();
        });

        Schema::create('transaction_payout', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('transaction_id');
            $t->unsignedInteger('payout_id');
            $t->decimal('amount', 12, 2);
            $t->timestamps();
            $t->unique(['transaction_id', 'payout_id']);
        });

        Schema::create('agency_ledger', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('agency_id');
            $t->unsignedInteger('store_id')->nullable();
            $t->unsignedInteger('transaction_id')->nullable();
            $t->unsignedInteger('payout_id')->nullable();
            $t->string('type');
            $t->decimal('amount', 12, 2);
            $t->string('description')->nullable();
            $t->dateTime('created_at')->nullable();
        });
    }

    protected function createTenancyTables(): void
    {
        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/2026_08_19_074534_create_organisations_table.php',
                'database/migrations/2026_08_19_074535_create_organisation_user_table.php',
                'database/migrations/2026_08_19_074537_create_organisation_settings_table.php',
                'database/migrations/2026_09_22_100200_add_commission_revenue_source_to_organisation_settings_table.php',
            ],
        ])->run();

        // The real migration also adds an FK onto agencies; sqlite can't
        // ALTER one in, and only the column matters here.
        Schema::table('organisations', function (Blueprint $t) {
            $t->unsignedInteger('brix_agency_id')->nullable();
        });
    }

    /** In-memory stand-in for the read-only `cartninja` connection. */
    protected function fakeCartninjaShops(): void
    {
        config()->set('database.connections.cartninja', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('cartninja');

        DB::connection('cartninja')->statement(
            'CREATE TABLE shops (id INTEGER PRIMARY KEY AUTOINCREMENT, shop_domain TEXT, plan_key TEXT, created_at TEXT)'
        );
    }

    protected function loginAsAdmin(string $role = 'SUPER_ADMIN'): \App\Models\Admin\AdminUser
    {
        $admin = \App\Models\Admin\AdminUser::create([
            'name' => 'Admin', 'email' => 'admin'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'), 'role' => $role, 'status' => 'active',
        ]);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    protected function seedCartninjaShop(string $domain, $createdAt): void
    {
        DB::connection('cartninja')->table('shops')->insert([
            'shop_domain' => $domain,
            'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m-d H:i:s') : $createdAt,
        ]);
    }
}
