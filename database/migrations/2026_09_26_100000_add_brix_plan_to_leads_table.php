<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot of the plan_key read from the read-only `cartninja` connection
     * when a manually-added lead's website already matches an installed
     * shop. Cached here (rather than re-queried on every page load) because
     * that connection is BRIX's, not ours, and may not always be reachable.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('brix_plan', 40)->nullable()->after('brix_status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('brix_plan');
        });
    }
};
