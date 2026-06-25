<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trn_payroll_incomes', function (Blueprint $table) {
            $table->id('payroll_income_id');
            $table->unsignedBigInteger('payroll_batch_detail_id');
            $table->unsignedBigInteger('income_type_id');
            $table->decimal('taxable', 10, 2)->default(0);
            $table->decimal('non_taxable', 10, 2)->default(0);
            $table->boolean('is_editable')->nullable();
            $table->boolean('is_deletable')->nullable();
            $table->decimal('orig_taxable', 10, 2)->default(0);
            $table->decimal('orig_non_taxable', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payroll_batch_detail_id', 'payroll_incomes_batch_detail_fk')
                ->references('payroll_batch_detail_id')
                ->on('trn_payroll_batch_details')
                ->cascadeOnDelete();
            $table->foreign('income_type_id', 'payroll_incomes_income_type_fk')
                ->references('income_type_id')
                ->on('tbl_income_types')
                ->cascadeOnDelete();
        });

        Schema::create('trn_payroll_deductions', function (Blueprint $table) {
            $table->id('payroll_deduction_id');
            $table->unsignedBigInteger('payroll_batch_detail_id');
            $table->unsignedBigInteger('deduction_type_id');
            $table->decimal('employee_amount', 10, 2)->default(0);
            $table->decimal('employer_amount', 10, 2)->default(0);
            $table->string('reference_number', 45)->nullable();
            $table->dateTime('dt_reference')->nullable();
            $table->boolean('is_editable')->nullable();
            $table->boolean('is_deletable')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payroll_batch_detail_id', 'payroll_deductions_batch_detail_fk')
                ->references('payroll_batch_detail_id')
                ->on('trn_payroll_batch_details')
                ->cascadeOnDelete();
            $table->foreign('deduction_type_id', 'payroll_deductions_deduction_type_fk')
                ->references('deduction_type_id')
                ->on('tbl_deduction_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trn_payroll_deductions');
        Schema::dropIfExists('trn_payroll_incomes');
    }
};
