<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_withholding_tax_computations', function (Blueprint $table) {
            $table->unsignedTinyInteger('withholding_tax_computation_id')->primary();
            $table->string('withholding_tax_computation', 45);
        });

        DB::table('lu_withholding_tax_computations')->insert([
            ['withholding_tax_computation_id' => 1, 'withholding_tax_computation' => 'Regular'],
            ['withholding_tax_computation_id' => 2, 'withholding_tax_computation' => 'Year End Tax'],
            ['withholding_tax_computation_id' => 3, 'withholding_tax_computation' => 'Annualized Tax'],
        ]);

        Schema::table('tbl_payroll_setting_others', function (Blueprint $table) {
            $table->unsignedInteger('last_batch_no')->default(0)->after('is_deduction_loan_priority_enabled');
        });

        Schema::table('trn_payroll_batches', function (Blueprint $table) {
            $table->unique(['payroll_calendar_id', 'batch_no'], 'payroll_batch_calendar_no_unique');
            $table->foreign('withholding_tax_computation_id')
                ->references('withholding_tax_computation_id')
                ->on('lu_withholding_tax_computations');
        });
    }

    public function down(): void
    {
        Schema::table('trn_payroll_batches', function (Blueprint $table) {
            $table->dropForeign(['withholding_tax_computation_id']);
            $table->dropUnique('payroll_batch_calendar_no_unique');
        });

        Schema::table('tbl_payroll_setting_others', function (Blueprint $table) {
            $table->dropColumn('last_batch_no');
        });

        Schema::dropIfExists('lu_withholding_tax_computations');
    }
};
