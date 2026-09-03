<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bridges this app's own login/session model (organisations) to the
     * real agency record in the brix_superadmin database (agencies.id).
     * Deliberately not a foreign key — brix_superadmin lives on a
     * different connection/database, so referential integrity there is
     * enforced in application code (Organisation::brixAgency()), not by
     * the schema.
     */
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->unsignedInteger('brix_agency_id')->nullable()->after('website')->index();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('brix_agency_id');
        });
    }
};
