<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_timekeeping_employee_setup', function (Blueprint $table) {
            $table->increments('timekeeping_employee_setup_id');
            $table->unsignedBigInteger('employee_id')->unique('uq_tk_employee_setup_employee_id');
            $table->unsignedInteger('timekeeping_holiday_group_id');
            $table->unsignedInteger('shift_code_id');
            $table->unsignedInteger('timekeeping_policy_id');
            $table->boolean('is_leave')->default(false);
            $table->boolean('is_populate')->default(false);
            $table->unsignedInteger('timekeeping_policy_team_setting_id')->nullable();
            $table->timestamps();

            $table->foreign('employee_id', 'fk_tk_employee_setup_employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('timekeeping_holiday_group_id', 'fk_tk_employee_setup_holiday_group_id')
                ->references('timekeeping_holiday_group_id')
                ->on('tbl_timekeeping_holiday_groups')
                ->restrictOnDelete();
            $table->foreign('shift_code_id', 'fk_tk_employee_setup_shift_code_id')
                ->references('shift_code_id')
                ->on('tbl_shift_codes')
                ->restrictOnDelete();
            $table->foreign('timekeeping_policy_id', 'fk_tk_employee_setup_policy_id')
                ->references('timekeeping_policy_id')
                ->on('tbl_timekeeping_policies')
                ->restrictOnDelete();
            $table->foreign('timekeeping_policy_team_setting_id', 'fk_tk_employee_setup_team_setting_id')
                ->references('timekeeping_policy_team_setting_id')
                ->on('tbl_timekeeping_policy_team_settings')
                ->nullOnDelete();
        });

        Schema::create('tbl_timekeeping_employee_rest_days', function (Blueprint $table) {
            $table->increments('timekeeping_employee_rest_day_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedTinyInteger('day_id');
            $table->boolean('is_paid')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'day_id'], 'uq_tk_employee_rest_days_employee_day');
            $table->foreign('employee_id', 'fk_tk_employee_rest_days_employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('day_id', 'fk_tk_employee_rest_days_day_id')
                ->references('day_id')
                ->on('lu_days')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_timekeeping_employee_rest_days');
        Schema::dropIfExists('tbl_timekeeping_employee_setup');
    }
};
