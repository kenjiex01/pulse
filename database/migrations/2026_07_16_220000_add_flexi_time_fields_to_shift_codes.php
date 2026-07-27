<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_shift_codes', function (Blueprint $table) {
            $table->boolean('is_flexi_time')->default(false)->after('time_out');
            $table->decimal('expected_hours_per_day', 7, 4)->unsigned()->nullable()->after('is_flexi_time');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_shift_codes', function (Blueprint $table) {
            $table->dropColumn(['is_flexi_time', 'expected_hours_per_day']);
        });
    }
};
