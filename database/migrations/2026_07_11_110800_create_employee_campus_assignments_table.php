<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_campus_assignments', function (Blueprint $table) {
            $table->id('employee_campus_assignment_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('campus_id');
            $table->string('biometric_id', 50)->nullable();
            $table->string('college', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('program', 150)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'campus_id'], 'uq_employee_campus_assignments_employee_campus');
            $table->foreign('employee_id', 'fk_employee_campus_assignments_employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('campus_id', 'fk_employee_campus_assignments_campus_id')
                ->references('campus_id')
                ->on('tbl_campuses')
                ->restrictOnDelete();
        });

        DB::table('tbl_employees')
            ->whereNull('deleted_at')
            ->whereNotNull('campus_id')
            ->orderBy('employee_id')
            ->get(['employee_id', 'campus_id', 'college', 'department', 'program'])
            ->each(function ($employee): void {
                DB::table('tbl_employee_campus_assignments')->insert([
                    'employee_id' => $employee->employee_id,
                    'campus_id' => $employee->campus_id,
                    'biometric_id' => null,
                    'college' => $employee->college,
                    'department' => $employee->department,
                    'program' => $employee->program,
                    'is_primary' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_campus_assignments');
    }
};
