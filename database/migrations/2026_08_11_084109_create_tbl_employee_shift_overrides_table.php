<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_shift_overrides', function (Blueprint $table) {
            $table->id('employee_shift_override_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->unsignedInteger('shift_code_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('shift_code_id')
                ->references('shift_code_id')
                ->on('tbl_shift_codes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_shift_overrides');
    }
};
