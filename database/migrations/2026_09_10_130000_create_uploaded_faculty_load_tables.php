<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_faculty_load_uploads')) {
            Schema::create('pulse_faculty_load_uploads', function (Blueprint $table) {
                $table->id('upload_id');
                $table->string('faculty_name')->nullable()->index();
                $table->string('employee_number', 50)->nullable();
                $table->string('faculty_email')->nullable();
                $table->string('load_type', 20)->nullable()->index();
                $table->string('department')->nullable();
                $table->string('campus_name')->nullable()->index();
                $table->string('term_label')->nullable()->index();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('period_range')->nullable();
                $table->string('employment_type', 30)->nullable();
                $table->string('appointment_basis', 30)->nullable();
                $table->decimal('total_hours_week', 8, 2)->nullable();
                $table->unsignedInteger('total_units')->nullable();
                $table->unsignedInteger('total_hours')->nullable();
                $table->string('original_filename');
                $table->string('stored_path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedSmallInteger('page_count')->nullable();
                $table->string('parse_status', 20)->default('pending')->index();
                $table->text('parse_message')->nullable();
                $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('pulse_faculty_load_items')) {
            Schema::create('pulse_faculty_load_items', function (Blueprint $table) {
                $table->id('item_id');
                $table->unsignedBigInteger('upload_id')->index();
                $table->unsignedSmallInteger('row_number')->nullable();
                $table->string('row_type', 20)->default('subject')->index();
                $table->string('subject_code', 50)->nullable();
                $table->string('title')->nullable();
                $table->text('class_schedule')->nullable();
                $table->string('day', 30)->nullable();
                $table->unsignedTinyInteger('units')->nullable();
                $table->unsignedSmallInteger('hours')->nullable();
                $table->unsignedSmallInteger('hours_paid')->nullable();
                $table->string('room', 80)->nullable();
                $table->string('section', 40)->nullable();
                $table->string('synchronous_schedule')->nullable();
                $table->unsignedSmallInteger('stud_count')->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('period_range', 80)->nullable();
                $table->string('schedule_note', 40)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('upload_id')->references('upload_id')->on('pulse_faculty_load_uploads')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_faculty_load_items');
        Schema::dropIfExists('pulse_faculty_load_uploads');
    }
};
