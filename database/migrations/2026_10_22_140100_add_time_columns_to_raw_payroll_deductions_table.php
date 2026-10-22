<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_payroll_deductions', function (Blueprint $table) {
            if (! Schema::hasColumn('raw_payroll_deductions', 'time_in')) {
                $table->string('time_in', 5)->nullable();
            }

            if (! Schema::hasColumn('raw_payroll_deductions', 'time_out')) {
                $table->string('time_out', 5)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('raw_payroll_deductions', function (Blueprint $table) {
            $table->dropColumn(['time_in', 'time_out']);
        });
    }
};
