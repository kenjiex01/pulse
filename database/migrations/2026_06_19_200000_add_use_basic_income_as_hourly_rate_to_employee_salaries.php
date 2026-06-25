<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->boolean('use_basic_income_as_hourly_rate')->default(false)->after('hours_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->dropColumn('use_basic_income_as_hourly_rate');
        });
    }
};
