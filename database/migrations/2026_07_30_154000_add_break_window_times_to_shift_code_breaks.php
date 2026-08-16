<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_shift_code_breaks', function (Blueprint $table) {
            $table->string('break_out', 5)->nullable()->after('shift_code_break_no');
            $table->string('break_in', 5)->nullable()->after('break_out');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_shift_code_breaks', function (Blueprint $table) {
            $table->dropColumn(['break_out', 'break_in']);
        });
    }
};
