<?php

namespace App\Services;

use App\Models\College;
use App\Models\PayrollCalendar;
use App\Models\PayrollCalendarCollege;
use App\Models\PayrollCalendarUserType;
use Illuminate\Support\Facades\DB;

class PayrollCalendarScopeService
{
    /**
     * @param  array<int, string>  $collegeCodes
     * @return array<int, int>
     */
    public function resolveCollegeIds(array $collegeCodes): array
    {
        $codes = collect($collegeCodes)->map(fn ($code) => (string) $code)->filter()->unique()->values();

        if ($codes->isEmpty()) {
            return [];
        }

        return College::query()
            ->active()
            ->whereIn('college_code', $codes)
            ->pluck('college_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $collegeCodes
     * @param  array<int, string>  $userTypes
     */
    public function sync(PayrollCalendar $period, array $collegeCodes, array $userTypes): void
    {
        $collegeIds = collect($this->resolveCollegeIds($collegeCodes));
        $userTypes = collect($userTypes)->filter()->unique()->values();

        DB::transaction(function () use ($period, $collegeIds, $userTypes): void {
            PayrollCalendarCollege::query()
                ->where('payroll_calendar_id', $period->payroll_calendar_id)
                ->when($collegeIds->isNotEmpty(), fn ($query) => $query->whereNotIn('college_id', $collegeIds))
                ->when($collegeIds->isEmpty(), fn ($query) => $query)
                ->delete();

            PayrollCalendarUserType::query()
                ->where('payroll_calendar_id', $period->payroll_calendar_id)
                ->when($userTypes->isNotEmpty(), fn ($query) => $query->whereNotIn('user_type', $userTypes))
                ->when($userTypes->isEmpty(), fn ($query) => $query)
                ->delete();

            foreach ($collegeIds as $collegeId) {
                PayrollCalendarCollege::query()->firstOrCreate([
                    'payroll_calendar_id' => $period->payroll_calendar_id,
                    'college_id' => $collegeId,
                ]);
            }

            foreach ($userTypes as $userType) {
                PayrollCalendarUserType::query()->firstOrCreate([
                    'payroll_calendar_id' => $period->payroll_calendar_id,
                    'user_type' => $userType,
                ]);
            }
        });
    }
}
