<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_links', function (Blueprint $table) {
            $table->id();
            // agencies.id is `int unsigned` in the real (merged) schema, not
            // Laravel's default bigint — no repo migration owns the
            // `agencies` table itself, so no hard FK constraint is declared
            // here (same reasoning as store_connection_attempts.store_id).
            $table->unsignedInteger('agency_id');
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('channel', 20);
            $table->string('campaign_name')->nullable();
            $table->string('destination_url');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_links');
    }
};
