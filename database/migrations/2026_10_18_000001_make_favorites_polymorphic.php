<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('favoritable_type');
            $table->unsignedBigInteger('favoritable_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'favorites_user_favoritable_unique');
            $table->index(['favoritable_type', 'favoritable_id']);
        });

        $rows = DB::table('favorites')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('favorites_new')->insert([
                'id' => $row->id,
                'user_id' => $row->user_id,
                'favoritable_type' => 'studio',
                'favoritable_id' => $row->studio_id,
                'created_at' => $row->created_at,
            ]);
        }

        Schema::drop('favorites');
        Schema::rename('favorites_new', 'favorites');
    }

    public function down(): void
    {
        Schema::create('favorites_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'studio_id']);
        });

        $rows = DB::table('favorites')->where('favoritable_type', 'studio')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('favorites_old')->insert([
                'id' => $row->id,
                'user_id' => $row->user_id,
                'studio_id' => $row->favoritable_id,
                'created_at' => $row->created_at,
            ]);
        }

        Schema::drop('favorites');
        Schema::rename('favorites_old', 'favorites');
    }
};
