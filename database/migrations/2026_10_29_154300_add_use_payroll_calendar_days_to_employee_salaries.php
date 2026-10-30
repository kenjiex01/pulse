<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->boolean('use_payroll_calendar_days')
                ->default(false)
                ->after('is_fixed_rate');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->dropColumn('use_payroll_calendar_days');
        });
    }
};
