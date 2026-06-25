<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_employment_information', function (Blueprint $table) {
            $table->id('employment_info_id');
            $table->foreignId('employee_id')->constrained('tbl_employees', 'employee_id')->cascadeOnDelete();
            $table->string('user_type', 20);
            $table->string('position', 150)->nullable();
            $table->string('designation', 150)->nullable();
            $table->string('rank', 150)->nullable();
            $table->string('employment_type', 100)->nullable();
            $table->date('hire_date')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['employee_id', 'sort_order']);
        });

        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->boolean('is_hybrid')->default(false)->after('suffix');
        });

        if (Schema::hasColumn('tbl_employees', 'user_type')) {
            $employees = DB::table('tbl_employees')->get(['employee_id', 'user_type', 'position', 'designation', 'rank', 'employment_type', 'hire_date']);

            foreach ($employees as $employee) {
                if (blank($employee->user_type) && blank($employee->position) && blank($employee->employment_type)) {
                    continue;
                }

                DB::table('tbl_employee_employment_information')->insert([
                    'employee_id' => $employee->employee_id,
                    'user_type' => $employee->user_type ?: 'staff',
                    'position' => $employee->position,
                    'designation' => $employee->designation,
                    'rank' => $employee->rank,
                    'employment_type' => $employee->employment_type,
                    'hire_date' => $employee->hire_date,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('tbl_employees', function (Blueprint $table) {
                $table->dropColumn([
                    'user_type',
                    'position',
                    'designation',
                    'rank',
                    'employment_type',
                    'hire_date',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->string('user_type', 20)->nullable()->after('suffix');
            $table->string('position')->nullable()->after('phone');
            $table->string('designation')->nullable()->after('position');
            $table->string('rank')->nullable()->after('designation');
            $table->string('employment_type')->nullable()->after('campus');
            $table->date('hire_date')->nullable()->after('is_active');
        });

        $records = DB::table('tbl_employee_employment_information')
            ->orderBy('employee_id')
            ->orderBy('sort_order')
            ->get();

        foreach ($records->groupBy('employee_id') as $employeeId => $group) {
            $primary = $group->first();

            DB::table('tbl_employees')
                ->where('employee_id', $employeeId)
                ->update([
                    'user_type' => $primary->user_type,
                    'position' => $primary->position,
                    'designation' => $primary->designation,
                    'rank' => $primary->rank,
                    'employment_type' => $primary->employment_type,
                    'hire_date' => $primary->hire_date,
                ]);
        }

        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->dropColumn('is_hybrid');
        });

        Schema::dropIfExists('tbl_employee_employment_information');
    }
};
