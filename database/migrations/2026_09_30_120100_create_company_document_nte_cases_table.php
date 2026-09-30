<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_company_document_nte_cases')) {
            return;
        }

        Schema::create('tbl_company_document_nte_cases', function (Blueprint $table) {
            $table->id('company_document_nte_case_id');
            $table->unsignedBigInteger('company_document_send_log_id');
            $table->unsignedBigInteger('company_document_form_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamp('sent_at');
            $table->timestamp('due_at');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('skolaris_request_id')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->json('response_snapshot_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_document_form_id', 'status'], 'co_doc_nte_form_status_idx');
            $table->index(['employee_id', 'status'], 'co_doc_nte_employee_status_idx');
            $table->index('due_at', 'co_doc_nte_due_idx');

            $table->foreign('company_document_send_log_id', 'co_doc_nte_send_log_fk')
                ->references('company_document_send_log_id')
                ->on('tbl_company_document_send_logs')
                ->cascadeOnDelete();
            $table->foreign('company_document_form_id', 'co_doc_nte_form_fk')
                ->references('company_document_form_id')
                ->on('tbl_company_document_forms')
                ->cascadeOnDelete();
            $table->foreign('employee_id', 'co_doc_nte_employee_fk')
                ->references('employee_id')
                ->on('tbl_employees')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_company_document_nte_cases');
    }
};
