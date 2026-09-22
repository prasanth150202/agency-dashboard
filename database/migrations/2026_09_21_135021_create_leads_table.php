<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('agency_id');
            $table->foreignId('tracking_link_id')->nullable()->constrained('tracking_links')->nullOnDelete();
            // stores.id is `int unsigned` in the real (merged) schema — set
            // only once the existing BRIX installation flow confirms the
            // actual Shopify store (see the module's Scenario A/B rule).
            // No hard FK constraint for the same reason as agency_id above.
            $table->unsignedInteger('store_id')->nullable();
            $table->string('shop_domain')->nullable();
            $table->string('lead_stage', 20)->default('NEW');
            // Deliberately separate from lead_stage — mirrors Store's real
            // installation/authorization state once matched, so an
            // agency-side pipeline label never gets conflated with BRIX's
            // own install truth.
            $table->string('brix_status', 20)->nullable();
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            // A store can only ever be attributed to one agency at a time —
            // this is the DB-level guarantee behind the "preserve existing
            // attribution" business rule. Multiple NULLs (not-yet-installed
            // leads) are allowed by both MySQL and SQLite.
            $table->unique('shop_domain');
            $table->index(['agency_id', 'lead_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
