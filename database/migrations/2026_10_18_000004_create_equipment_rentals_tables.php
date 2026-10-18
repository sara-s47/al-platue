<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->decimal('price_per_day', 12, 2)->nullable()->after('price');
        });

        DB::table('equipment')->whereNull('price_per_day')->update([
            'price_per_day' => DB::raw('price'),
        ]);

        Schema::create('equipment_rentals', function (Blueprint $table) {
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

        Schema::create('equipment_rental_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignId('equipment_rental_id')->constrained('equipment_rentals')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('days');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->timestamps();

            $table->index('equipment_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('equipment_rental_id')
                ->nullable()
                ->after('accessory_rental_id')
                ->constrained('equipment_rentals')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_rental_id');
        });

        Schema::dropIfExists('equipment_rental_items');
        Schema::dropIfExists('equipment_rentals');

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('price_per_day');
        });
    }
};
