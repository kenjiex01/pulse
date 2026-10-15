<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employee_loans', function (Blueprint $table) {
            $table->boolean('deduct_half_per_cutoff')->default(false)->after('dt_start_payment');
            $table->unsignedTinyInteger('deduction_cutoff')->nullable()->after('deduct_half_per_cutoff');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employee_loans', function (Blueprint $table) {
            $table->dropColumn(['deduct_half_per_cutoff', 'deduction_cutoff']);
        });
    }
};
