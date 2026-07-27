<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_govt_table_sss', function (Blueprint $table) {
            $table->decimal('mpf_salary_credit', 10, 2)->default(0)->after('salary_credit');
            $table->decimal('employee_mpf_share', 10, 2)->default(0)->after('employee_sss');
            $table->decimal('employer_mpf_share', 10, 2)->default(0)->after('employer_sss');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_govt_table_sss', function (Blueprint $table) {
            $table->dropColumn(['mpf_salary_credit', 'employee_mpf_share', 'employer_mpf_share']);
        });
    }
};
