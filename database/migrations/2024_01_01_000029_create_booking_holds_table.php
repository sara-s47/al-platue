<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('expires_at');
            $table->enum('status', ['active', 'expired', 'converted', 'cancelled'])->default('active');
            $table->timestamps();

            $table->index('studio_id');
            $table->index('start_at');
            $table->index('end_at');
            $table->index('expires_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};
