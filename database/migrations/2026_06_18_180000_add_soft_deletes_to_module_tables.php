<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'users',
        'roles',
        'tbl_employees',
        'tbl_campuses',
        'tbl_designations',
        'tbl_positions',
        'tbl_ranks',
        'tbl_employment_types',
        'tbl_employee_departments',
        'tbl_colleges',
        'tbl_programs',
        'tbl_income_types',
        'tbl_deduction_types',
        'tbl_loan_types',
        'tbl_leave_types',
        'tbl_rate_groups',
        'tbl_rate_group_day_types',
        'tbl_nd_rate_groups',
        'tbl_nd_rate_group_day_types',
        'tbl_day_types',
        'tbl_govt_table_pagibig',
        'tbl_govt_table_philhealth',
        'tbl_govt_table_sss',
        'tbl_withholding_tax_classes',
        'tbl_govt_table_wtax_annual_2023',
        'tbl_shift_codes',
        'tbl_shift_code_breaks',
        'tbl_employee_employment_information',
        'tbl_employee_salaries',
        'tbl_employee_salary_incomes',
        'tbl_employee_salary_deductions',
        'tbl_timekeeping_policy_tardiness',
        'tbl_timekeeping_policy_undertime',
        'tbl_timekeeping_policy_overtime',
        'tbl_timekeeping_policy_breaks',
        'tbl_timekeeping_policy_nd',
        'tbl_timekeeping_policy_leave',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
