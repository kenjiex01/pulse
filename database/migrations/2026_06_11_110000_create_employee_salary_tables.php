<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_basic_computations', function (Blueprint $table) {
            $table->unsignedTinyInteger('basic_computation_id')->primary();
            $table->string('basic_computation', 100);
        });

        Schema::create('tbl_employee_salaries', function (Blueprint $table) {
            $table->id('employee_salary_id');
            $table->foreignId('employment_info_id')
                ->constrained('tbl_employee_employment_information', 'employment_info_id')
                ->cascadeOnDelete();
            $table->date('date_effective')->nullable();
            $table->unsignedTinyInteger('basic_computation_id')->nullable();
            $table->unsignedTinyInteger('pay_type_id')->nullable();
            $table->decimal('days_per_period', 8, 5)->nullable();
            $table->decimal('hours_per_day', 4, 2)->nullable();
            $table->decimal('cola_rate_per_hour', 8, 2)->default(0);
            $table->unsignedInteger('rate_group_id')->nullable();
            $table->unsignedInteger('nd_rate_group_id')->nullable();
            $table->timestamps();

            $table->unique('employment_info_id');
            $table->foreign('basic_computation_id')->references('basic_computation_id')->on('lu_basic_computations')->nullOnDelete();
            $table->foreign('pay_type_id')->references('pay_type_id')->on('lu_pay_types')->nullOnDelete();
            $table->foreign('rate_group_id')->references('rate_group_id')->on('tbl_rate_groups')->nullOnDelete();
            $table->foreign('nd_rate_group_id')->references('nd_rate_group_id')->on('tbl_nd_rate_groups')->nullOnDelete();
        });

        Schema::create('tbl_employee_salary_incomes', function (Blueprint $table) {
            $table->id('employee_salary_income_id');
            $table->foreignId('employee_salary_id')->constrained('tbl_employee_salaries', 'employee_salary_id')->cascadeOnDelete();
            $table->unsignedBigInteger('income_type_id');
            $table->decimal('taxable', 12, 2)->default(0);
            $table->decimal('non_taxable', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('income_type_id')->references('income_type_id')->on('tbl_income_types')->cascadeOnDelete();
        });

        Schema::create('tbl_employee_salary_deductions', function (Blueprint $table) {
            $table->id('employee_salary_deduction_id');
            $table->foreignId('employee_salary_id')->constrained('tbl_employee_salaries', 'employee_salary_id')->cascadeOnDelete();
            $table->unsignedBigInteger('deduction_type_id');
            $table->decimal('employee_amount', 12, 2)->default(0);
            $table->decimal('employer_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('deduction_type_id')->references('deduction_type_id')->on('tbl_deduction_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_salary_deductions');
        Schema::dropIfExists('tbl_employee_salary_incomes');
        Schema::dropIfExists('tbl_employee_salaries');
        Schema::dropIfExists('lu_basic_computations');
    }
};
