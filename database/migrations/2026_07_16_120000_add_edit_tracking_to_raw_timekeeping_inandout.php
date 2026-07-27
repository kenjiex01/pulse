<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_timekeeping_inandout', function (Blueprint $table) {
            $table->dateTime('original_dt_datetime')->nullable()->after('dt_datetime');
            $table->boolean('original_is_in')->nullable()->after('original_dt_datetime');
            $table->boolean('is_edited')->default(false)->after('original_is_in');
            $table->timestamp('edited_at')->nullable()->after('is_edited');
            $table->unsignedBigInteger('edited_by_id')->nullable()->after('edited_at');

            $table->index('is_edited', 'idx_rtio_is_edited');
        });
    }

    public function down(): void
    {
        Schema::table('raw_timekeeping_inandout', function (Blueprint $table) {
            $table->dropIndex('idx_rtio_is_edited');
            $table->dropColumn([
                'original_dt_datetime',
                'original_is_in',
                'is_edited',
                'edited_at',
                'edited_by_id',
            ]);
        });
    }
};
