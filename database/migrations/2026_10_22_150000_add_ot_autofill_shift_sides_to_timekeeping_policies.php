<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->boolean('is_ot_autofill_before_shift')->default(true)->after('max_rest_days_per_week');
            $table->boolean('is_ot_autofill_after_shift')->default(true)->after('is_ot_autofill_before_shift');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_timekeeping_policies', function (Blueprint $table) {
            $table->dropColumn([
                'is_ot_autofill_before_shift',
                'is_ot_autofill_after_shift',
            ]);
        });
    }
};
