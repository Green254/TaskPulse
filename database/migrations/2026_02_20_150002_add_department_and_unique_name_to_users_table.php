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
        if (!Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('email')
                    ->constrained('departments')
                    ->nullOnDelete();
            });
        }

        // Ensure existing duplicate names are normalized before adding unique index.
        $duplicates = DB::table('users')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicates as $duplicateName) {
            $duplicateUsers = DB::table('users')
                ->where('name', $duplicateName)
                ->orderBy('id')
                ->pluck('id');

            $index = 0;
            foreach ($duplicateUsers as $userId) {
                if ($index > 0) {
                    $suffix = '-' . $userId;
                    $base = mb_substr($duplicateName, 0, max(0, 255 - mb_strlen($suffix)));
                    DB::table('users')
                        ->where('id', $userId)
                        ->update(['name' => $base . $suffix]);
                }

                $index++;
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        if (Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }
    }
};
