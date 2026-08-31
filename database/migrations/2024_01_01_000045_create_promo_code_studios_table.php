<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_studios', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();

            $table->primary(['promo_code_id', 'studio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_studios');
    }
};
