<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->foreignId('campus_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('tbl_campuses', 'campus_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campus_id');
        });
    }
};
