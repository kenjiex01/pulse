<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });

        if (Schema::hasColumn('users', 'role_id')) {
            DB::table('users')
                ->whereNotNull('role_id')
                ->orderBy('id')
                ->lazyById()
                ->each(function ($user) {
                    DB::table('tbl_user_roles')->insertOrIgnore([
                        'user_id' => $user->id,
                        'role_id' => $user->role_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('role_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('tbl_user_roles')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($assignment) {
                DB::table('users')
                    ->where('id', $assignment->user_id)
                    ->whereNull('role_id')
                    ->update(['role_id' => $assignment->role_id]);
            });

        Schema::dropIfExists('tbl_user_roles');
    }
};
