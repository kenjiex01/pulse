<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_load_sync_status', function (Blueprint $table) {
            $table->id('teaching_load_sync_status_id');
            $table->unsignedBigInteger('employee_id')->unique();
            $table->timestamp('last_pulled_at')->nullable();
            $table->date('last_date_from')->nullable();
            $table->date('last_date_to')->nullable();
            $table->unsignedInteger('last_records_count')->default(0);
            $table->unsignedBigInteger('last_pulled_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->cascadeOnDelete();
            $table->foreign('last_pulled_by_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('teaching_load_sessions', function (Blueprint $table) {
            $table->id('teaching_load_session_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('session_date');
            $table->string('employee_number')->nullable();
            $table->unsignedBigInteger('skolaris_offering_id')->nullable();
            $table->string('subject_code')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('section')->nullable();
            $table->string('campus_name')->nullable();
            $table->string('room')->nullable();
            $table->string('schedule_day')->nullable();
            $table->string('class_schedule')->nullable();
            $table->string('time_in')->nullable();
            $table->string('time_out')->nullable();
            $table->decimal('total_hours', 8, 2)->nullable();
            $table->decimal('total_render_hours', 8, 2)->nullable();
            $table->string('status_code')->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->timestamp('pulled_at')->nullable();
            $table->unsignedBigInteger('pulled_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->cascadeOnDelete();
            $table->foreign('pulled_by_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_load_sessions');
        Schema::dropIfExists('teaching_load_sync_status');
    }
};
