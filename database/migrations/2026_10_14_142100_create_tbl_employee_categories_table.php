<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_categories', function (Blueprint $table) {
            $table->id('employee_category_id');
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('payroll_basis', 32);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_protected')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();

        DB::table('tbl_employee_categories')->insert([
            [
                'code' => 'faculty',
                'name' => 'Faculty',
                'payroll_basis' => 'teaching_loads',
                'sort_order' => 1,
                'is_protected' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'staff',
                'name' => 'Staff',
                'payroll_basis' => 'logs',
                'sort_order' => 2,
                'is_protected' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'admin',
                'name' => 'Admin',
                'payroll_basis' => 'logs',
                'sort_order' => 3,
                'is_protected' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_categories');
    }
};
