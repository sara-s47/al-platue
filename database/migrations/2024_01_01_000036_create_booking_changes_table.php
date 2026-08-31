<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['reschedule', 'extension', 'overtime', 'cancellation']);
            $table->dateTime('old_start_at')->nullable();
            $table->dateTime('old_end_at')->nullable();
            $table->dateTime('new_start_at')->nullable();
            $table->dateTime('new_end_at')->nullable();
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('price_difference', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_changes');
    }
};
