<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbl_company_document_memo_sequences')) {
            Schema::create('tbl_company_document_memo_sequences', function (Blueprint $table) {
                $table->id('company_document_memo_sequence_id');
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();

                $table->unique('year', 'co_doc_memo_seq_year_unique');
            });
        }

        Schema::table('tbl_company_document_send_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_company_document_send_logs', 'control_no')) {
                $table->string('control_no', 40)->nullable()->after('submission_id');
            }
        });

        Schema::table('tbl_timekeeping_memo_send_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_timekeeping_memo_send_logs', 'control_no')) {
                $table->string('control_no', 40)->nullable()->after('submission_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_company_document_send_logs', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_company_document_send_logs', 'control_no')) {
                $table->dropColumn('control_no');
            }
        });

        Schema::table('tbl_timekeeping_memo_send_logs', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_timekeeping_memo_send_logs', 'control_no')) {
                $table->dropColumn('control_no');
            }
        });

        Schema::dropIfExists('tbl_company_document_memo_sequences');
    }
};
