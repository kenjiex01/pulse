<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->string('night_diff_start', 5)->nullable()->after('nd_deduct_break');
            $table->string('night_diff_end', 5)->nullable()->after('night_diff_start');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->dropColumn(['night_diff_start', 'night_diff_end']);
        });
    }
};
