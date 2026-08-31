<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->integer('grace_minutes');
            $table->integer('overtime_minutes');
            $table->integer('interval_minutes');
            $table->decimal('rate', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'outstanding'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_records');
    }
};
