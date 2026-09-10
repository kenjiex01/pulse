<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_company_document_forms', function (Blueprint $table) {
            $table->id('company_document_form_id');
            $table->string('code', 80);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('document_type', 32)->default('memo');
            $table->boolean('allow_multiple_submissions')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('submit_label', 80)->default('Submit');
            $table->text('success_message')->nullable();
            $table->json('settings_json')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code', 'deleted_at'], 'co_doc_form_code_unique');
            $table->index(['document_type', 'is_active'], 'co_doc_form_type_active_idx');

            $table->foreign('created_by', 'co_doc_form_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('tbl_company_document_elements', function (Blueprint $table) {
            $table->id('element_id');
            $table->unsignedBigInteger('company_document_form_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('type', 40);
            $table->text('label')->nullable();
            $table->string('field_key', 120)->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('options_json')->nullable();
            $table->json('validation_json')->nullable();
            $table->json('conditional_json')->nullable();
            $table->json('settings_json')->nullable();
            $table->string('width', 12)->default('full');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_document_form_id', 'sort_order'], 'co_doc_elem_form_sort_idx');

            $table->foreign('company_document_form_id', 'co_doc_elem_form_fk')
                ->references('company_document_form_id')
                ->on('tbl_company_document_forms')
                ->cascadeOnDelete();

            $table->foreign('parent_id', 'co_doc_elem_parent_fk')
                ->references('element_id')
                ->on('tbl_company_document_elements')
                ->nullOnDelete();
        });

        Schema::create('tbl_company_document_approvals', function (Blueprint $table) {
            $table->id('approval_id');
            $table->unsignedBigInteger('company_document_form_id');
            $table->unsignedInteger('step_number');
            $table->string('name', 150);
            $table->string('mode', 20)->default('single');
            $table->boolean('optional')->default(false);
            $table->unsignedInteger('sla_hours')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_document_form_id', 'step_number', 'deleted_at'], 'co_doc_appr_step_unique');

            $table->foreign('company_document_form_id', 'co_doc_appr_form_fk')
                ->references('company_document_form_id')
                ->on('tbl_company_document_forms')
                ->cascadeOnDelete();
        });

        Schema::create('tbl_company_document_approval_assignees', function (Blueprint $table) {
            $table->id('assignee_id');
            $table->unsignedBigInteger('approval_id');
            $table->string('assignee_type', 20);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id', 'co_doc_asgn_user_idx');
            $table->index('role_id', 'co_doc_asgn_role_idx');

            $table->foreign('approval_id', 'co_doc_asgn_appr_fk')
                ->references('approval_id')
                ->on('tbl_company_document_approvals')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'co_doc_asgn_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('role_id', 'co_doc_asgn_role_fk')
                ->references('id')
                ->on('roles')
                ->nullOnDelete();
        });

        Schema::create('tbl_company_document_submissions', function (Blueprint $table) {
            $table->id('submission_id');
            $table->unsignedBigInteger('company_document_form_id');
            $table->unsignedInteger('form_version')->default(1);
            $table->json('form_snapshot_json')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->string('status', 24)->default('submitted');
            $table->unsignedInteger('current_step')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'co_doc_sub_status_idx');
            $table->index('submitted_by_user_id', 'co_doc_sub_user_idx');

            $table->foreign('company_document_form_id', 'co_doc_sub_form_fk')
                ->references('company_document_form_id')
                ->on('tbl_company_document_forms')
                ->cascadeOnDelete();

            $table->foreign('submitted_by_user_id', 'co_doc_sub_submitter_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('tbl_company_document_submission_values', function (Blueprint $table) {
            $table->id('value_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('element_id')->nullable();
            $table->string('field_key', 120);
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['submission_id', 'field_key'], 'co_doc_sub_val_field_idx');

            $table->foreign('submission_id', 'co_doc_sub_val_sub_fk')
                ->references('submission_id')
                ->on('tbl_company_document_submissions')
                ->cascadeOnDelete();

            $table->foreign('element_id', 'co_doc_sub_val_elem_fk')
                ->references('element_id')
                ->on('tbl_company_document_elements')
                ->nullOnDelete();
        });

        Schema::create('tbl_company_document_submission_approvals', function (Blueprint $table) {
            $table->id('submission_approval_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->unsignedInteger('step_number');
            $table->string('step_name', 150);
            $table->string('mode', 20);
            $table->boolean('optional')->default(false);
            $table->unsignedBigInteger('assignee_user_id');
            $table->string('status', 16)->default('pending');
            $table->text('comment')->nullable();
            $table->string('signature_path', 500)->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['submission_id', 'step_number'], 'co_doc_sub_appr_step_idx');
            $table->index(['assignee_user_id', 'status'], 'co_doc_sub_appr_user_idx');

            $table->foreign('submission_id', 'co_doc_sub_appr_sub_fk')
                ->references('submission_id')
                ->on('tbl_company_document_submissions')
                ->cascadeOnDelete();

            $table->foreign('approval_id', 'co_doc_sub_appr_appr_fk')
                ->references('approval_id')
                ->on('tbl_company_document_approvals')
                ->nullOnDelete();

            $table->foreign('assignee_user_id', 'co_doc_sub_appr_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_company_document_submission_approvals');
        Schema::dropIfExists('tbl_company_document_submission_values');
        Schema::dropIfExists('tbl_company_document_submissions');
        Schema::dropIfExists('tbl_company_document_approval_assignees');
        Schema::dropIfExists('tbl_company_document_approvals');
        Schema::dropIfExists('tbl_company_document_elements');
        Schema::dropIfExists('tbl_company_document_forms');
    }
};
