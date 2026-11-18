<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_deductions', function (Blueprint $table) {
            $table->id('employee_deduction_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('deduction_type_id');
            $table->dateTime('dt_deduction');
            $table->decimal('deduction_amount', 10, 2)->default(0);
            $table->decimal('amortization_amount', 10, 2)->default(0);
            $table->decimal('deduction_interest', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index('deduction_type_id');

            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('deduction_type_id')
                ->references('deduction_type_id')
                ->on('tbl_deduction_types')
                ->restrictOnDelete();
        });

        Schema::table('trn_payroll_deductions', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_deduction_id')->nullable()->after('deduction_type_id');
            $table->index('employee_deduction_id');
            $table->foreign('employee_deduction_id', 'payroll_deductions_employee_deduction_fk')
                ->references('employee_deduction_id')
                ->on('tbl_employee_deductions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trn_payroll_deductions', function (Blueprint $table) {
            $table->dropForeign('payroll_deductions_employee_deduction_fk');
            $table->dropColumn('employee_deduction_id');
        });

        Schema::dropIfExists('tbl_employee_deductions');
    }
};
