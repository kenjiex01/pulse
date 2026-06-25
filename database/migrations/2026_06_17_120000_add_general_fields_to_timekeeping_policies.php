<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->boolean('enable_employee_validation_for_rest_days')->nullable()->after('enable_attendance_approval');
            $table->unsignedTinyInteger('max_rest_days_per_week')->nullable()->after('enable_employee_validation_for_rest_days');
            $table->decimal('min_hours_rendered_per_week', 10, 4)->unsigned()->nullable()->after('max_rest_days_per_week');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->dropColumn([
                'enable_employee_validation_for_rest_days',
                'max_rest_days_per_week',
                'min_hours_rendered_per_week',
            ]);
        });
    }
};
