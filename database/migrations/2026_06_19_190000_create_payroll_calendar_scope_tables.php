<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_payroll_calendar_colleges', function (Blueprint $table) {
            $table->id('payroll_calendar_college_id');
            $table->unsignedBigInteger('payroll_calendar_id');
            $table->unsignedBigInteger('college_id');

            $table->foreign('payroll_calendar_id')->references('payroll_calendar_id')->on('tbl_payroll_calendar')->cascadeOnDelete();
            $table->foreign('college_id')->references('college_id')->on('tbl_colleges')->cascadeOnDelete();
            $table->unique(['payroll_calendar_id', 'college_id'], 'payroll_calendar_college_unique');
        });

        Schema::create('tbl_payroll_calendar_user_types', function (Blueprint $table) {
            $table->id('payroll_calendar_user_type_id');
            $table->unsignedBigInteger('payroll_calendar_id');
            $table->string('user_type', 20);

            $table->foreign('payroll_calendar_id')->references('payroll_calendar_id')->on('tbl_payroll_calendar')->cascadeOnDelete();
            $table->unique(['payroll_calendar_id', 'user_type'], 'payroll_calendar_user_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_payroll_calendar_user_types');
        Schema::dropIfExists('tbl_payroll_calendar_colleges');
    }
};
