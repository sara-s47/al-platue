<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accessories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_per_day', 12, 2);
            $table->unsignedInteger('quantity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('accessory_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accessory_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('accessory_rentals', function (Blueprint $table) {
            $table->id();
            $table->string('rental_number', 50)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('days');
            $table->string('status', 20)->default('pending');
            $table->string('payment_method', 20);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->text('customer_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('accessory_rental_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accessory_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('accessory_rental_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('days');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->timestamps();

            $table->index('accessory_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->change();
            $table->foreignId('accessory_rental_id')
                ->nullable()
                ->after('booking_id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accessory_rental_id');
        });

        Schema::dropIfExists('accessory_rental_items');
        Schema::dropIfExists('accessory_rentals');
        Schema::dropIfExists('accessory_images');
        Schema::dropIfExists('accessories');
    }
};
