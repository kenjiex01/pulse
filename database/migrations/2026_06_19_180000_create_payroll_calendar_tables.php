<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_payroll_calendar', function (Blueprint $table) {
            $table->id('payroll_calendar_id');
            $table->unsignedTinyInteger('pay_type_id');
            $table->unsignedSmallInteger('pay_year');
            $table->unsignedSmallInteger('pay_period');
            $table->dateTime('dt_from');
            $table->dateTime('dt_to');
            $table->unsignedTinyInteger('calendar_month');
            $table->boolean('is_regular_period')->nullable();
            $table->timestamps();

            $table->foreign('pay_type_id')->references('pay_type_id')->on('lu_pay_types')->cascadeOnDelete();
            $table->unique(['pay_type_id', 'pay_year', 'pay_period']);
        });

        Schema::create('tbl_payroll_calendar_deductions', function (Blueprint $table) {
            $table->id('payroll_calendar_deduction_id');
            $table->unsignedBigInteger('payroll_calendar_id');
            $table->unsignedBigInteger('deduction_type_id');

            $table->foreign('payroll_calendar_id')->references('payroll_calendar_id')->on('tbl_payroll_calendar')->cascadeOnDelete();
            $table->foreign('deduction_type_id')->references('deduction_type_id')->on('tbl_deduction_types')->cascadeOnDelete();
            $table->unique(['payroll_calendar_id', 'deduction_type_id'], 'payroll_calendar_deduction_unique');
        });

        Schema::create('tbl_payroll_calendar_loans', function (Blueprint $table) {
            $table->id('payroll_calendar_loan_id');
            $table->unsignedBigInteger('payroll_calendar_id');
            $table->unsignedBigInteger('loan_type_id');

            $table->foreign('payroll_calendar_id')->references('payroll_calendar_id')->on('tbl_payroll_calendar')->cascadeOnDelete();
            $table->foreign('loan_type_id')->references('loan_type_id')->on('tbl_loan_types')->cascadeOnDelete();
            $table->unique(['payroll_calendar_id', 'loan_type_id'], 'payroll_calendar_loan_unique');
        });

        Schema::create('tbl_deduction_loan_priority', function (Blueprint $table) {
            $table->id('deduction_loan_priority_id');
            $table->unsignedBigInteger('deduction_type_id')->nullable();
            $table->unsignedBigInteger('loan_type_id')->nullable();
            $table->unsignedInteger('priority');

            $table->foreign('deduction_type_id')->references('deduction_type_id')->on('tbl_deduction_types')->cascadeOnDelete();
            $table->foreign('loan_type_id')->references('loan_type_id')->on('tbl_loan_types')->cascadeOnDelete();
        });

        Schema::create('tbl_payroll_setting_others', function (Blueprint $table) {
            $table->id('payroll_setting_other_id');
            $table->boolean('is_deduction_loan_priority_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_payroll_setting_others');
        Schema::dropIfExists('tbl_deduction_loan_priority');
        Schema::dropIfExists('tbl_payroll_calendar_loans');
        Schema::dropIfExists('tbl_payroll_calendar_deductions');
        Schema::dropIfExists('tbl_payroll_calendar');
    }
};
