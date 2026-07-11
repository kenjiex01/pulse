<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trn_payroll_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('posted_by_id')->nullable()->after('dt_processed');
            $table->dateTime('dt_posted')->nullable()->after('posted_by_id');

            $table->foreign('posted_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trn_payroll_batches', function (Blueprint $table) {
            $table->dropForeign(['posted_by_id']);
            $table->dropColumn(['posted_by_id', 'dt_posted']);
        });
    }
};
