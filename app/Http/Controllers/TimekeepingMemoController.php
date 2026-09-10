<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\SysLogService;
use App\Services\TimekeepingMemoAttendanceService;
use App\Services\TimekeepingMemoPreviewService;
use App\Services\TimekeepingMemoSendService;
use App\Support\LiveTable;
use App\Support\TimekeepingMemo as TimekeepingMemoSupport;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TimekeepingMemoController extends Controller
{
    public function __construct(
        private readonly TimekeepingMemoAttendanceService $attendanceService,
        private readonly TimekeepingMemoSendService $sendService,
        private readonly TimekeepingMemoPreviewService $previewService,
    ) {}

    public function index(Request $request): View
    {
        TimekeepingMemoSupport::authorize($request->user(), 'view');

        $filters = $this->filtersFromRequest($request);
        $search = $request->string('search')->trim()->toString();
        $rows = collect();
        $paginator = null;

        if ($filters['applied']) {
            $rows = $this->buildRows($filters, $search);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = LiveTable::perPage($request);
            $total = $rows->count();
            $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        }

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'tbl_timekeeping_memo_send_logs',
                description: 'Opened Memo list',
            );
        }

        $viewData = [
            'memoFilters' => $filters,
            'search' => $search,
            'rows' => $paginator?->items() ?? [],
            'paginator' => $paginator,
            'violationTypeOptions' => TimekeepingMemoSupport::violationTypeOptions(),
            'openViewEmployeeId' => $request->input('view_employee'),
        ];

        if ($request->ajax()) {
            return view('timekeeping.memo._results', $viewData);
        }

        return view('timekeeping.memo.index', $viewData);
    }

    public function details(Request $request, Employee $employee): View
    {
        TimekeepingMemoSupport::authorize($request->user(), 'view');

        $filters = $this->filtersFromRequest($request);
        abort_unless($filters['applied'], 422, 'Apply filters first.');

        $days = $this->attendanceService->violationDaysForEmployee(
            $employee,
            $filters['date_from'],
            $filters['date_to'],
            $filters['violation_type'],
        );

        return view('timekeeping.memo._details-modal', [
            'employee' => $employee,
            'memoFilters' => $filters,
            'days' => $days,
            'campusLabel' => $this->attendanceService->campusLabel($employee),
        ]);
    }

    public function preview(Request $request, Employee $employee): View
    {
        TimekeepingMemoSupport::authorize($request->user(), 'view');

        $filters = $this->filtersFromRequest($request);
        abort_unless($filters['applied'], 422, 'Apply filters first.');

        $workDates = $request->input('work_dates');
        if (! is_array($workDates)) {
            $workDates = null;
        }

        try {
            $preview = $this->previewService->buildForEmployee(
                $employee,
                $filters['date_from'],
                $filters['date_to'],
                $filters['violation_type'],
                $workDates,
            );
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        SysLogService::record(
            action: 'read',
            table: 'tbl_timekeeping_memo_send_logs',
            recordId: $employee->employee_id,
            description: 'Previewed memo for employee #'.$employee->employee_id,
        );

        return view('timekeeping.memo._preview-modal', [
            'employee' => $employee,
            'memoFilters' => $filters,
            'preview' => $preview,
            'campusLabel' => $this->attendanceService->campusLabel($employee),
        ]);
    }

    public function previewHtml(Request $request, Employee $employee): \Symfony\Component\HttpFoundation\Response
    {
        TimekeepingMemoSupport::authorize($request->user(), 'view');

        $filters = $this->filtersFromRequest($request);
        abort_unless($filters['applied'], 422, 'Apply filters first.');

        $workDates = $request->input('work_dates');
        if (! is_array($workDates)) {
            $workDates = null;
        }

        try {
            $html = $this->previewService->renderHtmlForEmployee(
                $employee,
                $filters['date_from'],
                $filters['date_to'],
                $filters['violation_type'],
                $workDates,
            );
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function previewPdf(Request $request, Employee $employee): \Symfony\Component\HttpFoundation\Response
    {
        TimekeepingMemoSupport::authorize($request->user(), 'view');

        $filters = $this->filtersFromRequest($request);
        abort_unless($filters['applied'], 422, 'Apply filters first.');

        $workDates = $request->input('work_dates');
        if (! is_array($workDates)) {
            $workDates = null;
        }

        try {
            $pdf = $this->previewService->renderPdfForEmployee(
                $employee,
                $filters['date_from'],
                $filters['date_to'],
                $filters['violation_type'],
                $workDates,
            );
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="memo-preview.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function send(Request $request, Employee $employee): RedirectResponse|JsonResponse
    {
        TimekeepingMemoSupport::authorize($request->user(), 'update');

        $filters = $this->filtersFromRequest($request);
        abort_unless($filters['applied'], 422, 'Apply filters first.');

        $validated = $request->validate([
            'work_dates' => ['nullable', 'array'],
            'work_dates.*' => ['date'],
        ]);

        try {
            $result = $this->sendService->sendForEmployee(
                $employee,
                $filters['date_from'],
                $filters['date_to'],
                $filters['violation_type'],
                $validated['work_dates'] ?? null,
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }

            return back()->with('error', $exception->getMessage());
        }

        SysLogService::record(
            action: 'create',
            table: 'tbl_timekeeping_memo_send_logs',
            recordId: $result['submission_id'],
            description: 'Sent memo to employee #'.$employee->employee_id.' ('.count($result['sent_dates']).' day(s))',
        );

        $message = 'Memo sent for '.count($result['sent_dates']).' day(s).';

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()
            ->route(TimekeepingMemoSupport::routeName('index'), $request->only(['date_from', 'date_to', 'violation_type', 'min_count', 'search']))
            ->with('success', $message);
    }

    public function batchSend(Request $request): RedirectResponse|JsonResponse
    {
        @set_time_limit(300);

        TimekeepingMemoSupport::authorize($request->user(), 'update');

        $filters = $this->filtersFromRequest($request);
        abort_unless($filters['applied'], 422, 'Apply filters first.');

        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:tbl_employees,employee_id'],
        ]);

        $sent = 0;
        $errors = [];

        foreach ($validated['employee_ids'] as $employeeId) {
            $employee = Employee::query()->find($employeeId);
            if ($employee === null) {
                continue;
            }

            try {
                $this->sendService->sendForEmployee(
                    $employee,
                    $filters['date_from'],
                    $filters['date_to'],
                    $filters['violation_type'],
                    null,
                    $request->user(),
                );
                $sent++;
            } catch (RuntimeException $exception) {
                $errors[] = trim($employee->full_name).': '.$exception->getMessage();
            }
        }

        SysLogService::record(
            action: 'create',
            table: 'tbl_timekeeping_memo_send_logs',
            description: 'Batch sent memos to '.$sent.' employee(s)',
        );

        if ($sent === 0) {
            $message = $errors[0] ?? 'No memos were sent.';

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $message = "Memo sent to {$sent} employee(s).";
        if ($errors !== []) {
            $message .= ' Some rows failed: '.implode(' ', array_slice($errors, 0, 3));
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()
            ->route(TimekeepingMemoSupport::routeName('index'), $request->only(['date_from', 'date_to', 'violation_type', 'min_count', 'search']))
            ->with('success', $message);
    }

    /**
     * @return array{
     *     applied: bool,
     *     date_from: string,
     *     date_to: string,
     *     violation_type: string,
     *     min_count: int
     * }
     */
    private function filtersFromRequest(Request $request): array
    {
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();
        $violationType = $this->attendanceService->normalizeViolationType($request->input('violation_type'));
        $minCount = max(1, (int) $request->input('min_count', 1));

        $applied = $dateFrom !== '' && $dateTo !== '';

        if ($applied) {
            $from = CarbonImmutable::parse($dateFrom);
            $to = CarbonImmutable::parse($dateTo);
            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }
            $dateFrom = $from->toDateString();
            $dateTo = $to->toDateString();
        }

        return [
            'applied' => $applied,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'violation_type' => $violationType,
            'min_count' => $minCount,
        ];
    }

    /**
     * @param  array{applied: bool, date_from: string, date_to: string, violation_type: string, min_count: int}  $filters
     * @return \Illuminate\Support\Collection<int, array{employee: Employee, campus: string, count: int}>
     */
    private function buildRows(array $filters, string $search)
    {
        $query = TimekeepingMemoSupport::employeeQuery();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('employee_number', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw("TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.$search.'%']);
            });
        }

        return $query->get()
            ->map(function (Employee $employee) use ($filters) {
                $count = $this->attendanceService->violationCountForEmployee(
                    $employee,
                    $filters['date_from'],
                    $filters['date_to'],
                    $filters['violation_type'],
                );

                return [
                    'employee' => $employee,
                    'campus' => $this->attendanceService->campusLabel($employee),
                    'count' => $count,
                ];
            })
            ->filter(fn (array $row) => $row['count'] >= $filters['min_count'])
            ->sortBy(fn (array $row) => strtolower(trim($row['employee']->full_name)))
            ->values();
    }
}
