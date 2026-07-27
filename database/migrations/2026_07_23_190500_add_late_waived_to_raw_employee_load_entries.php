<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_employee_load_entries', function (Blueprint $table) {
            $table->boolean('late_waived')->default(false)->after('time_out');
        });
    }

    public function down(): void
    {
        Schema::table('raw_employee_load_entries', function (Blueprint $table) {
            $table->dropColumn('late_waived');
        });
    }
};
