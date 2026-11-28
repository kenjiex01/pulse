<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trn_payroll_deductions', function (Blueprint $table) {
            $table->boolean('counts_toward_net')->default(true)->after('is_manual');
        });
    }

    public function down(): void
    {
        Schema::table('trn_payroll_deductions', function (Blueprint $table) {
            $table->dropColumn('counts_toward_net');
        });
    }
};
