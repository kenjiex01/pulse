<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\DeductionLoanPriority;
use App\Models\DeductionType;
use App\Models\LoanType;
use App\Models\PayrollCalendar;
use App\Models\PayrollSettingOther;
use App\Models\PayType;
use App\Services\PayrollCalendarGeneratorService;
use App\Services\PayrollCalendarScheduleService;
use App\Services\PayrollCalendarScopeService;
use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\PayrollCalendarModule;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollCalendarController extends Controller
{
    public function __construct(
        private readonly PayrollCalendarScheduleService $scheduleService,
        private readonly PayrollCalendarGeneratorService $generatorService,
        private readonly PayrollCalendarScopeService $scopeService,
    ) {}

    public function index(Request $request, string $payType): View
    {
        $payTypeSlug = PayrollCalendarModule::resolvePayTypeSlug($payType);
        $payTypeId = PayrollCalendarModule::payTypeIdFromSlug($payTypeSlug);
        PayrollCalendarModule::authorize($request->user(), 'view');

        $year = (int) $request->input('year', date('Y'));

        $periods = PayrollCalendar::query()
            ->with(['deductions', 'loans', 'colleges.college.campus', 'userTypes'])
            ->where('pay_type_id', $payTypeId)
            ->where('pay_year', $year)
            ->orderBy('pay_period')
            ->paginate(LiveTable::perPage($request, 15))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'tbl_payroll_calendar',
                description: 'Viewed '.PayrollCalendarModule::payTypeLabel($payTypeId).' pay periods for '.$year.' ('.$periods->total().' records)',
            );
        }

        $viewData = [
            'moduleTab' => 'calendar',
            'moduleTabs' => PayrollCalendarModule::MODULE_TABS,
            'payTypeSlug' => $payTypeSlug,
            'payTypeId' => $payTypeId,
            'payTypeTabs' => PayrollCalendarModule::payTypeTabs(),
            'year' => $year,
            'years' => PayrollCalendarModule::yearOptions($year),
            'periods' => $periods,
            'deductionTypes' => DeductionType::query()->orderBy('description')->get(),
            'loanTypes' => LoanType::query()->orderBy('description')->get(),
            'months' => PayrollCalendarModule::MONTHS,
            'collegeSelect' => $this->collegeSelectData(),
            'userTypeOptions' => PayrollCalendarModule::userTypeOptions(),
            'openCreate' => $request->boolean('create'),
            'openEditId' => old('edit_period_id', $request->input('edit')),
            'openViewId' => $request->input('view'),
        ];

        if ($request->ajax()) {
            return view('payroll.calendar._results', $viewData);
        }

        return view('payroll.calendar.index', $viewData);
    }

    public function priority(Request $request): View
    {
        PayrollCalendarModule::authorize($request->user(), 'view');

        $search = $request->string('search')->trim()->toString();
        $settings = PayrollSettingOther::settings();

        $priorities = DeductionLoanPriority::query()
            ->with(['deductionType', 'loanType'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->whereHas('deductionType', fn ($deductionQuery) => $deductionQuery->where('description', 'like', '%'.$search.'%'))
                        ->orWhereHas('loanType', fn ($loanQuery) => $loanQuery->where('description', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('priority')
            ->paginate(LiveTable::perPage($request, 15))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'tbl_deduction_loan_priority',
                description: 'Viewed deduction & loan priority list ('.$priorities->total().' records)',
            );
        }

        $viewData = [
            'moduleTab' => 'priority',
            'moduleTabs' => PayrollCalendarModule::MODULE_TABS,
            'priorities' => $priorities,
            'search' => $search,
            'settings' => $settings,
        ];

        if ($request->ajax()) {
            return view('payroll.calendar._priority-results', $viewData);
        }

        return view('payroll.calendar.priority', $viewData);
    }

    public function store(Request $request, string $payType): RedirectResponse
    {
        $payTypeSlug = PayrollCalendarModule::resolvePayTypeSlug($payType);
        $payTypeId = PayrollCalendarModule::payTypeIdFromSlug($payTypeSlug);
        PayrollCalendarModule::authorize($request->user(), 'add');

        $validated = $this->validatePeriod($request, $payTypeId);

        $period = DB::transaction(function () use ($validated, $payTypeId) {
            $period = PayrollCalendar::query()->create([
                'pay_type_id' => $payTypeId,
                'pay_year' => $validated['pay_year'],
                'pay_period' => $validated['pay_period'],
                'dt_from' => Carbon::parse($validated['date_from'])->startOfDay(),
                'dt_to' => Carbon::parse($validated['date_to'])->startOfDay(),
                'calendar_month' => $validated['calendar_month'] ?? (int) Carbon::parse($validated['date_from'])->format('n'),
                'is_regular_period' => ($validated['is_regular_period'] ?? false) ? true : null,
            ]);

            $this->scheduleService->attachDefaultSchedule($period);
            $this->scopeService->sync(
                $period,
                $validated['college_codes'],
                $validated['user_types'],
            );

            return $period;
        });

        SysLogService::record(
            action: 'create',
            table: 'tbl_payroll_calendar',
            recordId: $period->payroll_calendar_id,
            newValues: $period->fresh()->toArray(),
            description: 'Created '.PayrollCalendarModule::periodLabel($period),
        );

        return redirect()
            ->route(PayrollCalendarModule::routeName('pay-type'), [
                'payType' => $payTypeSlug,
                'year' => $validated['pay_year'],
            ])
            ->with('success', 'Pay period created successfully.');
    }

    public function update(Request $request, string $payType, PayrollCalendar $period): RedirectResponse
    {
        $payTypeSlug = PayrollCalendarModule::resolvePayTypeSlug($payType);
        $payTypeId = PayrollCalendarModule::payTypeIdFromSlug($payTypeSlug);
        PayrollCalendarModule::authorize($request->user(), 'update');
        $this->assertPeriodMatchesPayType($period, $payTypeId);

        $validated = $this->validatePeriod($request, $payTypeId, $period->payroll_calendar_id);
        $oldValues = $period->toArray();

        $period->update([
            'pay_year' => $validated['pay_year'],
            'pay_period' => $validated['pay_period'],
            'dt_from' => Carbon::parse($validated['date_from'])->startOfDay(),
            'dt_to' => Carbon::parse($validated['date_to'])->startOfDay(),
            'calendar_month' => $validated['calendar_month'] ?? (int) Carbon::parse($validated['date_from'])->format('n'),
            'is_regular_period' => ($validated['is_regular_period'] ?? false) ? true : null,
        ]);

        $this->scopeService->sync(
            $period,
            $validated['college_codes'],
            $validated['user_types'],
        );

        SysLogService::record(
            action: 'update',
            table: 'tbl_payroll_calendar',
            recordId: $period->payroll_calendar_id,
            oldValues: $oldValues,
            newValues: $period->fresh()->toArray(),
            description: 'Updated '.PayrollCalendarModule::periodLabel($period),
        );

        return redirect()
            ->route(PayrollCalendarModule::routeName('pay-type'), [
                'payType' => $payTypeSlug,
                'year' => $validated['pay_year'],
                'period' => $period->payroll_calendar_id,
            ])
            ->with('success', 'Pay period updated successfully.');
    }

    public function destroy(Request $request, string $payType, PayrollCalendar $period): RedirectResponse
    {
        $payTypeSlug = PayrollCalendarModule::resolvePayTypeSlug($payType);
        $payTypeId = PayrollCalendarModule::payTypeIdFromSlug($payTypeSlug);
        PayrollCalendarModule::authorize($request->user(), 'delete');
        $this->assertPeriodMatchesPayType($period, $payTypeId);

        $year = $period->pay_year;
        $oldValues = $period->toArray();
        $label = PayrollCalendarModule::periodLabel($period);

        DB::transaction(function () use ($period): void {
            $period->deductions()->delete();
            $period->loans()->delete();
            $period->colleges()->delete();
            $period->userTypes()->delete();
            $period->delete();
        });

        SysLogService::record(
            action: 'delete',
            table: 'tbl_payroll_calendar',
            recordId: $period->payroll_calendar_id,
            oldValues: $oldValues,
            description: 'Deleted '.$label,
        );

        return redirect()
            ->route(PayrollCalendarModule::routeName('pay-type'), [
                'payType' => $payTypeSlug,
                'year' => $year,
            ])
            ->with('success', 'Pay period deleted successfully.');
    }

    public function bulkDestroy(Request $request, string $payType): RedirectResponse
    {
        $payTypeSlug = PayrollCalendarModule::resolvePayTypeSlug($payType);
        $payTypeId = PayrollCalendarModule::payTypeIdFromSlug($payTypeSlug);
        PayrollCalendarModule::authorize($request->user(), 'delete');

        $validated = $request->validate([
            'period_ids' => ['required', 'array', 'min:1'],
            'period_ids.*' => ['integer', Rule::exists('tbl_payroll_calendar', 'payroll_calendar_id')],
            'year' => ['required', 'integer'],
        ]);

        $periods = PayrollCalendar::query()
            ->where('pay_type_id', $payTypeId)
            ->whereIn('payroll_calendar_id', $validated['period_ids'])
            ->get();

        if ($periods->isEmpty()) {
            return back()->with('error', 'Please select at least one pay period to delete.');
        }

        $deleted = 0;

        DB::transaction(function () use ($periods, &$deleted): void {
            foreach ($periods as $period) {
                $oldValues = $period->toArray();
                $label = PayrollCalendarModule::periodLabel($period);
                $period->deductions()->delete();
                $period->loans()->delete();
                $period->colleges()->delete();
                $period->userTypes()->delete();
                $period->delete();

                SysLogService::record(
                    action: 'delete',
                    table: 'tbl_payroll_calendar',
                    recordId: $period->payroll_calendar_id,
                    oldValues: $oldValues,
                    description: 'Deleted '.$label,
                );

                $deleted++;
            }
        });

        return redirect()
            ->route(PayrollCalendarModule::routeName('pay-type'), [
                'payType' => $payTypeSlug,
                'year' => $validated['year'],
            ])
            ->with('success', $deleted.' pay period(s) deleted successfully.');
    }

    public function saveSchedule(Request $request, string $payType, PayrollCalendar $period): RedirectResponse
    {
        $payTypeSlug = PayrollCalendarModule::resolvePayTypeSlug($payType);
        $payTypeId = PayrollCalendarModule::payTypeIdFromSlug($payTypeSlug);
        PayrollCalendarModule::authorize($request->user(), 'update');
        $this->assertPeriodMatchesPayType($period, $payTypeId);

        $validated = $request->validate([
            'deduction_type_ids' => ['nullable', 'array'],
            'deduction_type_ids.*' => ['integer', Rule::exists('tbl_deduction_types', 'deduction_type_id')],
            'loan_type_ids' => ['nullable', 'array'],
            'loan_type_ids.*' => ['integer', Rule::exists('tbl_loan_types', 'loan_type_id')],
        ]);

        $this->scheduleService->assertExclusivePhilhealth($validated['deduction_type_ids'] ?? []);

        $this->scheduleService->sync(
            $period,
            $validated['deduction_type_ids'] ?? [],
            $validated['loan_type_ids'] ?? [],
        );

        SysLogService::record(
            action: 'update',
            table: 'tbl_payroll_calendar',
            recordId: $period->payroll_calendar_id,
            description: 'Updated loan/deduction schedule for '.PayrollCalendarModule::periodLabel($period),
        );

        return redirect()
            ->route(PayrollCalendarModule::routeName('pay-type'), [
                'payType' => $payTypeSlug,
                'year' => $period->pay_year,
                'view' => $period->payroll_calendar_id,
            ])
            ->with('success', 'Loan and deduction schedule saved.');
    }

    public function autofill(Request $request, string $payType): RedirectResponse
    {
        $payTypeSlug = PayrollCalendarModule::resolvePayTypeSlug($payType);
        $payTypeId = PayrollCalendarModule::payTypeIdFromSlug($payTypeSlug);
        PayrollCalendarModule::authorize($request->user(), 'add');

        $validated = $this->validateAutofill($request, $payTypeId);
        $created = $this->generatorService->generate($payTypeId, $validated);

        foreach ($created as $period) {
            SysLogService::record(
                action: 'create',
                table: 'tbl_payroll_calendar',
                recordId: $period->payroll_calendar_id,
                newValues: $period->toArray(),
                description: 'Auto-filled '.PayrollCalendarModule::periodLabel($period),
            );
        }

        $message = $created->isEmpty()
            ? 'No pay periods were generated.'
            : $created->count().' pay period(s) generated successfully.';

        return redirect()
            ->route(PayrollCalendarModule::routeName('pay-type'), [
                'payType' => $payTypeSlug,
                'year' => $validated['pay_year'],
                'autofill' => null,
            ])
            ->with($created->isEmpty() ? 'error' : 'success', $message);
    }

    public function movePriority(Request $request, DeductionLoanPriority $priority): RedirectResponse
    {
        PayrollCalendarModule::authorize($request->user(), 'update');

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $currentPriority = (int) $priority->priority;
        $maxPriority = (int) DeductionLoanPriority::query()->max('priority');
        $targetPriority = $validated['direction'] === 'up'
            ? max(1, $currentPriority - 1)
            : min($maxPriority, $currentPriority + 1);

        if ($targetPriority === $currentPriority) {
            return back();
        }

        DB::transaction(function () use ($priority, $currentPriority, $targetPriority): void {
            $swap = DeductionLoanPriority::query()->where('priority', $targetPriority)->first();

            if ($swap) {
                $swap->update(['priority' => $currentPriority]);
            }

            $priority->update(['priority' => $targetPriority]);
        });

        SysLogService::record(
            action: 'update',
            table: 'tbl_deduction_loan_priority',
            recordId: $priority->deduction_loan_priority_id,
            description: 'Updated priority of '.$priority->descriptionLabel().' to "'.$targetPriority.'"',
        );

        return back()->with('success', 'Priority updated.');
    }

    public function enablePriority(Request $request): RedirectResponse
    {
        PayrollCalendarModule::authorize($request->user(), 'update');

        $settings = PayrollSettingOther::settings();
        $settings->update(['is_deduction_loan_priority_enabled' => true]);

        SysLogService::record(
            action: 'update',
            table: 'tbl_payroll_setting_others',
            recordId: $settings->payroll_setting_other_id,
            description: 'Enabled deduction & loan prioritization',
        );

        return back()->with('success', 'Deduction & loan prioritization enabled.');
    }

    private function assertPeriodMatchesPayType(PayrollCalendar $period, int $payTypeId): void
    {
        if ((int) $period->pay_type_id !== $payTypeId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePeriod(Request $request, int $payTypeId, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'pay_year' => ['required', 'integer', 'min:1000', 'max:9999'],
            'pay_period' => [
                'required',
                'integer',
                'min:1',
                'max:999',
                Rule::unique('tbl_payroll_calendar', 'pay_period')
                    ->where(fn ($query) => $query
                        ->where('pay_type_id', $payTypeId)
                        ->where('pay_year', $request->input('pay_year')))
                    ->ignore($ignoreId, 'payroll_calendar_id'),
            ],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'calendar_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_regular_period' => ['nullable', 'boolean'],
            'college_codes' => ['nullable', 'array'],
            'college_codes.*' => [
                'string',
                'max:20',
                Rule::exists('tbl_colleges', 'college_code')->whereNull('deleted_at'),
            ],
            'user_types' => ['required', 'array', 'min:1'],
            'user_types.*' => ['string', Rule::in(PayrollCalendarModule::userTypeKeys())],
        ]);

        $validated['college_codes'] = collect($validated['college_codes'] ?? [])
            ->map(fn ($code) => (string) $code)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (PayrollCalendarModule::requiresColleges($validated['user_types']) && $validated['college_codes'] === []) {
            throw ValidationException::withMessages([
                'college_codes' => 'Please select at least one college.',
            ]);
        }

        return $validated;
    }

    /**
     * @return array{options: array<string, string>}
     */
    private function collegeSelectData(): array
    {
        $options = [];

        College::query()
            ->active()
            ->orderBy('college_code')
            ->orderBy('college_name')
            ->get()
            ->unique('college_code')
            ->each(function (College $college) use (&$options): void {
                $options[$college->college_code] = trim($college->college_code.' — '.$college->college_name);
            });

        return [
            'options' => $options,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAutofill(Request $request, int $payTypeId): array
    {
        $baseRules = [
            'pay_year' => ['required', 'integer', 'min:1000', 'max:9999'],
            'date_from' => ['required', 'date'],
            'is_regular_period' => ['nullable', 'boolean'],
            'range_mode' => ['nullable', Rule::in(['date_to', 'occurrences'])],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'occurrences' => ['nullable', 'integer', 'min:1'],
        ];

        $rules = match ($payTypeId) {
            PayType::DAILY => $baseRules,
            PayType::WEEKLY => array_merge($baseRules, [
                'week_day' => ['required', 'integer', 'min:1', 'max:7'],
            ]),
            PayType::SEMI_MONTHLY => array_merge($baseRules, [
                'frequency_day_1' => ['required', 'integer', 'min:1', 'max:30'],
                'frequency_day_2' => ['nullable', Rule::in(array_merge(range(1, 30), ['last']))],
            ]),
            PayType::MONTHLY => array_merge($baseRules, [
                'frequency_day' => ['nullable', Rule::in(array_merge(range(1, 30), ['last']))],
            ]),
            default => $baseRules,
        };

        $validated = $request->validate($rules);

        if ($payTypeId === PayType::SEMI_MONTHLY) {
            if ($validated['frequency_day_2'] === 'last') {
                $validated['frequency_day_2'] = null;
            }
        }

        if ($payTypeId === PayType::MONTHLY && ($validated['frequency_day'] ?? null) === 'last') {
            $validated['frequency_day'] = null;
        }

        if ($payTypeId === PayType::DAILY && blank($validated['date_to'] ?? null) && blank($validated['occurrences'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' => 'Provide either Date To or Occurrences.',
            ]);
        }

        if (in_array($payTypeId, [PayType::WEEKLY, PayType::SEMI_MONTHLY, PayType::MONTHLY], true)) {
            $mode = $validated['range_mode'] ?? 'date_to';

            if ($mode === 'date_to' && blank($validated['date_to'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'date_to' => 'Date To is required.',
                ]);
            }

            if ($mode === 'occurrences' && blank($validated['occurrences'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'occurrences' => 'Occurrences is required.',
                ]);
            }
        }

        return $validated;
    }
}
