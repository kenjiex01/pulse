<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_govt_table_philhealth_minimum', function (Blueprint $table) {
            $table->decimal('employee_amount', 10, 2)->nullable()->after('minimum_amount');
            $table->decimal('employer_amount', 10, 2)->nullable()->after('employee_amount');
        });

        DB::table('tbl_govt_table_philhealth_minimum')
            ->whereNull('deleted_at')
            ->orderBy('govt_table_philhealth_minimum_id')
            ->get()
            ->each(function ($row): void {
                $minimumTotal = (float) ($row->minimum_amount ?? 0);
                $share = $minimumTotal > 0 ? round($minimumTotal / 2, 2) : 0.0;

                DB::table('tbl_govt_table_philhealth_minimum')
                    ->where('govt_table_philhealth_minimum_id', $row->govt_table_philhealth_minimum_id)
                    ->update([
                        'employee_amount' => $share,
                        'employer_amount' => $share,
                    ]);
            });

        Schema::table('tbl_govt_table_philhealth_minimum', function (Blueprint $table) {
            $table->decimal('employee_amount', 10, 2)->nullable(false)->change();
            $table->decimal('employer_amount', 10, 2)->nullable(false)->change();
            $table->dropColumn('minimum_amount');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_govt_table_philhealth_minimum', function (Blueprint $table) {
            $table->decimal('minimum_amount', 10, 2)->nullable()->after('govt_table_philhealth_minimum_id');
        });

        DB::table('tbl_govt_table_philhealth_minimum')
            ->whereNull('deleted_at')
            ->orderBy('govt_table_philhealth_minimum_id')
            ->get()
            ->each(function ($row): void {
                DB::table('tbl_govt_table_philhealth_minimum')
                    ->where('govt_table_philhealth_minimum_id', $row->govt_table_philhealth_minimum_id)
                    ->update([
                        'minimum_amount' => round((float) $row->employee_amount + (float) $row->employer_amount, 2),
                    ]);
            });

        Schema::table('tbl_govt_table_philhealth_minimum', function (Blueprint $table) {
            $table->decimal('minimum_amount', 10, 2)->nullable(false)->change();
            $table->dropColumn(['employee_amount', 'employer_amount']);
        });
    }
};
