<?php

use App\Models\CompanyDocumentForm;
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

        if (! Schema::hasColumn('tbl_company_document_forms', 'is_nte')) {
            Schema::table('tbl_company_document_forms', function (Blueprint $table) {
                $table->boolean('is_nte')->default(false);
            });
        }

        CompanyDocumentForm::query()
            ->where('code', 'hr_notice_to_explain')
            ->update(['is_nte' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tbl_company_document_forms')) {
            return;
        }

        if (! Schema::hasColumn('tbl_company_document_forms', 'is_nte')) {
            return;
        }

        Schema::table('tbl_company_document_forms', function (Blueprint $table) {
            $table->dropColumn('is_nte');
        });
    }
};
