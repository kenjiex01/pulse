<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_timekeeping_policy_tardiness', function (Blueprint $table) {
            $table->boolean('marks_absent')->default(false)->after('equivalent');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_policy_tardiness', function (Blueprint $table) {
            $table->dropColumn('marks_absent');
        });
    }
};
