<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_payroll_shift_codes', function (Blueprint $table) {
            $table->id('payroll_shift_code_id');
            $table->unsignedBigInteger('payroll_transaction_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->unsignedInteger('shift_code_id');

            $table->foreign('payroll_transaction_id')
                ->references('payroll_transaction_id')
                ->on('raw_payroll_transactions')
                ->cascadeOnDelete();
            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('shift_code_id')
                ->references('shift_code_id')
                ->on('tbl_shift_codes')
                ->restrictOnDelete();

            $table->index(['payroll_transaction_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_payroll_shift_codes');
    }
};
