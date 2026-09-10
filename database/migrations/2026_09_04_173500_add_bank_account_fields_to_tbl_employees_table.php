<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->string('bank_name', 150)->nullable()->after('tax_status');
            $table->string('bank_account_number', 45)->nullable()->after('bank_name');
            $table->string('bank_account_type', 20)->nullable()->after('bank_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_account_type']);
        });
    }
};
