<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_timekeeping_memo_setups', function (Blueprint $table) {
            $table->unsignedInteger('occurrence_count')->default(1)->after('email_cc');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_memo_setups', function (Blueprint $table) {
            $table->dropColumn('occurrence_count');
        });
    }
};
