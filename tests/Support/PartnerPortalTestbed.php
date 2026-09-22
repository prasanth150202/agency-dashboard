<?php

namespace Tests\Support;

use App\Models\Organisation;
use App\Models\Partners\Partner;
use App\Models\User;

/**
 * Shared bootstrap for partner-portal feature tests. Builds, inside the
 * per-test in-memory sqlite database only, everything needed to render a
 * tenant-scoped page: the referral tables, the scaffolded BRIX tables,
 * the login/tenant tables and a faked read-only `cartninja` connection.
 * Never touches MySQL or any real database.
 */
trait PartnerPortalTestbed
{
    use BrixTestSchema;

    protected function bootPortal(): void
    {
        $this->migrateReferralTables();
        $this->createBrixScaffoldTables();
        $this->createFinanceScaffoldTables();
        $this->createTenancyTables();
        $this->createAppSettingsTable();
        $this->fakeCartninjaShops();
    }

    /**
     * One isolated tenant: a Partner (agencies row), its Organisation
     * bridged via brix_agency_id, and an owner User.
     *
     * @return array{agency: Partner, org: Organisation, user: User}
     */
    protected function makeTenant(string $name): array
    {
        $agency = Partner::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'owner_name' => $name,
            'owner_email' => strtolower(str_replace(' ', '', $name)).'@example.com',
        ]);

        $org = Organisation::create(['name' => $name]);
        $org->forceFill(['brix_agency_id' => $agency->id])->save();

        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => 'owner']);

        return ['agency' => $agency, 'org' => $org, 'user' => $user];
    }

    /** Log in as a tenant's user with that tenant selected server-side. */
    protected function actAs(array $tenant): static
    {
        $this->actingAs($tenant['user'])->withSession(['organisation_id' => $tenant['org']->id]);

        return $this;
    }
}
