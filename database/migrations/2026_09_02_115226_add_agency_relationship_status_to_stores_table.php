<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors agency_stores.relationship_status (brix_superadmin) onto the
     * local store row, so the Stores index card can render agency
     * connection state (Pending / Authorized / Active / Disconnected)
     * without a cross-database query on every page load. Kept in sync by
     * the agency store-connection controller and internal webhook at
     * every transition; brix_superadmin's agency_stores row remains the
     * source of truth.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('agency_relationship_status')->nullable()->after('authorization_status');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('agency_relationship_status');
        });
    }
};
