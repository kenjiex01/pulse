<?php

namespace App\Support;

use App\Models\CompanyDocumentSendLog;
use Illuminate\Support\Collection;

class CompanyDocumentSendHistorySummary
{
    /**
     * @param  Collection<int, CompanyDocumentSendLog>  $logs
     * @return Collection<int, object{
     *     employee: \App\Models\Employee|null,
     *     send_count: int,
     *     sent_at_list: Collection<int, \Illuminate\Support\Carbon|null>,
     *     last_sent_at: \Illuminate\Support\Carbon|null,
     *     last_sender: \App\Models\User|null
     * }>
     */
    public static function byEmployee(Collection $logs): Collection
    {
        return $logs
            ->groupBy('employee_id')
            ->map(function (Collection $employeeLogs) {
                $employeeLogs = $employeeLogs->sortByDesc('sent_at')->values();
                $latest = $employeeLogs->first();

                return (object) [
                    'employee' => $latest?->employee,
                    'send_count' => $employeeLogs->count(),
                    'sent_at_list' => $employeeLogs->pluck('sent_at')->filter()->values(),
                    'last_sent_at' => $latest?->sent_at,
                    'last_sender' => $latest?->sender,
                ];
            })
            ->sortByDesc(fn (object $row) => $row->last_sent_at?->timestamp ?? 0)
            ->values();
    }
}
