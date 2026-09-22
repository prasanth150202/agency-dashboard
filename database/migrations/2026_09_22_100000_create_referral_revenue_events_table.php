<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A revenue event is one piece of *verified* BRIX billing earned by a
        // referred store. It is a fact about billing and is never rewritten
        // or deleted when commission settings change; commissions are
        // derived from it in `referral_commissions`.
        Schema::create('referral_revenue_events', function (Blueprint $table) {
            $table->id();
            // agencies.id / stores.id are `int unsigned` in the real schema
            // and no repo migration owns them, so no hard FK (see the
            // tracking_links migration).
            $table->unsignedInteger('agency_id');
            $table->unsignedInteger('store_id');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->restrictOnDelete();
            $table->string('shop_domain');

            // subscription | usage
            $table->string('revenue_type', 20);
            // System the event id comes from, e.g. shopify_usage_record.
            $table->string('source', 40);
            // The external billing identifier (Shopify usage record GID).
            $table->string('external_event_id');

            // Signed: a future reversal event carries a negative amount.
            $table->decimal('revenue_amount', 12, 2);
            $table->string('currency', 3);
            $table->timestamp('occurred_at');
            // verified | reversed
            $table->string('status', 20)->default('verified');
            // Set on a future reversal event to point at the original.
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Idempotency: the same billing event can never be recorded twice.
            $table->unique(['source', 'external_event_id', 'revenue_type'], 'uniq_referral_revenue_event');
            $table->index(['agency_id', 'revenue_type']);
            $table->index('lead_id');
            $table->index('store_id');
            $table->index('reversal_of_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_revenue_events');
    }
};
