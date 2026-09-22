<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // organisation_settings is this app's own per-organisation settings
        // table (it already carries `currency`); the commission revenue
        // source is agency-level configuration, so it lives here rather than
        // on a referral link or in a new table. A missing settings row means
        // the default, 'both'.
        Schema::table('organisation_settings', function (Blueprint $table) {
            // subscription | usage | both
            $table->string('commission_revenue_source', 20)->default('both')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('organisation_settings', function (Blueprint $table) {
            $table->dropColumn('commission_revenue_source');
        });
    }
};
