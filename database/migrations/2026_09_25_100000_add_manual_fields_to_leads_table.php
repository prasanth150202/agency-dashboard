<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields for an agency's own manually-added lead ("Add Lead"), distinct
     * from a referral-attributed one (Phase 3). `source` tells the two
     * apart without touching lead_stage/brix_status, which stay reserved
     * for pipeline/BRIX truth exactly as before.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('agency_id');
            $table->string('contact_name')->nullable()->after('company_name');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->string('website')->nullable()->after('contact_phone');
            $table->text('notes')->nullable()->after('website');
            // MANUAL | REFERRAL — a referral-attributed lead (Phase 3,
            // ReferralAttribution::createLead) never sets this, so it
            // defaults to REFERRAL for every existing/future referred row
            // without needing a backfill.
            $table->string('source', 20)->default('REFERRAL')->after('notes');
            // users.id — who in the agency added this lead by hand. Null
            // for a referral-attributed lead (nothing in Phase 3 acts as a user).
            $table->unsignedBigInteger('created_by')->nullable()->after('source');

            $table->index(['agency_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'source']);
            $table->dropColumn(['company_name', 'contact_name', 'contact_email', 'contact_phone', 'website', 'notes', 'source', 'created_by']);
        });
    }
};
