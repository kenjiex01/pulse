<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_excess_hours', function (Blueprint $table) {
            $table->unsignedTinyInteger('excess_hour_id')->autoIncrement();
            $table->string('excess_hour', 75);
        });

        Schema::create('lu_rounding', function (Blueprint $table) {
            $table->unsignedTinyInteger('rounding_id')->autoIncrement();
            $table->string('rounding', 45);
        });

        Schema::create('lu_non_regular_ot', function (Blueprint $table) {
            $table->unsignedTinyInteger('non_regular_ot_id')->primary();
            $table->string('description', 250);
        });

        Schema::create('tbl_leave_processing_modes', function (Blueprint $table) {
            $table->unsignedInteger('leave_processing_mode_id')->autoIncrement();
            $table->string('mode_label', 20)->nullable();
        });

        Schema::create('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('is_allow_flexi_time')->nullable();
            $table->decimal('max_flexi_time', 7, 4)->unsigned()->nullable();
            $table->decimal('grace_period', 7, 4)->unsigned()->nullable();
            $table->boolean('is_deduct_grace_period')->nullable();
            $table->unsignedInteger('tardiness_leave_type_id')->nullable();
            $table->unsignedInteger('undertime_leave_type_id')->nullable();
            $table->unsignedTinyInteger('tardiness_rounding_id')->nullable();
            $table->unsignedTinyInteger('undertime_rounding_id')->nullable();
            $table->unsignedTinyInteger('is_ot_form_required')->nullable();
            $table->boolean('is_consider_after_time')->nullable();
            $table->boolean('is_consider_before_time')->nullable();
            $table->unsignedTinyInteger('excess_hour_id')->nullable();
            $table->decimal('min_minutes', 7, 4)->unsigned()->nullable();
            $table->unsignedTinyInteger('overtime_rounding_id')->nullable();
            $table->boolean('is_offset_undertime')->nullable();
            $table->boolean('is_offset_lwop')->nullable();
            $table->string('special_ot_start', 5)->nullable();
            $table->decimal('special_ot_min_minutes', 7, 4)->unsigned()->nullable();
            $table->unsignedTinyInteger('break_computation')->nullable();
            $table->boolean('break_deduct_tardiness')->nullable();
            $table->decimal('break_grace_period', 7, 4)->unsigned()->nullable();
            $table->boolean('is_break_deduct_grace_period')->nullable();
            $table->unsignedInteger('break_tardiness_leave_type_id')->nullable();
            $table->unsignedTinyInteger('break_tardiness_rounding_id')->nullable();
            $table->unsignedInteger('awol_leave_type_id')->nullable();
            $table->boolean('nd_deduct_break')->nullable();
            $table->unsignedInteger('leave_processing_mode')->default(1);
            $table->integer('validity_of_late_file')->default(30)->nullable();
            $table->boolean('hide_negative_leaves')->nullable();
            $table->boolean('enable_attendance_approval')->nullable();
            $table->unsignedInteger('non_regular_hours_computation_basis')->nullable();
            $table->boolean('enable_notification')->nullable();
            $table->text('notif_for_process')->nullable();
            $table->boolean('is_fix_break')->nullable();
            $table->decimal('buffer_time_in', 10, 2)->nullable();
            $table->decimal('buffer_time_out', 10, 2)->nullable();

            $table->foreign('excess_hour_id', 'FK_tbl_timekeeping_policies_excess_hour_id')
                ->references('excess_hour_id')
                ->on('lu_excess_hours')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('tbl_timekeeping_policy_tardiness', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_tardiness_id')->autoIncrement();
            $table->decimal('time_from', 7, 4);
            $table->decimal('time_to', 7, 4);
            $table->decimal('equivalent', 7, 4);
        });

        Schema::create('tbl_timekeeping_policy_undertime', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_undertime_id')->autoIncrement();
            $table->decimal('time_from', 7, 4);
            $table->decimal('time_to', 7, 4);
            $table->decimal('equivalent', 7, 4);
        });

        Schema::create('tbl_timekeeping_policy_overtime', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_overtime_id')->autoIncrement();
            $table->decimal('time_from', 7, 4);
            $table->decimal('time_to', 7, 4);
            $table->decimal('equivalent', 7, 4);
        });

        Schema::create('tbl_timekeeping_policy_breaks', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_break_id')->autoIncrement();
            $table->decimal('time_from', 7, 4);
            $table->decimal('time_to', 7, 4);
            $table->decimal('equivalent', 7, 4);
        });

        Schema::create('tbl_timekeeping_policy_nd', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_nd_id')->autoIncrement();
            $table->decimal('time_from', 7, 4);
            $table->decimal('time_to', 7, 4);
            $table->decimal('equivalent', 7, 4);
        });

        Schema::create('tbl_timekeeping_policy_leave', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_leave_id')->autoIncrement();
            $table->unsignedInteger('leave_type_id');
            $table->decimal('time_from', 7, 4);
            $table->decimal('time_to', 7, 4);
            $table->decimal('equivalent', 7, 4);
        });

        Schema::create('tbl_timekeeping_policy_day_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->char('sunday', 1);
            $table->char('monday', 1);
            $table->char('tuesday', 1);
            $table->char('wednesday', 1);
            $table->char('thursday', 1);
            $table->char('friday', 1);
            $table->char('saturday', 1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_timekeeping_policy_day_codes');
        Schema::dropIfExists('tbl_timekeeping_policy_leave');
        Schema::dropIfExists('tbl_timekeeping_policy_nd');
        Schema::dropIfExists('tbl_timekeeping_policy_breaks');
        Schema::dropIfExists('tbl_timekeeping_policy_overtime');
        Schema::dropIfExists('tbl_timekeeping_policy_undertime');
        Schema::dropIfExists('tbl_timekeeping_policy_tardiness');
        Schema::dropIfExists('tbl_timekeeping_policies');
        Schema::dropIfExists('tbl_leave_processing_modes');
        Schema::dropIfExists('lu_non_regular_ot');
        Schema::dropIfExists('lu_rounding');
        Schema::dropIfExists('lu_excess_hours');
    }
};
