<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_timekeeping_employee_setup', function (Blueprint $table) {
            $table->boolean('is_auto_compute_excess_as_ot')->default(false)->after('is_populate');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_employee_setup', function (Blueprint $table) {
            $table->dropColumn('is_auto_compute_excess_as_ot');
        });
    }
};
