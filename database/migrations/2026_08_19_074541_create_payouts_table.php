<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('pending'); // pending, processing, paid
            $table->timestamps();

            $table->index(['organisation_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
