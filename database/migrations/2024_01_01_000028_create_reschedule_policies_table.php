<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reschedule_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('min_notice_hours');
            $table->integer('max_reschedules');
            $table->decimal('fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reschedule_policies');
    }
};
