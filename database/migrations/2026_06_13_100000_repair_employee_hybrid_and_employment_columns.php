<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_employees', 'is_hybrid')) {
            Schema::table('tbl_employees', function (Blueprint $table) {
                $table->boolean('is_hybrid')->default(false)->after('suffix');
            });
        }

        if (! Schema::hasTable('tbl_employee_employment_information')) {
            return;
        }

        if (! Schema::hasColumn('tbl_employees', 'user_type')) {
            return;
        }

        $employees = DB::table('tbl_employees')->get([
            'employee_id',
            'user_type',
            'position',
            'designation',
            'rank',
            'employment_type',
            'hire_date',
        ]);

        foreach ($employees as $employee) {
            if (blank($employee->user_type) && blank($employee->position) && blank($employee->employment_type)) {
                continue;
            }

            $exists = DB::table('tbl_employee_employment_information')
                ->where('employee_id', $employee->employee_id)
                ->exists();

            if ($exists) {
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
            $columns = array_filter([
                Schema::hasColumn('tbl_employees', 'user_type') ? 'user_type' : null,
                Schema::hasColumn('tbl_employees', 'position') ? 'position' : null,
                Schema::hasColumn('tbl_employees', 'designation') ? 'designation' : null,
                Schema::hasColumn('tbl_employees', 'rank') ? 'rank' : null,
                Schema::hasColumn('tbl_employees', 'employment_type') ? 'employment_type' : null,
                Schema::hasColumn('tbl_employees', 'hire_date') ? 'hire_date' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn(array_values($columns));
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tbl_employees', 'user_type')) {
            Schema::table('tbl_employees', function (Blueprint $table) {
                $table->string('user_type', 20)->nullable()->after('suffix');
                $table->string('position')->nullable()->after('phone');
                $table->string('designation')->nullable()->after('position');
                $table->string('rank')->nullable()->after('designation');
                $table->string('employment_type')->nullable()->after('campus');
                $table->date('hire_date')->nullable()->after('is_active');
            });
        }

        if (Schema::hasColumn('tbl_employees', 'is_hybrid')) {
            Schema::table('tbl_employees', function (Blueprint $table) {
                $table->dropColumn('is_hybrid');
            });
        }
    }
};
