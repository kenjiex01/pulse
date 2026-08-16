<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trn_payroll_attendance_days', function (Blueprint $table) {
            $table->id('payroll_attendance_day_id');
            $table->unsignedBigInteger('payroll_batch_detail_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->string('day_type', 32)->default('Regular');
            $table->unsignedInteger('shift_code_id')->nullable();
            $table->string('time_in', 16)->nullable();
            $table->string('time_out', 16)->nullable();
            $table->decimal('basic', 10, 2)->nullable();
            $table->decimal('excess_hours', 10, 2)->nullable();
            $table->decimal('ot', 10, 2)->nullable();
            $table->decimal('sot', 10, 2)->nullable();
            $table->decimal('ndiff', 10, 2)->nullable();
            $table->decimal('ndot', 10, 2)->nullable();
            $table->decimal('ndsot', 10, 2)->nullable();
            $table->decimal('late', 10, 2)->nullable();
            $table->decimal('undertime', 10, 2)->nullable();
            $table->decimal('break_late', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payroll_batch_detail_id', 'payroll_att_days_batch_detail_fk')
                ->references('payroll_batch_detail_id')
                ->on('trn_payroll_batch_details')
                ->cascadeOnDelete();
            $table->foreign('employee_id', 'payroll_att_days_employee_fk')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('shift_code_id', 'payroll_att_days_shift_fk')
                ->references('shift_code_id')
                ->on('tbl_shift_codes')
                ->nullOnDelete();

            $table->unique(
                ['payroll_batch_detail_id', 'work_date'],
                'payroll_att_days_detail_date_unique'
            );
            $table->index(['employee_id', 'work_date'], 'payroll_att_days_employee_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trn_payroll_attendance_days');
    }
};
