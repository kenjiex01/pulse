<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_employee_load_entries', function (Blueprint $table) {
            $table->decimal('total_hours', 8, 2)->nullable()->after('class_schedule');
        });

        // Backfill from teaching_load_sessions when offering + date + employee match.
        $sessions = DB::table('teaching_load_sessions')
            ->whereNull('deleted_at')
            ->whereNotNull('total_hours')
            ->get(['employee_id', 'session_date', 'skolaris_offering_id', 'subject_code', 'section', 'class_schedule', 'total_hours']);

        foreach ($sessions as $session) {
            $query = DB::table('raw_employee_load_entries')
                ->whereNull('deleted_at')
                ->where('employee_id', $session->employee_id)
                ->whereDate('session_date', $session->session_date);

            if ($session->skolaris_offering_id) {
                $query->where('skolaris_offering_id', $session->skolaris_offering_id);
            } else {
                if ($session->section) {
                    $query->where('section', $session->section);
                }
                if ($session->class_schedule) {
                    $query->where('class_schedule', $session->class_schedule);
                }
            }

            $query->update(['total_hours' => $session->total_hours]);
        }
    }

    public function down(): void
    {
        Schema::table('raw_employee_load_entries', function (Blueprint $table) {
            $table->dropColumn('total_hours');
        });
    }
};
