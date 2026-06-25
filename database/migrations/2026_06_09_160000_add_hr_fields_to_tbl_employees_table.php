<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->string('email')->nullable()->after('last_name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('position')->nullable()->after('phone');
            $table->string('department')->nullable()->after('position');
            $table->string('campus', 50)->nullable()->after('department');
            $table->string('employment_type')->nullable()->after('campus');
            $table->string('employment_status', 20)->default('active')->after('employment_type');
            $table->string('compliance_status', 20)->default('pending')->after('employment_status');
            $table->boolean('is_active')->default(true)->after('compliance_status');
            $table->date('hire_date')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'phone',
                'position',
                'department',
                'campus',
                'employment_type',
                'employment_status',
                'compliance_status',
                'is_active',
                'hire_date',
            ]);
        });
    }
};
