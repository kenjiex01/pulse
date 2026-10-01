<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_hr_probationary_end_notification_logs', function (Blueprint $table) {
            $table->id('hr_probationary_end_notification_log_id');
            $table->foreignId('employee_id')->constrained('tbl_employees', 'employee_id')->cascadeOnDelete();
            $table->date('probationary_end_date');
            $table->unsignedSmallInteger('days_before');
            $table->string('recipient_type', 20);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(
                ['employee_id', 'probationary_end_date', 'days_before', 'recipient_type'],
                'hr_prob_end_notif_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_hr_probationary_end_notification_logs');
    }
};
