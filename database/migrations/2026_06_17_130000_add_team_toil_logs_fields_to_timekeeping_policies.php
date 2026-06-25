<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_timekeeping_policy_team_settings', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_team_setting_id')->autoIncrement();
            $table->unsignedInteger('limit');
            $table->string('description', 250);
        });

        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_team_setting_id')->nullable()->after('buffer_time_out');
            $table->boolean('enable_toil')->nullable()->after('timekeeping_policy_team_setting_id');
            $table->unsignedInteger('exp_days')->nullable()->after('enable_toil');
            $table->decimal('min_toil_hours', 7, 4)->unsigned()->nullable()->after('exp_days');
            $table->decimal('max_toil_hours', 7, 4)->unsigned()->nullable()->after('min_toil_hours');
            $table->boolean('enable_logs_tagging')->nullable()->after('max_toil_hours');
            $table->char('raw_logs_tag', 1)->nullable()->after('enable_logs_tagging');
            $table->char('edited_logs_tag', 1)->nullable();
            $table->char('filed_logs_tag', 1)->nullable();
            $table->char('auto_logs_tag', 1)->nullable();
            $table->string('raw_logs_desc', 45)->nullable();
            $table->string('edited_logs_desc', 45)->nullable();
            $table->string('filed_logs_desc', 45)->nullable();
            $table->string('auto_logs_desc', 45)->nullable();
            $table->char('default_shift_tag', 1)->nullable();
            $table->char('planned_shift_tag', 1)->nullable();
            $table->char('filed_shift_tag', 1)->nullable();
            $table->char('edited_shift_tag', 1)->nullable();
            $table->string('default_shift_desc', 45)->nullable();
            $table->string('planned_shift_desc', 45)->nullable();
            $table->string('filed_shift_desc', 45)->nullable();
            $table->string('edited_shift_desc', 45)->nullable();

            $table->foreign('timekeeping_policy_team_setting_id', 'FK_tbl_timekeeping_policies_team_setting_id')
                ->references('timekeeping_policy_team_setting_id')
                ->on('tbl_timekeeping_policy_team_settings')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->dropForeign('FK_tbl_timekeeping_policies_team_setting_id');
            $table->dropColumn([
                'timekeeping_policy_team_setting_id',
                'enable_toil',
                'exp_days',
                'min_toil_hours',
                'max_toil_hours',
                'enable_logs_tagging',
                'raw_logs_tag',
                'edited_logs_tag',
                'filed_logs_tag',
                'auto_logs_tag',
                'raw_logs_desc',
                'edited_logs_desc',
                'filed_logs_desc',
                'auto_logs_desc',
                'default_shift_tag',
                'planned_shift_tag',
                'filed_shift_tag',
                'edited_shift_tag',
                'default_shift_desc',
                'planned_shift_desc',
                'filed_shift_desc',
                'edited_shift_desc',
            ]);
        });

        Schema::dropIfExists('tbl_timekeeping_policy_team_settings');
    }
};
