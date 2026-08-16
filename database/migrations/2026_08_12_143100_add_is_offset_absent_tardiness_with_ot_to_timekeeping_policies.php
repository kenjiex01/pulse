<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_timekeeping_policies', 'is_offset_absent_tardiness_with_ot')) {
                $table->boolean('is_offset_absent_tardiness_with_ot')->default(false)->after('is_offset_lwop');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_timekeeping_policies', 'is_offset_absent_tardiness_with_ot')) {
                $table->dropColumn('is_offset_absent_tardiness_with_ot');
            }
        });
    }
};
