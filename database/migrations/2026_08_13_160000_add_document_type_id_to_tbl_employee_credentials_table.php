<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employee_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('document_type_id')->nullable()->after('employee_id');
            $table->index('document_type_id');
            $table->foreign('document_type_id')
                ->references('document_type_id')
                ->on('tbl_document_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employee_credentials', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropIndex(['document_type_id']);
            $table->dropColumn('document_type_id');
        });
    }
};
