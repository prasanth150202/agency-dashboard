<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * AgencySeeder/FinanceSeeder populate the dashboard with fake
     * agencies, stores, and finance records for demoing/UI development.
     * They're deliberately not called here so `php artisan db:seed` (and
     * fresh installs) start from a genuinely empty, real-data state. Run
     * them explicitly when demo data is actually wanted:
     *   php artisan db:seed --class=AgencySeeder
     *   php artisan db:seed --class=FinanceSeeder
     */
    public function run(): void
    {
        //
    }
}
