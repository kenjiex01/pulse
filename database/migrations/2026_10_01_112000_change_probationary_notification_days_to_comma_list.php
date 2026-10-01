<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('tbl_hr_setup_settings', function (Blueprint $table) {
                $table->string('probationary_end_notification_days', 255)->nullable()->change();
            });

            return;
        }

        DB::statement(
            'ALTER TABLE tbl_hr_setup_settings MODIFY probationary_end_notification_days VARCHAR(255) NULL'
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('tbl_hr_setup_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('probationary_end_notification_days')->nullable()->change();
            });

            return;
        }

        DB::statement(
            'ALTER TABLE tbl_hr_setup_settings MODIFY probationary_end_notification_days SMALLINT UNSIGNED NULL'
        );
    }
};
