<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lu_icct_offense_categories')) {
            DB::table('lu_icct_offense_categories')
                ->whereIn('code', ['B - C', 'B - D', 'C - D'])
                ->update(['deleted_at' => now()]);

            if (Schema::hasTable('lu_icct_offenses') && Schema::hasColumn('lu_icct_offenses', 'icct_offense_category_id')) {
                DB::table('lu_icct_offenses')
                    ->whereIn('category', ['B - C', 'B - D', 'C - D'])
                    ->update(['icct_offense_category_id' => null]);
            }
        }

        if (Schema::hasTable('lu_icct_offense_penalties') && ! Schema::hasColumn('lu_icct_offense_penalties', 'icct_offense_category_id')) {
            Schema::table('lu_icct_offense_penalties', function (Blueprint $table) {
                $table->unsignedBigInteger('icct_offense_category_id')->nullable()->after('icct_offense_penalty_id');
                $table->softDeletes();
            });

            if (Schema::hasTable('lu_icct_offense_categories')) {
                $categoryIds = DB::table('lu_icct_offense_categories')
                    ->whereNull('deleted_at')
                    ->pluck('icct_offense_category_id', 'code');

                foreach ($categoryIds as $code => $categoryId) {
                    DB::table('lu_icct_offense_penalties')
                        ->where('category', $code)
                        ->update(['icct_offense_category_id' => $categoryId]);
                }
            }

            Schema::table('lu_icct_offense_penalties', function (Blueprint $table) {
                $table->foreign('icct_offense_category_id', 'lu_icct_offense_penalties_category_fk')
                    ->references('icct_offense_category_id')
                    ->on('lu_icct_offense_categories')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lu_icct_offense_penalties') && Schema::hasColumn('lu_icct_offense_penalties', 'icct_offense_category_id')) {
            Schema::table('lu_icct_offense_penalties', function (Blueprint $table) {
                $table->dropForeign('lu_icct_offense_penalties_category_fk');
                $table->dropColumn('icct_offense_category_id');
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('lu_icct_offense_categories')) {
            DB::table('lu_icct_offense_categories')
                ->whereIn('code', ['B - C', 'B - D', 'C - D'])
                ->update(['deleted_at' => null]);
        }
    }
};
