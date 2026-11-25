<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employment_types', function (Blueprint $table) {
            $table->boolean('deduct_government_deductions')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employment_types', function (Blueprint $table) {
            $table->dropColumn('deduct_government_deductions');
        });
    }
};
