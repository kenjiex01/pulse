<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_timekeeping_employee_consultation_hours', function (Blueprint $table) {
            $table->id('timekeeping_employee_consultation_hour_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedTinyInteger('day_id');
            $table->string('time_start', 8);
            $table->string('time_end', 8);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'day_id'], 'idx_tk_consultation_hours_employee_day');
            $table->foreign('employee_id', 'fk_tk_consultation_hours_employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('day_id', 'fk_tk_consultation_hours_day_id')
                ->references('day_id')
                ->on('lu_days')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_timekeeping_employee_consultation_hours');
    }
};
