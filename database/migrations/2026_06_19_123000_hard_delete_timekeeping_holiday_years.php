<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_timekeeping_holiday_years') && Schema::hasColumn('tbl_timekeeping_holiday_years', 'deleted_at')) {
            DB::table('tbl_timekeeping_holiday_years')->whereNotNull('deleted_at')->delete();

            Schema::table('tbl_timekeeping_holiday_years', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tbl_timekeeping_holiday_years') && ! Schema::hasColumn('tbl_timekeeping_holiday_years', 'deleted_at')) {
            Schema::table('tbl_timekeeping_holiday_years', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }
};
