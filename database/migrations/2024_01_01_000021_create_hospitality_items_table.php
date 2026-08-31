<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('hospitality_categories')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('pricing_model', ['per_item', 'per_unit', 'per_person', 'per_package', 'included']);
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_items');
    }
};
