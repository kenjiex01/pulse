<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_payroll_overtimes', function (Blueprint $table) {
            $table->id('payroll_overtime_id');
            $table->unsignedBigInteger('payroll_transaction_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->dateTime('ot_start');
            $table->dateTime('ot_end');

            $table->foreign('payroll_transaction_id')
                ->references('payroll_transaction_id')
                ->on('raw_payroll_transactions')
                ->cascadeOnDelete();
            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();

            $table->index(['payroll_transaction_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_payroll_overtimes');
    }
};
