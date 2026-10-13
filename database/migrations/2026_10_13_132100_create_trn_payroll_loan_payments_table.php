<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trn_payroll_loan_payments', function (Blueprint $table) {
            $table->id('payroll_loan_payment_id');
            $table->unsignedBigInteger('payroll_batch_detail_id');
            $table->unsignedBigInteger('employee_loan_id');
            $table->decimal('payment', 10, 2)->default(0);
            $table->decimal('penalty', 10, 2)->default(0);
            $table->boolean('is_editable')->nullable();
            $table->boolean('is_deletable')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_loan_id');
            $table->unique(
                ['payroll_batch_detail_id', 'employee_loan_id'],
                'payroll_loan_payment_detail_loan_unique',
            );

            $table->foreign('payroll_batch_detail_id', 'payroll_loan_payments_detail_fk')
                ->references('payroll_batch_detail_id')
                ->on('trn_payroll_batch_details')
                ->cascadeOnDelete();
            $table->foreign('employee_loan_id', 'payroll_loan_payments_loan_fk')
                ->references('employee_loan_id')
                ->on('tbl_employee_loans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trn_payroll_loan_payments');
    }
};
