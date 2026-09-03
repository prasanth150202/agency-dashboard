<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('plan')->default('Starter')->after('status');
            // Null = use the agency's default_commission_rate ("Agency Rate").
            // Set = a per-store override ("Custom Rate").
            $table->decimal('commission_rate', 5, 2)->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['plan', 'commission_rate']);
        });
    }
};
