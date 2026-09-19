<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_company_document_send_logs')) {
            return;
        }

        Schema::create('tbl_company_document_send_logs', function (Blueprint $table) {
            $table->id('company_document_send_log_id');
            $table->unsignedBigInteger('company_document_form_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('submission_id')->nullable();
            $table->unsignedBigInteger('sent_by_user_id')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['company_document_form_id', 'sent_at'], 'co_doc_send_form_sent_idx');
            $table->index('employee_id', 'co_doc_send_employee_idx');

            $table->foreign('company_document_form_id', 'co_doc_send_form_fk')
                ->references('company_document_form_id')
                ->on('tbl_company_document_forms')
                ->cascadeOnDelete();
            $table->foreign('employee_id', 'co_doc_send_employee_fk')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
            $table->foreign('submission_id', 'co_doc_send_submission_fk')
                ->references('submission_id')
                ->on('tbl_company_document_submissions')
                ->nullOnDelete();
            $table->foreign('sent_by_user_id', 'co_doc_send_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_company_document_send_logs');
    }
};
