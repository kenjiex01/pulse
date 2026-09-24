<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employee_employment_information', function (Blueprint $table) {
            $table->date('date_effective_from')->nullable()->after('hire_date');
            $table->date('date_effective_to')->nullable()->after('date_effective_from');
            $table->date('last_payroll_date')->nullable()->after('date_effective_to');
            $table->date('separation_date')->nullable()->after('last_payroll_date');
            $table->unsignedBigInteger('lineage_employment_info_id')->nullable()->after('separation_date');
            $table->foreign('lineage_employment_info_id', 'emp_info_lineage_fk')
                ->references('employment_info_id')
                ->on('tbl_employee_employment_information')
                ->nullOnDelete();
            $table->index(['lineage_employment_info_id'], 'emp_info_lineage_idx');
        });

        $driver = Schema::getConnection()->getDriverName();
        $fromExpression = $driver === 'sqlite'
            ? "COALESCE(hire_date, date(created_at))"
            : 'COALESCE(hire_date, DATE(created_at))';

        DB::table('tbl_employee_employment_information')
            ->whereNull('date_effective_from')
            ->whereNull('lineage_employment_info_id')
            ->update([
                'date_effective_from' => DB::raw($fromExpression),
            ]);
    }

    public function down(): void
    {
        Schema::table('tbl_employee_employment_information', function (Blueprint $table) {
            $table->dropForeign('emp_info_lineage_fk');
            $table->dropIndex('emp_info_lineage_idx');
            $table->dropColumn([
                'date_effective_from',
                'date_effective_to',
                'last_payroll_date',
                'separation_date',
                'lineage_employment_info_id',
            ]);
        });
    }
};
