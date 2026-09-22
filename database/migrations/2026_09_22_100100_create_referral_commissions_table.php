<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The agency's earned amount from exactly one revenue event. The
        // rate and the rule used are frozen on the row, so later agency
        // rate or source changes never rewrite history. Not linked to
        // payouts: a commission becoming eligible does not mean it is paid.
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revenue_event_id')->constrained('referral_revenue_events')->restrictOnDelete();
            $table->unsignedInteger('agency_id');
            $table->unsignedInteger('store_id');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->restrictOnDelete();
            $table->foreignId('tracking_link_id')->nullable()->constrained('tracking_links')->nullOnDelete();

            $table->string('revenue_type', 20);
            // Snapshots, so the commission stays auditable on its own.
            $table->decimal('revenue_amount', 12, 2);
            $table->string('currency', 3);
            $table->decimal('commission_rate', 5, 2);
            // store_override | agency_default
            $table->string('rate_source', 20);
            $table->decimal('commission_amount', 12, 2);

            // pending | eligible | paid | reversed | cancelled
            $table->string('status', 20)->default('pending');
            $table->timestamp('available_at')->nullable();
            // The rule/configuration in force: revenue source mode, hierarchy, holding period.
            $table->json('rule')->nullable();
            $table->timestamps();

            // One commission per revenue event, ever.
            $table->unique('revenue_event_id');
            $table->index(['agency_id', 'status']);
            $table->index('lead_id');
            $table->index('tracking_link_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
