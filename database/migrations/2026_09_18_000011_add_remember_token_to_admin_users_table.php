<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel's Auth::guard('admin')->login() always writes a
     * remember_token on login (regardless of the "remember me" checkbox
     * in some code paths) — the real admin_users table predates this
     * app's use of it as an Authenticatable model and never had one.
     */
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->rememberToken()->after('avatar_color');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
