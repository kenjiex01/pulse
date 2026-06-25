<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_payroll_batch_status', function (Blueprint $table) {
            $table->unsignedTinyInteger('payroll_batch_status_id')->primary();
            $table->string('payroll_batch_status', 45);
        });

        Schema::create('trn_payroll_batches', function (Blueprint $table) {
            $table->id('payroll_batch_id');
            $table->unsignedBigInteger('payroll_calendar_id');
            $table->unsignedInteger('batch_no')->default(0);
            $table->unsignedBigInteger('created_by_id');
            $table->timestamp('dt_created')->useCurrent();
            $table->unsignedBigInteger('locked_for_id')->nullable();
            $table->dateTime('dt_locked')->nullable();
            $table->unsignedBigInteger('processed_by_id')->nullable();
            $table->dateTime('dt_processed')->nullable();
            $table->unsignedTinyInteger('payroll_batch_status_id')->default(1);
            $table->unsignedInteger('progress_current')->default(0);
            $table->unsignedInteger('progress_total')->default(0);
            $table->unsignedTinyInteger('withholding_tax_computation_id')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payroll_calendar_id')->references('payroll_calendar_id')->on('tbl_payroll_calendar')->cascadeOnDelete();
            $table->foreign('payroll_batch_status_id')->references('payroll_batch_status_id')->on('lu_payroll_batch_status');
            $table->foreign('created_by_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('trn_payroll_batch_details', function (Blueprint $table) {
            $table->id('payroll_batch_detail_id');
            $table->unsignedBigInteger('payroll_batch_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payroll_batch_id')->references('payroll_batch_id')->on('trn_payroll_batches')->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->cascadeOnDelete();
            $table->unique(['payroll_batch_id', 'employee_id'], 'payroll_batch_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trn_payroll_batch_details');
        Schema::dropIfExists('trn_payroll_batches');
        Schema::dropIfExists('lu_payroll_batch_status');
    }
};
