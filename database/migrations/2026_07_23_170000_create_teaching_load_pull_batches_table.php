<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_load_pull_batches', function (Blueprint $table) {
            $table->id('teaching_load_pull_batch_id');
            $table->unsignedInteger('batch_no')->default(0);
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedInteger('employee_count')->default(0);
            $table->unsignedInteger('records_count')->default(0);
            $table->unsignedBigInteger('pulled_by_id')->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('pulled_by_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('teaching_load_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('teaching_load_pull_batch_id')->nullable()->after('teaching_load_session_id');
            $table->foreign('teaching_load_pull_batch_id')->references('teaching_load_pull_batch_id')->on('teaching_load_pull_batches')->nullOnDelete();
            $table->index(['teaching_load_pull_batch_id', 'employee_id'], 'teaching_load_sessions_batch_employee_idx');
        });

        $groups = DB::table('teaching_load_sessions')
            ->select('pulled_at', 'date_from', 'date_to', 'pulled_by_id')
            ->whereNull('deleted_at')
            ->groupBy('pulled_at', 'date_from', 'date_to', 'pulled_by_id')
            ->orderBy('pulled_at')
            ->get();

        $batchNo = 0;

        foreach ($groups as $group) {
            $batchNo++;
            $batchId = DB::table('teaching_load_pull_batches')->insertGetId([
                'batch_no' => $batchNo,
                'date_from' => $group->date_from,
                'date_to' => $group->date_to,
                'employee_count' => (int) DB::table('teaching_load_sessions')
                    ->whereNull('deleted_at')
                    ->where('pulled_at', $group->pulled_at)
                    ->where('date_from', $group->date_from)
                    ->where('date_to', $group->date_to)
                    ->where(function ($query) use ($group) {
                        if ($group->pulled_by_id === null) {
                            $query->whereNull('pulled_by_id');
                        } else {
                            $query->where('pulled_by_id', $group->pulled_by_id);
                        }
                    })
                    ->distinct()
                    ->count('employee_id'),
                'records_count' => (int) DB::table('teaching_load_sessions')
                    ->whereNull('deleted_at')
                    ->where('pulled_at', $group->pulled_at)
                    ->where('date_from', $group->date_from)
                    ->where('date_to', $group->date_to)
                    ->where(function ($query) use ($group) {
                        if ($group->pulled_by_id === null) {
                            $query->whereNull('pulled_by_id');
                        } else {
                            $query->where('pulled_by_id', $group->pulled_by_id);
                        }
                    })
                    ->count(),
                'pulled_by_id' => $group->pulled_by_id,
                'pulled_at' => $group->pulled_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('teaching_load_sessions')
                ->whereNull('deleted_at')
                ->where('pulled_at', $group->pulled_at)
                ->where('date_from', $group->date_from)
                ->where('date_to', $group->date_to)
                ->where(function ($query) use ($group) {
                    if ($group->pulled_by_id === null) {
                        $query->whereNull('pulled_by_id');
                    } else {
                        $query->where('pulled_by_id', $group->pulled_by_id);
                    }
                })
                ->update(['teaching_load_pull_batch_id' => $batchId]);
        }
    }

    public function down(): void
    {
        Schema::table('teaching_load_sessions', function (Blueprint $table) {
            $table->dropIndex('teaching_load_sessions_batch_employee_idx');
            $table->dropForeign(['teaching_load_pull_batch_id']);
            $table->dropColumn('teaching_load_pull_batch_id');
        });

        Schema::dropIfExists('teaching_load_pull_batches');
    }
};
