<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RawTimekeepingInandout;
use App\Models\TimekeepingEmployeeRestDay;
use App\Models\TimekeepingEmployeeSetup;
use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\EmployeeApprovalSettings;
use App\Support\TimekeepingEmployeeProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TimekeepingEmployeeProfileController extends Controller
{
    public function index(Request $request): View
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        $search = $request->string('search')->trim()->toString();
        $formOptions = TimekeepingEmployeeProfile::formOptions();

        $employees = TimekeepingEmployeeProfile::query()
            ->search($search)
            ->paginate(LiveTable::perPage($request, 15))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'tbl_timekeeping_employee_setup',
                description: 'Viewed Employee Profile list ('.$employees->total().' employees)',
            );
        }

        $viewData = [
            'employees' => $employees,
            'search' => $search,
            'formOptions' => $formOptions,
            'openSetupEmployeeId' => old('setup_employee_id', $request->input('setup_employee')),
            'openViewEmployeeId' => $request->input('view_employee'),
        ];

        if ($request->ajax()) {
            return view('timekeeping.employee-profile._results', $viewData);
        }

        return view('timekeeping.employee-profile.index', $viewData);
    }

    public function show(Request $request, Employee $employee): RedirectResponse
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        return redirect()->route(TimekeepingEmployeeProfile::routeName('index'), [
            'view_employee' => $employee->employee_id,
            'search' => $request->input('search'),
            'page' => $request->input('page'),
        ]);
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'update');

        $validated = $request->validate([
            'timekeeping_holiday_group_id' => ['required', 'integer', 'exists:tbl_timekeeping_holiday_groups,timekeeping_holiday_group_id'],
            'shift_code_id' => ['required', 'integer', 'exists:tbl_shift_codes,shift_code_id'],
            'timekeeping_policy_id' => ['required', 'integer', 'exists:tbl_timekeeping_policies,timekeeping_policy_id'],
            'is_leave' => ['nullable', 'boolean'],
            'is_populate' => ['nullable', 'boolean'],
            'rest_days' => ['nullable', 'array'],
            'rest_days.*.selected' => ['nullable', 'boolean'],
            'rest_days.*.is_paid' => ['nullable', 'boolean'],
        ]);

        $existingSetup = $employee->timekeepingSetup;
        $isCreate = $existingSetup === null;

        DB::transaction(function () use ($employee, $validated, $isCreate): void {
            $setup = TimekeepingEmployeeSetup::query()->updateOrCreate(
                ['employee_id' => $employee->employee_id],
                [
                    'timekeeping_holiday_group_id' => $validated['timekeeping_holiday_group_id'],
                    'shift_code_id' => $validated['shift_code_id'],
                    'timekeeping_policy_id' => $validated['timekeeping_policy_id'],
                    'is_leave' => (bool) ($validated['is_leave'] ?? false),
                    'is_populate' => (bool) ($validated['is_populate'] ?? false),
                ],
            );

            TimekeepingEmployeeRestDay::query()
                ->where('employee_id', $employee->employee_id)
                ->delete();

            $restDays = collect($validated['rest_days'] ?? [])
                ->filter(fn (array $day) => ! empty($day['selected']))
                ->map(fn (array $day, int|string $dayId) => [
                    'employee_id' => $employee->employee_id,
                    'day_id' => (int) $dayId,
                    'is_paid' => ! empty($day['is_paid']),
                ])
                ->values()
                ->all();

            if ($restDays !== []) {
                TimekeepingEmployeeRestDay::query()->insert($restDays);
            }
        });

        SysLogService::record(
            action: $isCreate ? 'add' : 'edit',
            table: 'tbl_timekeeping_employee_setup',
            description: ($isCreate ? 'Added' : 'Updated').' timekeeping profile for '.$employee->full_name.' ('.$employee->employee_number.')',
            recordId: $employee->employee_id,
        );

        return redirect()
            ->route(TimekeepingEmployeeProfile::routeName('index'), [
                'search' => $request->input('search'),
                'page' => $request->input('page'),
                'setup_employee' => $employee->employee_id,
            ])
            ->with('success', 'Employee profile settings saved successfully.');
    }

    public function approvalSettings(Request $request, Employee $employee): View
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        if (! TimekeepingEmployeeProfile::showApprovalMatrix()) {
            abort(404);
        }

        $formTypeId = (int) $request->input('form_type_id', 0);

        return view('timekeeping.employee-profile._tab-approval-settings', [
            'employee' => $employee,
            'formTypeId' => $formTypeId,
        ]);
    }

    public function approvalRoutes(Request $request, Employee $employee): View
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        if (! TimekeepingEmployeeProfile::showApprovalMatrix()) {
            abort(404);
        }

        $formTypeId = (int) $request->input('form_type_id', 0);

        return view('timekeeping.employee-profile._tab-approval-routes', [
            'employee' => $employee,
            'formTypeId' => $formTypeId,
            'steps' => EmployeeApprovalSettings::stepsFor($employee, $formTypeId),
        ]);
    }

    public function attendanceView(Request $request, Employee $employee): View
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        $perPage = LiveTable::perPage($request, 10);
        $pageName = 'attendance_page';

        $query = RawTimekeepingInandout::query()
            ->where('employee_id', $employee->employee_id)
            ->orderBy('dt_datetime')
            ->orderBy('timekeeping_inandout_id');

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($lastPage, (int) $request->input($pageName, $lastPage)));

        $attendanceLogs = $query->paginate($perPage, ['*'], $pageName, $page);

        return view('timekeeping.employee-profile._tab-attendance-view', [
            'employee' => $employee->loadMissing('timekeepingSetup'),
            'attendanceLogs' => $attendanceLogs,
        ]);
    }

    public function employeeLoadView(Request $request, Employee $employee): View
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        $summary = TimekeepingEmployeeProfile::employeeLoadSummary($employee);

        $perPage = LiveTable::perPage($request, 10);
        $pageName = 'employee_load_page';
        $query = TimekeepingEmployeeProfile::employeeLoadEntriesQuery($employee);

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($lastPage, (int) $request->input($pageName, $lastPage)));

        $employeeLoadEntries = $query->paginate($perPage, ['*'], $pageName, $page);

        $employee->loadMissing('timekeepingSetup.policy');
        $policy = $employee->timekeepingSetup?->policy;

        return view('timekeeping.employee-profile._tab-employee-load-view', [
            'employee' => $employee,
            'employeeLoadEntries' => $employeeLoadEntries,
            'summary' => $summary,
            'timekeepingPolicy' => $policy,
        ]);
    }
}
