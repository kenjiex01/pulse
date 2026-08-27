<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_campuses', function (Blueprint $table) {
            $table->decimal('minimum_wage', 12, 2)->nullable()->after('parent_campus_id');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_campuses', function (Blueprint $table) {
            $table->dropColumn('minimum_wage');
        });
    }
};
