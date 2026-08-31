<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_closed')->default(false);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['studio_id', 'date']);
            $table->index(['studio_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_schedule_overrides');
    }
};
