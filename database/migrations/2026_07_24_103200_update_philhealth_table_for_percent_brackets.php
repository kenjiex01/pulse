<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_govt_table_philhealth', function (Blueprint $table) {
            $table->boolean('is_percent')->default(false)->after('salary_to');
            $table->decimal('percentage', 8, 2)->default(0)->after('is_percent');
            $table->boolean('is_active')->default(true)->after('employer_share');
        });

        Schema::table('tbl_govt_table_philhealth', function (Blueprint $table) {
            $table->dropColumn('contribution_base');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_govt_table_philhealth', function (Blueprint $table) {
            $table->decimal('contribution_base', 10, 2)->nullable()->after('salary_to');
        });

        Schema::table('tbl_govt_table_philhealth', function (Blueprint $table) {
            $table->dropColumn(['is_percent', 'percentage', 'is_active']);
        });
    }
};
