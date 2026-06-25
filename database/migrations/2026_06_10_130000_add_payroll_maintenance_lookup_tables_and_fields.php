<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_income_classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('income_class_id')->autoIncrement();
            $table->string('income_class', 45);
        });

        Schema::create('lu_loan_classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('loan_class_id')->autoIncrement();
            $table->string('loan_class', 45);
        });

        Schema::create('tbl_govt_tables', function (Blueprint $table) {
            $table->unsignedTinyInteger('govt_table_id')->autoIncrement();
            $table->string('govt_table_code', 4);
            $table->string('description', 45);
            $table->unsignedTinyInteger('order_by')->default(0);
        });

        Schema::create('tbl_computation_basis', function (Blueprint $table) {
            $table->id('computation_basis_id');
            $table->string('computation_basis_code', 4);
            $table->string('description', 45);
        });

        Schema::create('tbl_leave_apply_to', function (Blueprint $table) {
            $table->id('leave_apply_to_id');
            $table->string('name', 20)->nullable();
        });

        Schema::create('lu_late_undertime_leaves', function (Blueprint $table) {
            $table->unsignedTinyInteger('late_undertime_leave_id')->autoIncrement();
            $table->string('late_undertime_leave_type', 45);
        });

        Schema::table('tbl_income_types', function (Blueprint $table) {
            $table->boolean('breakdown_in_ytd_report')->nullable()->after('is_in_compensation_limit');
            $table->boolean('is_default_basic')->nullable()->after('breakdown_in_ytd_report');
        });

        Schema::table('tbl_loan_types', function (Blueprint $table) {
            $table->boolean('is_viewable')->default(true)->after('sss_loan_type');
        });

        Schema::table('tbl_leave_types', function (Blueprint $table) {
            $table->unsignedBigInteger('leave_apply_to_id')->nullable()->after('computation_basis_id');
            $table->unsignedTinyInteger('late_undertime_leave_id')->nullable()->after('leave_apply_to_id');
            $table->boolean('is_breakdown_in_report')->nullable()->after('is_valid_for_adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_leave_types', function (Blueprint $table) {
            $table->dropColumn(['leave_apply_to_id', 'late_undertime_leave_id', 'is_breakdown_in_report']);
        });

        Schema::table('tbl_loan_types', function (Blueprint $table) {
            $table->dropColumn('is_viewable');
        });

        Schema::table('tbl_income_types', function (Blueprint $table) {
            $table->dropColumn(['breakdown_in_ytd_report', 'is_default_basic']);
        });

        Schema::dropIfExists('lu_late_undertime_leaves');
        Schema::dropIfExists('tbl_leave_apply_to');
        Schema::dropIfExists('tbl_computation_basis');
        Schema::dropIfExists('tbl_govt_tables');
        Schema::dropIfExists('lu_loan_classes');
        Schema::dropIfExists('lu_income_classes');
    }
};
