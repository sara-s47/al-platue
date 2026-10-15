<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->index(['is_active', 'sort_order']);
        });

        DB::table('studios')->orderBy('id')->pluck('id')->each(function ($id, $index) {
            DB::table('studios')->where('id', $id)->update(['sort_order' => $index + 1]);
        });
    }

    public function down(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
