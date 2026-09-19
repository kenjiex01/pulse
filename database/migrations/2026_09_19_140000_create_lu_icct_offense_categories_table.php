<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lu_icct_offense_categories')) {
            Schema::create('lu_icct_offense_categories', function (Blueprint $table) {
                $table->id('icct_offense_category_id');
                $table->string('code', 16);
                $table->string('label', 32);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique('code', 'lu_icct_offense_categories_code_unique');
            });
        }

        $now = now();
        $categories = [
            ['code' => 'A', 'label' => 'A', 'sort_order' => 1],
            ['code' => 'B', 'label' => 'B', 'sort_order' => 2],
            ['code' => 'C', 'label' => 'C', 'sort_order' => 5],
            ['code' => 'D', 'label' => 'D', 'sort_order' => 7],
        ];

        foreach ($categories as $category) {
            DB::table('lu_icct_offense_categories')->updateOrInsert(
                ['code' => $category['code']],
                [
                    'label' => $category['label'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
            );
        }

        if (Schema::hasTable('lu_icct_offenses') && ! Schema::hasColumn('lu_icct_offenses', 'icct_offense_category_id')) {
            Schema::table('lu_icct_offenses', function (Blueprint $table) {
                $table->unsignedBigInteger('icct_offense_category_id')->nullable()->after('nature_of_offense');
            });
        }

        if (Schema::hasTable('lu_icct_offenses') && Schema::hasColumn('lu_icct_offenses', 'icct_offense_category_id')) {
            $categoryIds = DB::table('lu_icct_offense_categories')
                ->pluck('icct_offense_category_id', 'code');

            foreach ($categoryIds as $code => $categoryId) {
                DB::table('lu_icct_offenses')
                    ->where('category', $code)
                    ->whereNull('icct_offense_category_id')
                    ->update(['icct_offense_category_id' => $categoryId]);
            }

            Schema::table('lu_icct_offenses', function (Blueprint $table) {
                $table->foreign('icct_offense_category_id', 'lu_icct_offenses_category_fk')
                    ->references('icct_offense_category_id')
                    ->on('lu_icct_offense_categories')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lu_icct_offenses') && Schema::hasColumn('lu_icct_offenses', 'icct_offense_category_id')) {
            Schema::table('lu_icct_offenses', function (Blueprint $table) {
                $table->dropForeign('lu_icct_offenses_category_fk');
                $table->dropColumn('icct_offense_category_id');
            });
        }

        Schema::dropIfExists('lu_icct_offense_categories');
    }
};
