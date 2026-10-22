<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trn_payroll_disputes', function (Blueprint $table) {
            $table->id('payroll_dispute_id');
            $table->unsignedBigInteger('payroll_batch_id');
            $table->unsignedBigInteger('payroll_batch_detail_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('category', 3);
            $table->date('work_date');
            $table->unsignedBigInteger('shift_code_id')->nullable();
            $table->string('time_in', 5)->nullable();
            $table->string('time_out', 5)->nullable();
            $table->string('ot_start', 5)->nullable();
            $table->string('ot_end', 5)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->unsignedBigInteger('source_payroll_batch_id')->nullable();
            $table->decimal('expected_pay', 12, 2)->default(0);
            $table->decimal('expected_ot_pay', 12, 2)->default(0);
            $table->decimal('computed_hours', 10, 4)->nullable();
            $table->decimal('computed_amount', 12, 2)->nullable();
            $table->decimal('computed_ot_hours', 10, 4)->nullable();
            $table->decimal('computed_ot_amount', 12, 2)->nullable();
            $table->unsignedBigInteger('payroll_income_id')->nullable();
            $table->unsignedBigInteger('ot_payroll_income_id')->nullable();
            $table->unsignedBigInteger('payroll_transaction_id')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'work_date', 'category'], 'idx_payroll_disputes_employee_date');
            $table->index('payroll_batch_detail_id', 'idx_payroll_disputes_detail');
            $table->index('payroll_transaction_id', 'idx_payroll_disputes_transaction');

            $table->foreign('payroll_batch_id')
                ->references('payroll_batch_id')
                ->on('trn_payroll_batches')
                ->cascadeOnDelete();
            $table->foreign('payroll_batch_detail_id')
                ->references('payroll_batch_detail_id')
                ->on('trn_payroll_batch_details')
                ->cascadeOnDelete();
            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
        });

        Schema::table('trn_payroll_incomes', function (Blueprint $table) {
            $table->string('remarks', 100)->nullable()->after('non_taxable');
        });
    }

    public function down(): void
    {
        Schema::table('trn_payroll_incomes', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });

        Schema::dropIfExists('trn_payroll_disputes');
    }
};
