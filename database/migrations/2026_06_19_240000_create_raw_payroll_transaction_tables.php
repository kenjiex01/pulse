<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_payroll_transactions', function (Blueprint $table) {
            $table->id('payroll_transaction_id');
            $table->unsignedTinyInteger('payroll_transaction_type_id');
            $table->unsignedBigInteger('payroll_calendar_id');
            $table->unsignedBigInteger('uploaded_by_id');
            $table->timestamp('dt_uploaded')->useCurrent();
            $table->unsignedInteger('batch_no')->default(0);
            $table->string('filename')->nullable();

            $table->foreign('payroll_calendar_id')->references('payroll_calendar_id')->on('tbl_payroll_calendar')->cascadeOnDelete();
            $table->foreign('uploaded_by_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('raw_payroll_incomes', function (Blueprint $table) {
            $table->id('payroll_income_id');
            $table->unsignedBigInteger('payroll_transaction_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('income_type_id');
            $table->decimal('taxable', 10, 2)->nullable();
            $table->decimal('non_taxable', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->boolean('is_adjustment')->nullable();

            $table->foreign('payroll_transaction_id')->references('payroll_transaction_id')->on('raw_payroll_transactions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->cascadeOnDelete();
            $table->foreign('income_type_id')->references('income_type_id')->on('tbl_income_types')->cascadeOnDelete();
        });

        Schema::create('raw_payroll_deductions', function (Blueprint $table) {
            $table->id('payroll_deduction_id');
            $table->unsignedBigInteger('payroll_transaction_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('deduction_type_id');
            $table->decimal('employee_amount', 10, 2)->nullable();
            $table->decimal('employer_amount', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->boolean('is_adjustment')->nullable();
            $table->string('reference_number', 45)->nullable();
            $table->dateTime('dt_reference')->nullable();

            $table->foreign('payroll_transaction_id')->references('payroll_transaction_id')->on('raw_payroll_transactions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->cascadeOnDelete();
            $table->foreign('deduction_type_id')->references('deduction_type_id')->on('tbl_deduction_types')->cascadeOnDelete();
        });

        Schema::create('raw_payroll_hours_worked', function (Blueprint $table) {
            $table->id('payroll_hours_worked_id');
            $table->unsignedBigInteger('payroll_transaction_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedInteger('day_type_id');
            $table->unsignedTinyInteger('time_type_id');
            $table->decimal('hours', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->nullable();

            $table->foreign('payroll_transaction_id')->references('payroll_transaction_id')->on('raw_payroll_transactions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->cascadeOnDelete();
            $table->foreign('day_type_id')->references('day_type_id')->on('tbl_day_types')->cascadeOnDelete();
            $table->foreign('time_type_id')->references('time_type_id')->on('tbl_time_types')->cascadeOnDelete();
        });

        Schema::create('raw_payroll_leaves', function (Blueprint $table) {
            $table->id('payroll_leave_id');
            $table->unsignedBigInteger('payroll_transaction_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->dateTime('dt_from');
            $table->dateTime('dt_to');
            $table->decimal('leave_hours', 5, 2)->default(0);
            $table->unsignedBigInteger('applies_to_leave_type_id')->nullable();
            $table->decimal('applied_hours', 10, 2)->nullable();
            $table->string('reason', 150)->nullable();

            $table->foreign('payroll_transaction_id')->references('payroll_transaction_id')->on('raw_payroll_transactions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('leave_type_id')->on('tbl_leave_types')->cascadeOnDelete();
            $table->foreign('applies_to_leave_type_id')->references('leave_type_id')->on('tbl_leave_types')->nullOnDelete();
        });

        Schema::create('raw_payroll_loan_payments', function (Blueprint $table) {
            $table->id('payroll_loan_payment_id');
            $table->unsignedBigInteger('payroll_transaction_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('loan_type_id');
            $table->dateTime('dt_loan');
            $table->decimal('payment', 10, 2)->nullable();
            $table->decimal('penalty', 10, 2)->nullable();
            $table->string('reference_number', 45)->nullable();
            $table->dateTime('dt_reference')->nullable();

            $table->foreign('payroll_transaction_id')->references('payroll_transaction_id')->on('raw_payroll_transactions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->cascadeOnDelete();
            $table->foreign('loan_type_id')->references('loan_type_id')->on('tbl_loan_types')->cascadeOnDelete();
        });

        Schema::create('raw_payroll_resigned_employees', function (Blueprint $table) {
            $table->id('payroll_resigned_employee_id');
            $table->unsignedBigInteger('payroll_transaction_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('dt_resigned')->nullable();

            $table->foreign('payroll_transaction_id')->references('payroll_transaction_id')->on('raw_payroll_transactions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_payroll_resigned_employees');
        Schema::dropIfExists('raw_payroll_loan_payments');
        Schema::dropIfExists('raw_payroll_leaves');
        Schema::dropIfExists('raw_payroll_hours_worked');
        Schema::dropIfExists('raw_payroll_deductions');
        Schema::dropIfExists('raw_payroll_incomes');
        Schema::dropIfExists('raw_payroll_transactions');
    }
};
