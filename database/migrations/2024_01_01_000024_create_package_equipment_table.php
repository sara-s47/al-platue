<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_equipment', function (Blueprint $table) {
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->unsignedInteger('quantity');

            $table->primary(['package_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_equipment');
    }
};
