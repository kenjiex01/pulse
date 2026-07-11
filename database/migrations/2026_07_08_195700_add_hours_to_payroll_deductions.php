<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trn_payroll_deductions', function (Blueprint $table) {
            $table->decimal('hours', 10, 4)->nullable()->after('deduction_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('trn_payroll_deductions', function (Blueprint $table) {
            $table->dropColumn('hours');
        });
    }
};
