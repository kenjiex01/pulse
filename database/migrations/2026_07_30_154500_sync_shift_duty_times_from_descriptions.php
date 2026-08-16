<?php

use App\Support\ShiftCodeDescriptionParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $shifts = DB::table('tbl_shift_codes')
            ->whereNull('deleted_at')
            ->get(['shift_code_id', 'shift_code', 'description', 'time_in', 'time_out']);

        foreach ($shifts as $shift) {
            $schedule = ShiftCodeDescriptionParser::parseDutySchedule((string) $shift->description);

            if ($schedule === null) {
                continue;
            }

            $previousTimeIn = (string) $shift->time_in;
            $previousTimeOut = (string) $shift->time_out;

            DB::table('tbl_shift_codes')
                ->where('shift_code_id', $shift->shift_code_id)
                ->update([
                    'time_in' => $schedule['time_in'],
                    'time_out' => $schedule['time_out'],
                    'updated_at' => now(),
                ]);

            $hadMisplacedBreakWindow = $previousTimeIn !== '00:00'
                && $previousTimeOut !== '00:00'
                && (
                    $previousTimeIn !== $schedule['time_in']
                    || $previousTimeOut !== $schedule['time_out']
                );

            if (! $hadMisplacedBreakWindow) {
                continue;
            }

            $firstBreak = DB::table('tbl_shift_code_breaks')
                ->where('shift_code_id', $shift->shift_code_id)
                ->whereNull('deleted_at')
                ->orderBy('shift_code_break_no')
                ->first(['shift_code_break_id', 'break_out', 'break_in']);

            if ($firstBreak === null) {
                continue;
            }

            DB::table('tbl_shift_code_breaks')
                ->where('shift_code_break_id', $firstBreak->shift_code_break_id)
                ->update([
                    'break_out' => $firstBreak->break_out ?? $previousTimeIn,
                    'break_in' => $firstBreak->break_in ?? $previousTimeOut,
                ]);
        }
    }

    public function down(): void
    {
        // One-time data correction — not reversed automatically.
    }
};
