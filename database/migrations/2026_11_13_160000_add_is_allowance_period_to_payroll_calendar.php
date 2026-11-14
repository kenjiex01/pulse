<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_payroll_calendar', function (Blueprint $table) {
            $table->boolean('is_allowance_period')->nullable()->after('is_regular_period');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_payroll_calendar', function (Blueprint $table) {
            $table->dropColumn('is_allowance_period');
        });
    }
};
