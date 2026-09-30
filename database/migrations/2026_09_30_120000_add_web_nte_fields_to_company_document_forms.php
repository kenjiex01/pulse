<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_company_document_forms', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_company_document_forms', 'expects_web_nte_response')) {
                $table->boolean('expects_web_nte_response')->default(false)->after('requires_nte');
            }
            if (! Schema::hasColumn('tbl_company_document_forms', 'nte_response_days')) {
                $table->unsignedSmallInteger('nte_response_days')->default(7)->after('expects_web_nte_response');
            }
            if (! Schema::hasColumn('tbl_company_document_forms', 'skolaris_nte_request_type_code')) {
                $table->string('skolaris_nte_request_type_code', 60)->nullable()->after('nte_response_days');
            }
        });

        DB::table('tbl_company_document_forms')
            ->where('is_nte', true)
            ->update([
                'expects_web_nte_response' => true,
                'nte_response_days' => 7,
                'requires_nte' => false,
            ]);

        DB::table('tbl_company_document_forms')
            ->where('requires_nte', true)
            ->where('is_nte', false)
            ->update(['requires_nte' => false]);
    }

    public function down(): void
    {
        Schema::table('tbl_company_document_forms', function (Blueprint $table) {
            foreach (['skolaris_nte_request_type_code', 'nte_response_days', 'expects_web_nte_response'] as $column) {
                if (Schema::hasColumn('tbl_company_document_forms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
