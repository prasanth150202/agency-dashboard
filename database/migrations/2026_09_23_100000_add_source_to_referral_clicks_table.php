<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_clicks', function (Blueprint $table) {
            // How the visitor reached /ref/{code}. NULL means an ordinary
            // link click (every click recorded before this column existed);
            // 'qr' means the visitor scanned the link's QR code. QR reuses
            // the same /ref/{code} attribution path — this only labels it.
            $table->string('source', 20)->nullable()->after('referrer');
        });
    }

    public function down(): void
    {
        Schema::table('referral_clicks', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
