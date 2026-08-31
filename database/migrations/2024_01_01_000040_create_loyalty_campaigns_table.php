<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('points', 12, 2);
            $table->string('reason')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'running', 'completed', 'cancelled'])->default('draft');
            $table->dateTime('scheduled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_campaigns');
    }
};
