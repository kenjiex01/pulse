<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_timekeeping_memo_setups', function (Blueprint $table) {
            $table->string('email_subject', 255)->nullable()->after('company_document_form_id');
            $table->text('email_body')->nullable()->after('email_subject');
            $table->string('email_cc', 500)->nullable()->after('email_body');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_memo_setups', function (Blueprint $table) {
            $table->dropColumn(['email_subject', 'email_body', 'email_cc']);
        });
    }
};
