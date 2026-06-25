<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $equivalentTables = [
        'tbl_timekeeping_policy_tardiness',
        'tbl_timekeeping_policy_undertime',
        'tbl_timekeeping_policy_overtime',
        'tbl_timekeeping_policy_breaks',
        'tbl_timekeeping_policy_nd',
        'tbl_timekeeping_policy_leave',
    ];

    public function up(): void
    {
        $this->rebuildPoliciesTable();
        $this->addPolicyForeignKeys();
        $this->rebuildDayCodesTable();
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_policy_day_codes', function (Blueprint $table) {
            $table->dropUnique(['timekeeping_policy_id']);
            $table->dropForeign(['timekeeping_policy_id']);
            $table->dropColumn('timekeeping_policy_id');
        });

        foreach ($this->equivalentTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['timekeeping_policy_id']);
                $table->dropColumn('timekeeping_policy_id');
            });
        }
    }

    private function rebuildPoliciesTable(): void
    {
        Schema::create('tbl_timekeeping_policies_new', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_id')->autoIncrement();
            $table->string('policy_code', 30);
            $table->string('policy_name', 100);
            $table->string('description', 250)->nullable();
            $table->boolean('is_active')->default(true);
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
            $table->timestamps();

            $table->unique('policy_code');
            $table->foreign('excess_hour_id', 'FK_tbl_timekeeping_policies_new_excess_hour_id')
                ->references('excess_hour_id')
                ->on('lu_excess_hours')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        if (Schema::hasTable('tbl_timekeeping_policies')) {
            $existing = DB::table('tbl_timekeeping_policies')->orderBy('id')->get();

            foreach ($existing as $index => $row) {
                $rowArray = (array) $row;
                unset($rowArray['id']);

                DB::table('tbl_timekeeping_policies_new')->insert(array_merge($rowArray, [
                    'policy_code' => $index === 0 ? 'DEFAULT' : 'POLICY_'.$row->id,
                    'policy_name' => $index === 0 ? 'Default Policy' : 'Policy '.$row->id,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        Schema::dropIfExists('tbl_timekeeping_policies');
        Schema::rename('tbl_timekeeping_policies_new', 'tbl_timekeeping_policies');
    }

    private function addPolicyForeignKeys(): void
    {
        foreach ($this->equivalentTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('timekeeping_policy_id')->default(1);
            });

            DB::table($tableName)->update(['timekeeping_policy_id' => 1]);

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreign('timekeeping_policy_id', 'FK_'.$tableName.'_policy_id')
                    ->references('timekeeping_policy_id')
                    ->on('tbl_timekeeping_policies')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            });
        }
    }

    private function rebuildDayCodesTable(): void
    {
        $existingDayCodes = Schema::hasTable('tbl_timekeeping_policy_day_codes')
            ? DB::table('tbl_timekeeping_policy_day_codes')->first()
            : null;

        Schema::dropIfExists('tbl_timekeeping_policy_day_codes');

        Schema::create('tbl_timekeeping_policy_day_codes', function (Blueprint $table) {
            $table->unsignedInteger('timekeeping_policy_day_code_id')->autoIncrement();
            $table->unsignedInteger('timekeeping_policy_id');
            $table->char('sunday', 1);
            $table->char('monday', 1);
            $table->char('tuesday', 1);
            $table->char('wednesday', 1);
            $table->char('thursday', 1);
            $table->char('friday', 1);
            $table->char('saturday', 1);

            $table->unique('timekeeping_policy_id');
            $table->foreign('timekeeping_policy_id', 'FK_tbl_timekeeping_policy_day_codes_policy_id')
                ->references('timekeeping_policy_id')
                ->on('tbl_timekeeping_policies')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        $defaults = [
            'sunday' => 'U',
            'monday' => 'M',
            'tuesday' => 'T',
            'wednesday' => 'W',
            'thursday' => 'H',
            'friday' => 'F',
            'saturday' => 'A',
        ];

        $policyIds = DB::table('tbl_timekeeping_policies')->pluck('timekeeping_policy_id');

        foreach ($policyIds as $policyId) {
            DB::table('tbl_timekeeping_policy_day_codes')->insert([
                'timekeeping_policy_id' => $policyId,
                'sunday' => $existingDayCodes->sunday ?? $defaults['sunday'],
                'monday' => $existingDayCodes->monday ?? $defaults['monday'],
                'tuesday' => $existingDayCodes->tuesday ?? $defaults['tuesday'],
                'wednesday' => $existingDayCodes->wednesday ?? $defaults['wednesday'],
                'thursday' => $existingDayCodes->thursday ?? $defaults['thursday'],
                'friday' => $existingDayCodes->friday ?? $defaults['friday'],
                'saturday' => $existingDayCodes->saturday ?? $defaults['saturday'],
            ]);
        }
    }
};
