<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_hr_setup_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('probationary_end_notification_days')
                ->nullable()
                ->after('hr_email');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_hr_setup_settings', function (Blueprint $table) {
            $table->dropColumn('probationary_end_notification_days');
        });
    }
};
