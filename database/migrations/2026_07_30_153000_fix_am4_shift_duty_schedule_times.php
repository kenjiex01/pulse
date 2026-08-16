<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbl_shift_codes')
            ->where('shift_code', 'AM4')
            ->where('time_in', '14:00')
            ->where('time_out', '15:00')
            ->update([
                'time_in' => '10:00',
                'time_out' => '19:00',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('tbl_shift_codes')
            ->where('shift_code', 'AM4')
            ->where('time_in', '10:00')
            ->where('time_out', '19:00')
            ->update([
                'time_in' => '14:00',
                'time_out' => '15:00',
                'updated_at' => now(),
            ]);
    }
};
