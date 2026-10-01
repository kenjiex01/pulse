<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_hr_setup_settings', function (Blueprint $table) {
            $table->text('hr_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_hr_setup_settings', function (Blueprint $table) {
            $table->string('hr_email', 255)->nullable()->change();
        });
    }
};
