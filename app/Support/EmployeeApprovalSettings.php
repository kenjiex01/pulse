<?php

namespace App\Support;

use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\LuUserRequestType;
use Illuminate\Support\Collection;

class EmployeeApprovalSettings
{
    public static function formTypes(): Collection
    {
        return LuUserRequestType::query()
            ->forEmployeeProfileSettings()
            ->get();
    }

    public static function stepsFor(Employee $employee, int $formTypeId): Collection
    {
        return ApprovalStep::query()
            ->where('employee_id', $employee->employee_id)
            ->where('form_type', $formTypeId)
            ->with(['members' => fn ($query) => $query->with('user')->orderBy('approval_step_member_id')])
            ->orderBy('step_no')
            ->orderBy('approval_step_id')
            ->get();
    }
}
