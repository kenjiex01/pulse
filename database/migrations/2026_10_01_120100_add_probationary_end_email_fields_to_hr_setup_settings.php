<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_hr_setup_settings', function (Blueprint $table) {
            $table->string('probationary_end_email_subject', 255)->nullable()->after('probationary_end_notification_days');
            $table->text('probationary_end_email_body')->nullable()->after('probationary_end_email_subject');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_hr_setup_settings', function (Blueprint $table) {
            $table->dropColumn([
                'probationary_end_email_subject',
                'probationary_end_email_body',
            ]);
        });
    }
};
