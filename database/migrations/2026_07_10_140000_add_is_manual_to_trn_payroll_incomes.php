<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trn_payroll_incomes', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('orig_non_taxable');
        });
    }

    public function down(): void
    {
        Schema::table('trn_payroll_incomes', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
