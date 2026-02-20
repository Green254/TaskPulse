<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('role_users')) {
            return;
        }

        DB::table('role_users')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->delete();

        DB::table('role_users')
            ->whereNotIn('role_id', DB::table('roles')->select('id'))
            ->delete();

        $duplicates = DB::table('role_users')
            ->select('user_id', 'role_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'role_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('role_users')
                ->where('user_id', $duplicate->user_id)
                ->where('role_id', $duplicate->role_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('role_users', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['role_id']);
        });

        Schema::table('role_users', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        try {
            Schema::table('role_users', function (Blueprint $table) {
                $table->unique(['user_id', 'role_id']);
            });
        } catch (\Throwable) {
            // Unique index may already exist on fresh databases.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('role_users')) {
            return;
        }

        try {
            Schema::table('role_users', function (Blueprint $table) {
                $table->dropUnique('role_users_user_id_role_id_unique');
            });
        } catch (\Throwable) {
            // Index may not exist on legacy databases.
        }

        Schema::table('role_users', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['role_id']);
        });

        Schema::table('role_users', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
