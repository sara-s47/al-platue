<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_dimension_scores', function (Blueprint $table) {
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dimension_id')->constrained('review_dimensions')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');

            $table->primary(['review_id', 'dimension_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_dimension_scores');
    }
};
