<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employee_employment_information', function (Blueprint $table) {
            $table->date('probationary_end_date')->nullable()->after('hire_date');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employee_employment_information', function (Blueprint $table) {
            $table->dropColumn('probationary_end_date');
        });
    }
};
