<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RawTimekeepingInandout;
use App\Models\TimekeepingEmployeeRestDay;
use App\Models\TimekeepingEmployeeSetup;
use App\Services\EmployeeAttendanceLogService;
use App\Services\EmployeeAttendanceViewService;
use App\Services\SysLogService;
use App\Services\EmployeeLoadAttendanceMatcher;
use App\Services\TimekeepingEmployeeProfileUploadService;
use App\Support\LiveTable;
use App\Support\EmployeeApprovalSettings;
use App\Support\ReportPdfDownload;
use App\Support\TimekeepingEmployeeProfile;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimekeepingEmployeeProfileController extends Controller
{
    public function __construct(
        private readonly EmployeeAttendanceLogService $attendanceLogService,
        private readonly EmployeeAttendanceViewService $attendanceViewService,
        private readonly TimekeepingEmployeeProfileUploadService $uploadService,
    ) {}

    public function index(Request $request): View
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        $search = $request->string('search')->trim()->toString();
        $formOptions = TimekeepingEmployeeProfile::formOptions();

        $employees = TimekeepingEmployeeProfile::query()
            ->search($search)
            ->paginate(LiveTable::perPage($request))
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

        if (! $request->ajax()) {
            $stagingToken = (string) session('employee_profile_upload_staging_token', '');
            $openPreview = $request->boolean('preview') && $stagingToken !== '';
            $viewData['openUpload'] = $request->boolean('upload');
            $viewData['openPreview'] = $openPreview;
            $viewData['stagingToken'] = $stagingToken;
            $viewData['staging'] = $openPreview ? $this->uploadService->getStaging($request->user(), $stagingToken) : null;
        }

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
            'is_auto_compute_excess_as_ot' => ['nullable', 'boolean'],
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
                    'is_auto_compute_excess_as_ot' => (bool) ($validated['is_auto_compute_excess_as_ot'] ?? false),
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

    public function downloadUploadTemplate(Request $request): StreamedResponse
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'update');

        $content = $this->uploadService->buildTemplateContent();
        $filename = 'employee_profile_setup_template.csv';

        return response()->streamDownload(
            fn () => print($content),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    public function processUpload(Request $request): RedirectResponse
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'update');

        $validated = $request->validate([
            'upload_file' => ['required', 'file', 'max:'.config('uploads.max_file_kb', 15360)],
        ]);

        try {
            $result = $this->uploadService->parseUploadedFile($request->file('upload_file'));
            $token = $this->uploadService->createStagingToken($request->user(), $result);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimekeepingEmployeeProfile::routeName('index'), ['upload' => 1])
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        session(['employee_profile_upload_staging_token' => $token]);

        return redirect()
            ->route(TimekeepingEmployeeProfile::routeName('index'), ['preview' => 1])
            ->with('success', 'File parsed. Review the preview before loading to the database.');
    }

    public function commitUpload(Request $request): RedirectResponse
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'update');

        $validated = $request->validate([
            'staging_token' => ['required', 'string'],
        ]);

        try {
            $result = $this->uploadService->commit($request->user(), $validated['staging_token']);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(TimekeepingEmployeeProfile::routeName('index'), ['preview' => 1])
                ->with('error', $exception->getMessage());
        }

        session()->forget('employee_profile_upload_staging_token');

        return redirect()
            ->route(TimekeepingEmployeeProfile::routeName('index'))
            ->with('success', 'Timekeeping setup updated for '.$result['applied'].' employee(s).');
    }

    public function discardUpload(Request $request): RedirectResponse
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'update');

        $token = (string) $request->input('staging_token', session('employee_profile_upload_staging_token'));

        if ($token !== '') {
            $this->uploadService->discardStaging($request->user(), $token);
        }

        session()->forget('employee_profile_upload_staging_token');

        return redirect()
            ->route(TimekeepingEmployeeProfile::routeName('index'), ['upload' => 1])
            ->with('success', 'Upload cancelled.');
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

        [$dateFrom, $dateTo] = $this->resolveAttendanceDateRange($request);

        $attendance = $this->attendanceViewService->rangeForEmployee($employee, $dateFrom, $dateTo);

        return view('timekeeping.employee-profile._tab-attendance-view', [
            'employee' => $employee->loadMissing(['timekeepingSetup', 'timekeepingRestDays']),
            'attendance' => $attendance,
            'selectedDate' => $request->input('day'),
        ]);
    }

    public function downloadAttendanceViewPdf(Request $request, Employee $employee): StreamedResponse
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        [$dateFrom, $dateTo] = $this->resolveAttendanceDateRange($request);

        $result = $this->attendanceViewService->pdfResultForEmployee($employee, $dateFrom, $dateTo);

        SysLogService::record(
            action: 'read',
            table: 'raw_timekeeping_inandout',
            recordId: (int) $employee->employee_id,
            description: 'Downloaded Attendance View PDF for '.$employee->full_name.' ('.$employee->employee_number.') — '.($result->meta['period_label'] ?? ''),
        );

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $employee->employee_number) ?: 'employee';

        return ReportPdfDownload::stream(
            $result,
            sprintf('Attendance_View_%s_%s_%s', $safeNumber, $dateFrom, $dateTo),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveAttendanceDateRange(Request $request): array
    {
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        if ($dateFrom === '' || $dateTo === '') {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);

            if ($year < 2000 || $year > 2100) {
                $year = (int) now()->year;
            }

            if ($month < 1 || $month > 12) {
                $month = (int) now()->month;
            }

            $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
            $dateFrom = $start->toDateString();
            $dateTo = $start->endOfMonth()->toDateString();
        }

        try {
            $from = CarbonImmutable::parse($dateFrom)->startOfDay();
            $to = CarbonImmutable::parse($dateTo)->startOfDay();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
            $from = CarbonImmutable::parse($start->toDateString());
            $to = CarbonImmutable::parse($start->copy()->endOfMonth()->toDateString());
        }

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $maxDays = 92;
        if (((int) $from->diffInDays($to) + 1) > $maxDays) {
            $to = $from->addDays($maxDays - 1);
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    public function calendarView(Request $request, Employee $employee): View
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        $calendar = $this->attendanceLogService->calendarMonth($employee, $year, $month);

        return view('timekeeping.employee-profile._tab-calendar-view', [
            'employee' => $employee->loadMissing('timekeepingSetup'),
            'calendar' => $calendar,
            'selectedDate' => $request->input('day'),
        ]);
    }

    public function employeeLoadView(Request $request, Employee $employee, EmployeeLoadAttendanceMatcher $attendanceMatcher): View
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'view');

        $employeeLoadEntries = TimekeepingEmployeeProfile::employeeLoadEntriesQuery($employee)
            ->with('transaction')
            ->get();

        // Skolaris-pulled loads keep schedule only; Time In/Out are resolved from attendance logs.
        $skolarisEntries = $employeeLoadEntries->filter(function ($entry) {
            return str_starts_with((string) ($entry->transaction?->filename ?? ''), 'Skolaris Pull')
                || ($entry->verification_remarks === 'Pulled from Skolaris');
        });

        if ($skolarisEntries->isNotEmpty()) {
            $attendanceMatcher->applyToEntries($employee, $skolarisEntries);
            $employeeLoadEntries = TimekeepingEmployeeProfile::employeeLoadEntriesQuery($employee)
                ->with('transaction')
                ->get();
        }

        $summary = TimekeepingEmployeeProfile::employeeLoadSummary($employee);

        $employee->loadMissing('timekeepingSetup.policy');
        $policy = $employee->timekeepingSetup?->policy;

        return view('timekeeping.employee-profile._tab-employee-load-view', [
            'employee' => $employee,
            'employeeLoadEntries' => $employeeLoadEntries,
            'summary' => $summary,
            'timekeepingPolicy' => $policy,
        ]);
    }

    public function storeAttendanceLog(Request $request, Employee $employee): RedirectResponse
    {
        TimekeepingEmployeeProfile::authorize($request->user(), 'update');

        $validator = Validator::make($request->all(), [
            'log_date' => ['required', 'date'],
            'log_time' => ['required', 'date_format:H:i'],
            'is_in' => ['required', 'boolean'],
            'view_tab' => ['nullable', 'string'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'day' => ['nullable', 'date'],
            'form_context' => ['required', 'string'],
        ]);

        $redirectExtras = $this->attendanceRedirectExtras($request);

        if ($validator->fails()) {
            return redirect()
                ->route(TimekeepingEmployeeProfile::routeName('index'), $this->employeeProfileRedirectParams($request, $employee, $redirectExtras))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $dateTime = Carbon::parse($validated['log_date'].' '.$validated['log_time'].':00');

        $this->attendanceLogService->create(
            $employee,
            $dateTime,
            (bool) $validated['is_in'],
            (int) $request->user()->id,
        );

        $redirectExtras['day'] = $validated['log_date'];
        $redirectExtras['year'] = (int) Carbon::parse($validated['log_date'])->year;
        $redirectExtras['month'] = (int) Carbon::parse($validated['log_date'])->month;

        return redirect()
            ->route(TimekeepingEmployeeProfile::routeName('index'), $this->employeeProfileRedirectParams($request, $employee, $redirectExtras))
            ->with('success', 'Attendance log added successfully.');
    }

    public function updateAttendanceLog(
        Request $request,
        Employee $employee,
        RawTimekeepingInandout $attendanceLog,
    ): RedirectResponse {
        TimekeepingEmployeeProfile::authorize($request->user(), 'update');

        if ((int) $attendanceLog->employee_id !== (int) $employee->employee_id) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'log_date' => ['required', 'date'],
            'log_time' => ['required', 'date_format:H:i'],
            'is_in' => ['required', 'boolean'],
            'attendance_page' => ['nullable', 'integer', 'min:1'],
            'view_tab' => ['nullable', 'string'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'day' => ['nullable', 'date'],
            'form_context' => ['required', 'string'],
        ]);

        $redirectExtras = $this->attendanceRedirectExtras($request);

        if ($validator->fails()) {
            return redirect()
                ->route(TimekeepingEmployeeProfile::routeName('index'), $this->employeeProfileRedirectParams($request, $employee, $redirectExtras))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $dateTime = Carbon::parse($validated['log_date'].' '.$validated['log_time'].':00');

        $this->attendanceLogService->update(
            $attendanceLog,
            $dateTime,
            (bool) $validated['is_in'],
            (int) $request->user()->id,
        );

        $redirectExtras['day'] = $validated['log_date'];
        $redirectExtras['year'] = (int) Carbon::parse($validated['log_date'])->year;
        $redirectExtras['month'] = (int) Carbon::parse($validated['log_date'])->month;

        return redirect()
            ->route(TimekeepingEmployeeProfile::routeName('index'), $this->employeeProfileRedirectParams($request, $employee, $redirectExtras))
            ->with('success', 'Attendance log updated successfully.');
    }

    public function destroyAttendanceLog(
        Request $request,
        Employee $employee,
        RawTimekeepingInandout $attendanceLog,
    ): RedirectResponse {
        TimekeepingEmployeeProfile::authorize($request->user(), 'update');

        if ((int) $attendanceLog->employee_id !== (int) $employee->employee_id) {
            abort(404);
        }

        $day = $attendanceLog->dt_datetime?->toDateString();
        $year = $attendanceLog->dt_datetime?->year;
        $month = $attendanceLog->dt_datetime?->month;

        $this->attendanceLogService->delete($attendanceLog, (int) $request->user()->id);

        $redirectExtras = $this->attendanceRedirectExtras($request);
        if ($day) {
            $redirectExtras['day'] = $day;
        }
        if ($year) {
            $redirectExtras['year'] = $year;
        }
        if ($month) {
            $redirectExtras['month'] = $month;
        }

        return redirect()
            ->route(TimekeepingEmployeeProfile::routeName('index'), $this->employeeProfileRedirectParams($request, $employee, $redirectExtras))
            ->with('success', 'Attendance log deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceRedirectExtras(Request $request): array
    {
        $viewTab = TimekeepingEmployeeProfile::normalizeSetupTab($request->input('view_tab', 'attendance'));

        return array_filter([
            'view_tab' => $viewTab,
            'attendance_page' => $request->input('attendance_page'),
            'date_from' => $viewTab === 'attendance' ? $request->input('date_from') : null,
            'date_to' => $viewTab === 'attendance' ? $request->input('date_to') : null,
            'year' => in_array($viewTab, ['attendance', 'calendar'], true) ? $request->input('year') : null,
            'month' => in_array($viewTab, ['attendance', 'calendar'], true) ? $request->input('month') : null,
            'day' => in_array($viewTab, ['attendance', 'calendar'], true) ? $request->input('day') : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function employeeProfileRedirectParams(Request $request, Employee $employee, array $extra = []): array
    {
        return array_merge([
            'view_employee' => $employee->employee_id,
            'search' => $request->input('search'),
            'page' => $request->input('page'),
        ], $extra);
    }
}
