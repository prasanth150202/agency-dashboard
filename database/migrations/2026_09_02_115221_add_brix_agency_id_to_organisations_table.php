<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bridges this app's own login/session model (organisations) to the
     * real partner record in `agencies` — now a real foreign key, since
     * both tables live in the same merged database. agencies.id is `int
     * unsigned`, hence unsignedInteger rather than Laravel's default
     * bigint foreignId().
     */
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->unsignedInteger('brix_agency_id')->nullable()->after('website');
            $table->foreign('brix_agency_id')->references('id')->on('agencies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropForeign(['brix_agency_id']);
            $table->dropColumn('brix_agency_id');
        });
    }
};
