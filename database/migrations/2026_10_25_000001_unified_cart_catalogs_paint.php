<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createDayCatalog('portable_bathrooms', 'portable_bathroom_images', 'portable_bathroom_id');
        $this->createDayCatalog('caravans', 'caravan_images', 'caravan_id');
        $this->createDayCatalog('generators', 'generator_images', 'generator_id');

        if (Schema::hasTable('rental_orders') && ! Schema::hasTable('orders')) {
            Schema::rename('rental_orders', 'orders');
        } elseif (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 50)->unique();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->unsignedInteger('days')->default(0);
                $table->string('status', 20)->default('pending');
                $table->string('payment_method', 20)->default('cash');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('remaining_amount', 12, 2)->default(0);
                $table->text('customer_notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('status');
                $table->index('created_at');
            });
        }

        $this->createOrderLineTable('portable_bathroom_order_items', 'portable_bathroom_id', 'portable_bathrooms');
        $this->createOrderLineTable('caravan_order_items', 'caravan_id', 'caravans');
        $this->createOrderLineTable('generator_order_items', 'generator_id', 'generators');

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'booking_id')) {
                $table->foreignId('booking_id')->nullable()->after('user_id')->constrained('bookings')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'paint_color_id')) {
                $table->unsignedBigInteger('paint_color_id')->nullable()->after('customer_notes');
            }
            if (! Schema::hasColumn('orders', 'paint_surcharge')) {
                $table->decimal('paint_surcharge', 12, 2)->default(0)->after('paint_color_id');
            }
            if (! Schema::hasColumn('orders', 'studio_amount')) {
                $table->decimal('studio_amount', 12, 2)->default(0)->after('subtotal');
            }
        });

        $this->renameRentalOrderFk('accessory_rental_items');
        $this->renameRentalOrderFk('equipment_rental_items');
        $this->renameRentalOrderFk('payments');

        if (Schema::hasTable('cart_items') && ! Schema::hasColumn('cart_items', 'meta')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->json('meta')->nullable()->after('quantity');
            });
        }

        if (Schema::hasTable('studios')) {
            Schema::table('studios', function (Blueprint $table) {
                if (! Schema::hasColumn('studios', 'paint_pricing_mode')) {
                    $table->string('paint_pricing_mode', 20)->default('flat_extra')->after('sort_order');
                }
                if (! Schema::hasColumn('studios', 'flat_extra_paint_price')) {
                    $table->decimal('flat_extra_paint_price', 12, 2)->nullable()->after('paint_pricing_mode');
                }
            });
        }

        if (! Schema::hasTable('studio_paint_colors')) {
            Schema::create('studio_paint_colors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('hex', 20)->nullable();
                $table->boolean('is_default')->default(false);
                $table->decimal('price', 12, 2)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['studio_id', 'is_default']);
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'paint_color_id')) {
            Schema::table('orders', function (Blueprint $table) {
                // FK after studio_paint_colors exists
            });
            try {
                Schema::table('orders', function (Blueprint $table) {
                    $table->foreign('paint_color_id')->references('id')->on('studio_paint_colors')->nullOnDelete();
                });
            } catch (\Throwable) {
                // already exists or sqlite quirk
            }
        }

        $this->backfillOrdersFromBookings();
    }

    protected function createDayCatalog(string $table, string $imagesTable, string $fk): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price_per_day', 12, 2);
                $table->unsignedInteger('quantity')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('is_active');
            });
        }

        if (! Schema::hasTable($imagesTable)) {
            Schema::create($imagesTable, function (Blueprint $blueprint) use ($table, $fk) {
                $blueprint->id();
                $blueprint->foreignId($fk)->constrained($table)->cascadeOnDelete();
                $blueprint->string('path', 500);
                $blueprint->integer('sort_order')->default(0);
                $blueprint->timestamps();
            });
        }
    }

    protected function createOrderLineTable(string $table, string $productFk, string $productTable): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint) use ($productFk, $productTable) {
            $blueprint->id();
            $blueprint->foreignId($productFk)->constrained($productTable)->restrictOnDelete();
            $blueprint->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $blueprint->unsignedInteger('quantity');
            $blueprint->unsignedInteger('days');
            $blueprint->decimal('unit_price', 12, 2);
            $blueprint->decimal('total_price', 12, 2);
            $blueprint->timestamps();
            $blueprint->index($productFk);
        });
    }

    protected function renameRentalOrderFk(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, 'rental_order_id') && ! Schema::hasColumn($table, 'order_id')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('order_id')->nullable()->after('id');
            });

            DB::table($table)->whereNotNull('rental_order_id')->orderBy('id')->chunkById(100, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['order_id' => $row->rental_order_id]);
                }
            });
        }
    }

    protected function backfillOrdersFromBookings(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasTable('orders')) {
            return;
        }

        $existing = DB::table('orders')->whereNotNull('booking_id')->pluck('booking_id')->all();
        $validUserIds = array_fill_keys(
            DB::table('users')->pluck('id')->map(fn ($id) => (int) $id)->all(),
            true,
        );

        DB::table('bookings')->orderBy('id')->chunkById(50, function ($bookings) use (&$existing, $validUserIds) {
            foreach ($bookings as $booking) {
                if (in_array($booking->id, $existing, true)) {
                    continue;
                }

                // Skip orphaned bookings (user deleted) — FK on orders.user_id would fail.
                if (! isset($validUserIds[(int) $booking->user_id])) {
                    continue;
                }

                $number = $booking->booking_number ?? ('BK-'.$booking->id);
                if (DB::table('orders')->where('order_number', $number)->exists()) {
                    $number = 'BK-'.$booking->id.'-'.($booking->booking_number ?? $booking->id);
                }

                $start = isset($booking->start_at) ? substr((string) $booking->start_at, 0, 10) : null;
                $end = isset($booking->end_at) ? substr((string) $booking->end_at, 0, 10) : $start;

                $createdBy = $booking->created_by ?? null;
                if ($createdBy !== null && ! isset($validUserIds[(int) $createdBy])) {
                    $createdBy = null;
                }

                DB::table('orders')->insert([
                    'order_number' => $number,
                    'user_id' => $booking->user_id,
                    'booking_id' => $booking->id,
                    'start_date' => $start,
                    'end_date' => $end,
                    'days' => 1,
                    'status' => $booking->status ?? 'confirmed',
                    'payment_method' => 'cash',
                    'subtotal' => $booking->subtotal ?? 0,
                    'studio_amount' => $booking->subtotal ?? 0,
                    'tax_amount' => $booking->tax_amount ?? 0,
                    'total_amount' => $booking->total_amount ?? 0,
                    'paid_amount' => $booking->paid_amount ?? 0,
                    'remaining_amount' => $booking->remaining_amount ?? 0,
                    'customer_notes' => $booking->customer_notes ?? null,
                    'created_by' => $createdBy,
                    'paint_surcharge' => 0,
                    'created_at' => $booking->created_at ?? now(),
                    'updated_at' => $booking->updated_at ?? now(),
                ]);

                $existing[] = $booking->id;
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generator_order_items');
        Schema::dropIfExists('caravan_order_items');
        Schema::dropIfExists('portable_bathroom_order_items');
        Schema::dropIfExists('generator_images');
        Schema::dropIfExists('generators');
        Schema::dropIfExists('caravan_images');
        Schema::dropIfExists('caravans');
        Schema::dropIfExists('portable_bathroom_images');
        Schema::dropIfExists('portable_bathrooms');
        Schema::dropIfExists('studio_paint_colors');
    }
};
