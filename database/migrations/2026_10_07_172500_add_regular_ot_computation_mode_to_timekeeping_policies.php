<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->unsignedTinyInteger('regular_ot_computation_mode')->nullable()->after('is_ot_form_required');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->dropColumn('regular_ot_computation_mode');
        });
    }
};
