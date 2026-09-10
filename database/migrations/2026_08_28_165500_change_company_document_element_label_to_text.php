<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_company_document_elements', function (Blueprint $table) {
            $table->text('label')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_company_document_elements', function (Blueprint $table) {
            $table->string('label', 255)->nullable()->change();
        });
    }
};
