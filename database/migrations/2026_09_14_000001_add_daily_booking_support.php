<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('booking_mode', 20)->default('hourly')->after('package_id');
            $table->index('booking_mode');
        });

        Schema::table('booking_holds', function (Blueprint $table) {
            $table->string('booking_mode', 20)->default('hourly')->after('studio_id');
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->decimal('price_per_day', 12, 2)->nullable()->after('price_per_hour');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['booking_mode']);
            $table->dropColumn('booking_mode');
        });

        Schema::table('booking_holds', function (Blueprint $table) {
            $table->dropColumn('booking_mode');
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn('price_per_day');
        });
    }
};
