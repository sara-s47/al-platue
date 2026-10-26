<?php

use App\Enums\CatalogSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_section_covers', function (Blueprint $table) {
            $table->id();
            $table->string('section')->unique();
            $table->string('cover_image')->nullable();
            $table->timestamps();
        });

        $now = now();

        foreach (CatalogSection::values() as $section) {
            DB::table('catalog_section_covers')->insert([
                'section' => $section,
                'cover_image' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_section_covers');
    }
};
