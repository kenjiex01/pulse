<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_campuses', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_campus_id')->nullable()->after('campus_name');
            $table->foreign('parent_campus_id', 'fk_campuses_parent_campus_id')
                ->references('campus_id')
                ->on('tbl_campuses')
                ->nullOnDelete();
        });

        $caintaId = DB::table('tbl_campuses')
            ->where('campus_code', 'CA')
            ->whereNull('deleted_at')
            ->value('campus_id');

        if ($caintaId) {
            DB::table('tbl_campuses')
                ->where('campus_code', 'GH')
                ->whereNull('deleted_at')
                ->whereNull('parent_campus_id')
                ->update(['parent_campus_id' => $caintaId]);
        }
    }

    public function down(): void
    {
        Schema::table('tbl_campuses', function (Blueprint $table) {
            $table->dropForeign('fk_campuses_parent_campus_id');
            $table->dropColumn('parent_campus_id');
        });
    }
};
