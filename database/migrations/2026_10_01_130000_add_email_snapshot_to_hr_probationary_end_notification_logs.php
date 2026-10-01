<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_hr_probationary_end_notification_logs', function (Blueprint $table) {
            $table->string('hr_email_to', 255)->nullable()->after('recipient_type');
            $table->string('email_subject', 255)->nullable()->after('hr_email_to');
            $table->text('email_body')->nullable()->after('email_subject');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_hr_probationary_end_notification_logs', function (Blueprint $table) {
            $table->dropColumn(['hr_email_to', 'email_subject', 'email_body']);
        });
    }
};
