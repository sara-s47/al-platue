<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_hospitality', function (Blueprint $table) {
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospitality_item_id')->constrained()->cascadeOnDelete();

            $table->primary(['studio_id', 'hospitality_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_hospitality');
    }
};
