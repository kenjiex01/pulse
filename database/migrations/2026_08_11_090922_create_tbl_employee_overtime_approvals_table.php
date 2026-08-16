<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_overtime_approvals', function (Blueprint $table) {
            $table->id('employee_overtime_approval_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->dateTime('ot_start');
            $table->dateTime('ot_end');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_overtime_approvals');
    }
};
