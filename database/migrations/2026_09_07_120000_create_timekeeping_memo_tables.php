<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_timekeeping_memo_setups', function (Blueprint $table) {
            $table->id('timekeeping_memo_setup_id');
            $table->string('violation_type', 20)->unique();
            $table->unsignedBigInteger('company_document_form_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_document_form_id', 'tk_memo_setup_form_fk')
                ->references('company_document_form_id')
                ->on('tbl_company_document_forms')
                ->nullOnDelete();
        });

        Schema::create('tbl_timekeeping_memo_send_logs', function (Blueprint $table) {
            $table->id('timekeeping_memo_send_log_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('violation_type', 20);
            $table->date('work_date');
            $table->unsignedBigInteger('company_document_form_id');
            $table->unsignedBigInteger('submission_id')->nullable();
            $table->unsignedBigInteger('sent_by_user_id')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['employee_id', 'violation_type', 'work_date'], 'tk_memo_send_unique');
            $table->foreign('employee_id', 'tk_memo_send_employee_fk')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('company_document_form_id', 'tk_memo_send_form_fk')
                ->references('company_document_form_id')
                ->on('tbl_company_document_forms')
                ->cascadeOnDelete();
            $table->foreign('submission_id', 'tk_memo_send_submission_fk')
                ->references('submission_id')
                ->on('tbl_company_document_submissions')
                ->nullOnDelete();
            $table->foreign('sent_by_user_id', 'tk_memo_send_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        $now = now();
        foreach (['late', 'undertime', 'absent'] as $type) {
            DB::table('tbl_timekeeping_memo_setups')->insert([
                'violation_type' => $type,
                'company_document_form_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_timekeeping_memo_send_logs');
        Schema::dropIfExists('tbl_timekeeping_memo_setups');
    }
};
