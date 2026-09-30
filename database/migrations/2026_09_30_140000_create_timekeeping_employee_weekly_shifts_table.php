<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_timekeeping_employee_weekly_shifts', function (Blueprint $table) {
            $table->id('timekeeping_employee_weekly_shift_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedTinyInteger('day_id');
            $table->unsignedInteger('shift_code_id');
            $table->timestamps();

            $table->unique(['employee_id', 'day_id'], 'uq_tk_employee_weekly_shifts_employee_day');
            $table->foreign('employee_id', 'fk_tk_employee_weekly_shifts_employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('day_id', 'fk_tk_employee_weekly_shifts_day_id')
                ->references('day_id')
                ->on('lu_days')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('shift_code_id', 'fk_tk_employee_weekly_shifts_shift_code_id')
                ->references('shift_code_id')
                ->on('tbl_shift_codes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        if (! Schema::hasTable('tbl_timekeeping_employee_setup')) {
            return;
        }

        $setups = DB::table('tbl_timekeeping_employee_setup')
            ->whereNotNull('shift_code_id')
            ->get(['employee_id', 'shift_code_id']);

        $now = now();
        foreach ($setups as $setup) {
            for ($dayId = 1; $dayId <= 7; $dayId++) {
                DB::table('tbl_timekeeping_employee_weekly_shifts')->insert([
                    'employee_id' => $setup->employee_id,
                    'day_id' => $dayId,
                    'shift_code_id' => $setup->shift_code_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_timekeeping_employee_weekly_shifts');
    }
};
