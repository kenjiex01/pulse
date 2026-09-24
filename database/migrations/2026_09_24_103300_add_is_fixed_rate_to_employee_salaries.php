<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->boolean('is_fixed_rate')
                ->default(false)
                ->after('is_above_minimum_wage_earner');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->dropColumn('is_fixed_rate');
        });
    }
};
