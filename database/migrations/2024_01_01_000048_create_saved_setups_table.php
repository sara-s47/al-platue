<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('guest_count');
            $table->json('configuration_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_setups');
    }
};
