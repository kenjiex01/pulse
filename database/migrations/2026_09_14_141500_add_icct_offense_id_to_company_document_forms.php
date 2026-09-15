<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbl_company_document_forms')) {
            return;
        }

        if (Schema::hasColumn('tbl_company_document_forms', 'icct_offense_id')) {
            return;
        }

        Schema::table('tbl_company_document_forms', function (Blueprint $table) {
            $table->unsignedBigInteger('icct_offense_id')->nullable()->after('document_type');
            $table->foreign('icct_offense_id', 'company_document_form_icct_offense_fk')
                ->references('icct_offense_id')
                ->on('lu_icct_offenses')
                ->nullOnDelete();
            $table->index('icct_offense_id', 'company_document_form_icct_offense_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tbl_company_document_forms', 'icct_offense_id')) {
            return;
        }

        Schema::table('tbl_company_document_forms', function (Blueprint $table) {
            $table->dropForeign('company_document_form_icct_offense_fk');
            $table->dropIndex('company_document_form_icct_offense_idx');
            $table->dropColumn('icct_offense_id');
        });
    }
};
