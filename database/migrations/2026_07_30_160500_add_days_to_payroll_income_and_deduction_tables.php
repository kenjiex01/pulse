<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trn_payroll_incomes', function (Blueprint $table) {
            $table->decimal('days', 10, 4)->nullable()->after('hours');
        });

        Schema::table('trn_payroll_deductions', function (Blueprint $table) {
            $table->decimal('days', 10, 4)->nullable()->after('hours');
        });

        Schema::table('raw_payroll_incomes', function (Blueprint $table) {
            $table->decimal('days', 10, 4)->nullable()->after('hours');
        });

        Schema::table('raw_payroll_deductions', function (Blueprint $table) {
            $table->decimal('days', 10, 4)->nullable()->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('trn_payroll_incomes', function (Blueprint $table) {
            $table->dropColumn('days');
        });

        Schema::table('trn_payroll_deductions', function (Blueprint $table) {
            $table->dropColumn('days');
        });

        Schema::table('raw_payroll_incomes', function (Blueprint $table) {
            $table->dropColumn('days');
        });

        Schema::table('raw_payroll_deductions', function (Blueprint $table) {
            $table->dropColumn('days');
        });
    }
};
