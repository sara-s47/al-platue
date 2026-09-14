<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')
            ->where(function ($query) {
                $query->whereNull('password')->orWhere('password', '');
            })
            ->get(['id']);

        foreach ($users as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }
};
