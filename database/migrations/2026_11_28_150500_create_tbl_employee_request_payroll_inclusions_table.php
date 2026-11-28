<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_request_payroll_inclusions', function (Blueprint $table) {
            $table->id('employee_request_payroll_inclusion_id');
            $table->unsignedBigInteger('employee_request_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('effect_key', 191);
            $table->string('effect_type', 40);
            $table->date('effect_date');
            $table->unsignedBigInteger('payroll_batch_id')->nullable();
            $table->unsignedBigInteger('payroll_batch_detail_id')->nullable();
            $table->decimal('hours', 10, 4)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->dateTime('included_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('effect_key', 'er_payroll_effect_key_unique');
            $table->index(['employee_id', 'effect_date'], 'er_payroll_employee_date_idx');
            $table->foreign('employee_request_id', 'er_payroll_request_fk')
                ->references('employee_request_id')
                ->on('tbl_employee_requests')
                ->cascadeOnDelete();
            $table->foreign('employee_id', 'er_payroll_employee_fk')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('payroll_batch_id', 'er_payroll_batch_fk')
                ->references('payroll_batch_id')
                ->on('trn_payroll_batches')
                ->nullOnDelete();
            $table->foreign('payroll_batch_detail_id', 'er_payroll_detail_fk')
                ->references('payroll_batch_detail_id')
                ->on('trn_payroll_batch_details')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_request_payroll_inclusions');
    }
};
