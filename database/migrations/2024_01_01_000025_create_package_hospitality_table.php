<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_hospitality', function (Blueprint $table) {
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospitality_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');

            $table->primary(['package_id', 'hospitality_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_hospitality');
    }
};
