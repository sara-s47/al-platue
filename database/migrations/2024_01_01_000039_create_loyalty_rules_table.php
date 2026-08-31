<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('rule_type', ['welcome', 'booking', 'spending', 'redemption', 'expiration']);
            $table->decimal('value', 12, 2)->nullable();
            $table->decimal('points', 12, 2);
            $table->unsignedInteger('min_redemption_points')->nullable();
            $table->decimal('max_redemption_amount', 12, 2)->nullable();
            $table->decimal('max_redemption_percentage', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
    }
};
