<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->index('employment_info_id', 'tbl_employee_salaries_employment_info_id_index');
        });

        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->dropUnique(['employment_info_id']);
        });

        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->renameColumn('date_effective', 'date_effective_from');
            $table->date('date_effective_to')->nullable()->after('date_effective_from');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->dropColumn('date_effective_to');
            $table->renameColumn('date_effective_from', 'date_effective');
        });

        Schema::table('tbl_employee_salaries', function (Blueprint $table) {
            $table->unique('employment_info_id');
            $table->dropIndex('tbl_employee_salaries_employment_info_id_index');
        });
    }
};
