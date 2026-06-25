<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lu_user_request_types', function (Blueprint $table) {
            $table->increments('user_request_type_id');
            $table->string('user_request_type', 45);
            $table->string('filename', 45)->nullable();
            $table->boolean('is_employee')->nullable();
            $table->boolean('is_user')->nullable();
        });

        Schema::create('tbl_approval_steps', function (Blueprint $table) {
            $table->increments('approval_step_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('sys_user_id')->nullable();
            $table->unsignedInteger('form_type')->default(0);
            $table->unsignedInteger('step_no')->default(1);
            $table->boolean('automatic_forwarding')->default(false);
            $table->unsignedInteger('hours_before_forwarding')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'form_type'], 'idx_approval_steps_employee_form_type');
            $table->foreign('employee_id', 'fk_approval_steps_employee_id')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
        });

        Schema::create('tbl_approval_steps_members', function (Blueprint $table) {
            $table->increments('approval_step_member_id');
            $table->unsignedInteger('approval_step_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('allow_batch_approve')->default(false);
            $table->boolean('allow_view_attendance')->default(false);
            $table->timestamps();

            $table->foreign('approval_step_id', 'fk_approval_step_members_step_id')
                ->references('approval_step_id')
                ->on('tbl_approval_steps')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'fk_approval_step_members_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_approval_steps_members');
        Schema::dropIfExists('tbl_approval_steps');
        Schema::dropIfExists('lu_user_request_types');
    }
};
