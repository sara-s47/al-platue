<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accessory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('accessories', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('id')
                ->constrained('accessory_categories')
                ->restrictOnDelete();
            $table->string('cover_image')->nullable()->after('is_active');
        });

        foreach (['studios', 'equipment', 'portable_bathrooms', 'caravans', 'generators'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('cover_image')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('accessories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('cover_image');
        });

        foreach (['studios', 'equipment', 'portable_bathrooms', 'caravans', 'generators'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('cover_image');
            });
        }

        Schema::dropIfExists('accessory_categories');
    }
};
