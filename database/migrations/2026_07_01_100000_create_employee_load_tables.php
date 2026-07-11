<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_employee_load_transactions', function (Blueprint $table) {
            $table->id('employee_load_transaction_id');
            $table->unsignedInteger('batch_no')->default(0);
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('enrollment_period_id')->nullable();
            $table->string('enrollment_period_label')->nullable();
            $table->date('dt_from')->nullable();
            $table->date('dt_to')->nullable();
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->timestamp('dt_uploaded')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('uploaded_by_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('raw_employee_load_entries', function (Blueprint $table) {
            $table->id('employee_load_entry_id');
            $table->unsignedBigInteger('employee_load_transaction_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('skolaris_offering_id')->nullable();
            $table->string('employee_number')->nullable();
            $table->string('faculty_name')->nullable();
            $table->string('college')->nullable();
            $table->string('modality')->nullable();
            $table->string('subject')->nullable();
            $table->string('section')->nullable();
            $table->string('load_date')->nullable();
            $table->date('session_date')->nullable();
            $table->string('class_schedule')->nullable();
            $table->string('time_in')->nullable();
            $table->string('time_out')->nullable();
            $table->string('remarks')->nullable();
            $table->string('comments')->nullable();
            $table->string('verification_remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_load_transaction_id', 'employee_load_entries_transaction_fk')
                ->references('employee_load_transaction_id')
                ->on('raw_employee_load_transactions')
                ->cascadeOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('tbl_employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_employee_load_entries');
        Schema::dropIfExists('raw_employee_load_transactions');
    }
};
