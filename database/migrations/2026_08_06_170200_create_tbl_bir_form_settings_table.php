<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_bir_form_settings', function (Blueprint $table) {
            $table->id('bir_form_setting_id');
            $table->string('company_name', 255)->nullable();
            $table->string('company_address', 500)->nullable();
            $table->string('company_tin', 30)->nullable();
            $table->string('company_rdo_code', 20)->nullable();
            $table->string('company_zip', 20)->nullable();
            $table->string('signatory_name', 255)->nullable();
            $table->string('signatory_title', 255)->nullable();
            $table->string('compensation_atc', 20)->nullable();
            $table->decimal('smw_rate_per_day', 12, 2)->nullable();
            $table->decimal('smw_rate_per_month', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_bir_form_settings');
    }
};
