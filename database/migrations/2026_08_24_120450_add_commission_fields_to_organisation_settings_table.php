<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisation_settings', function (Blueprint $table) {
            $table->decimal('default_commission_rate', 5, 2)->default(30)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('organisation_settings', function (Blueprint $table) {
            $table->dropColumn('default_commission_rate');
        });
    }
};
