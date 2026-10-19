<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
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
            $table->index('created_at');
        });

        if (Schema::hasTable('accessory_rental_items')) {
            Schema::table('accessory_rental_items', function (Blueprint $table) {
                $table->foreignId('rental_order_id')
                    ->nullable()
                    ->constrained('rental_orders')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('equipment_rental_items')) {
            Schema::table('equipment_rental_items', function (Blueprint $table) {
                $table->foreignId('rental_order_id')
                    ->nullable()
                    ->constrained('rental_orders')
                    ->cascadeOnDelete();
            });

            Schema::table('equipment_rental_items', function (Blueprint $table) {
                $table->unsignedBigInteger('equipment_rental_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('rental_order_id')
                    ->nullable()
                    ->constrained('rental_orders')
                    ->cascadeOnDelete();
            });
        }

        $this->migrateAccessoryRentals();
        $this->migrateEquipmentRentals();
    }

    protected function migrateAccessoryRentals(): void
    {
        if (! Schema::hasTable('accessory_rentals') || ! Schema::hasTable('accessory_rental_items')) {
            return;
        }

        $rows = DB::table('accessory_rentals')->orderBy('id')->get();

        foreach ($rows as $row) {
            $orderId = DB::table('rental_orders')->insertGetId([
                'order_number' => $row->rental_number,
                'user_id' => $row->user_id,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'days' => $row->days,
                'status' => $row->status,
                'payment_method' => $row->payment_method,
                'subtotal' => $row->subtotal,
                'tax_amount' => $row->tax_amount,
                'total_amount' => $row->total_amount,
                'paid_amount' => $row->paid_amount,
                'remaining_amount' => $row->remaining_amount,
                'customer_notes' => $row->customer_notes,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            DB::table('accessory_rental_items')
                ->where('accessory_rental_id', $row->id)
                ->update(['rental_order_id' => $orderId]);

            if (Schema::hasColumn('payments', 'accessory_rental_id')) {
                DB::table('payments')
                    ->where('accessory_rental_id', $row->id)
                    ->update(['rental_order_id' => $orderId]);
            }
        }
    }

    protected function migrateEquipmentRentals(): void
    {
        if (! Schema::hasTable('equipment_rentals') || ! Schema::hasTable('equipment_rental_items')) {
            return;
        }

        $rows = DB::table('equipment_rentals')->orderBy('id')->get();

        foreach ($rows as $row) {
            $orderNumber = $row->rental_number;

            if (DB::table('rental_orders')->where('order_number', $orderNumber)->exists()) {
                $orderNumber = 'M-'.$orderNumber;
            }

            $orderId = DB::table('rental_orders')->insertGetId([
                'order_number' => $orderNumber,
                'user_id' => $row->user_id,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'days' => $row->days,
                'status' => $row->status,
                'payment_method' => $row->payment_method,
                'subtotal' => $row->subtotal,
                'tax_amount' => $row->tax_amount,
                'total_amount' => $row->total_amount,
                'paid_amount' => $row->paid_amount,
                'remaining_amount' => $row->remaining_amount,
                'customer_notes' => $row->customer_notes,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            DB::table('equipment_rental_items')
                ->where('equipment_rental_id', $row->id)
                ->update(['rental_order_id' => $orderId]);

            if (Schema::hasColumn('payments', 'equipment_rental_id')) {
                DB::table('payments')
                    ->where('equipment_rental_id', $row->id)
                    ->update(['rental_order_id' => $orderId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'rental_order_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('rental_order_id');
            });
        }

        if (Schema::hasTable('equipment_rental_items') && Schema::hasColumn('equipment_rental_items', 'rental_order_id')) {
            Schema::table('equipment_rental_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('rental_order_id');
            });
        }

        if (Schema::hasTable('accessory_rental_items') && Schema::hasColumn('accessory_rental_items', 'rental_order_id')) {
            Schema::table('accessory_rental_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('rental_order_id');
            });
        }

        Schema::dropIfExists('rental_orders');
    }
};
