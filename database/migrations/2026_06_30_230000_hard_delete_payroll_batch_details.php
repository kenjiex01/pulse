<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $deletedDetailIds = DB::table('trn_payroll_batch_details')
            ->whereNotNull('deleted_at')
            ->pluck('payroll_batch_detail_id');

        if ($deletedDetailIds->isNotEmpty()) {
            DB::table('trn_payroll_incomes')
                ->whereIn('payroll_batch_detail_id', $deletedDetailIds)
                ->delete();

            DB::table('trn_payroll_deductions')
                ->whereIn('payroll_batch_detail_id', $deletedDetailIds)
                ->delete();

            DB::table('trn_payroll_batch_details')
                ->whereIn('payroll_batch_detail_id', $deletedDetailIds)
                ->delete();
        }

        Schema::table('trn_payroll_batch_details', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('trn_payroll_batch_details', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
