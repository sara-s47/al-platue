<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('image_path')->nullable();
            $table->json('colors')->nullable();
            $table->unsignedInteger('default_quantity')->default(1);
            $table->unsignedInteger('min_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['studio_id', 'is_active']);
        });

        Schema::create('booking_studio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_item_id')->constrained('studio_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('default_quantity');
            $table->unsignedInteger('min_quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->string('color')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'studio_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_studio_items');
        Schema::dropIfExists('studio_items');
    }
};
