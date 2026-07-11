<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Requests\Employee\WizardCampusRequest;
use App\Http\Requests\Employee\WizardDetailsRequest;
use App\Http\Requests\Employee\WizardReviewRequest;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\Role;
use App\Services\EmployeeCampusAssignmentSync;
use App\Services\EmployeeEmploymentSync;
use App\Services\EmployeeSalarySync;
use App\Services\EmployeeWizardSession;
use App\Services\SysLogService;
use App\Support\LiveTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $compliance = $request->string('compliance')->toString();

        $baseQuery = Employee::query()
            ->with([
                'campus',
                'employmentInformations.salary.payType',
                'employmentInformations.salary.basicComputation',
                'employmentInformations.salary.rateGroup',
                'employmentInformations.salary.ndRateGroup',
                'employmentInformations.salary.incomes.incomeType',
                'employmentInformations.salary.deductions.deductionType',
            ])
            ->search($search)
            ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('employment_status', $status))
            ->when($compliance !== '' && $compliance !== 'all', fn ($query) => $query->where('compliance_status', $compliance));

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('employment_status', Employee::STATUS_ACTIVE)->count(),
            'inactive' => (clone $baseQuery)->where('employment_status', Employee::STATUS_INACTIVE)->count(),
            'pending_compliance' => (clone $baseQuery)->where('compliance_status', Employee::COMPLIANCE_PENDING)->count(),
        ];

        $employees = (clone $baseQuery)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(LiveTable::perPage($request, 25))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'tbl_employees',
                description: 'Viewed employees list ('.$employees->total().' records)',
            );
        }

        $viewData = compact('employees', 'stats', 'search', 'status', 'compliance');

        if ($request->ajax()) {
            return view('employees._results', $viewData);
        }

        return view('employees.index', $viewData);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $step = max(0, min(2, (int) $request->query('step', 0)));
        $campusId = EmployeeWizardSession::campusId();
        $wizardData = EmployeeWizardSession::data();

        if ($step > 0 && ! $campusId) {
            return redirect()->route('employees.create', ['step' => 0]);
        }

        if ($step > 1 && blank($wizardData['first_name'] ?? null)) {
            return redirect()->route('employees.create', ['step' => 1]);
        }

        $campuses = Campus::query()
            ->where('is_active', true)
            ->orderBy('campus_name')
            ->get();

        $selectedCampus = $campusId
            ? $campuses->firstWhere('campus_id', $campusId)
            : null;

        $employee = new Employee(array_merge([
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
            'employee_number' => Employee::generateEmployeeNumber(),
            'extended_profile' => [],
            'campus_id' => $campusId,
            'is_hybrid' => false,
        ], $wizardData));

        if ($selectedCampus) {
            $employee->setRelation('campus', $selectedCampus);
        }

        $wizardEmploymentRecords = $wizardData['employment_informations'] ?? [];
        if ($wizardEmploymentRecords !== []) {
            $employee->setRelation(
                'employmentInformations',
                collect($wizardEmploymentRecords)->map(fn (array $record) => new \App\Models\EmployeeEmploymentInformation($record)),
            );
        } elseif (! $employee->relationLoaded('employmentInformations')) {
            $employee->setRelation('employmentInformations', collect());
        }

        if (! empty($wizardData['campus_assignments'])) {
            $employee->setRelation(
                'campusAssignments',
                collect($wizardData['campus_assignments'])->map(fn (array $record) => new \App\Models\EmployeeCampusAssignment($record)),
            );
        } elseif (! $employee->relationLoaded('campusAssignments')) {
            $employee->setRelation('campusAssignments', collect());
        }

        $roles = Role::query()->orderBy('name')->get();

        SysLogService::record(
            action: 'read',
            table: 'tbl_employees',
            description: 'Opened add employee wizard (step '.$step.')',
        );

        return view('employees.create', compact('step', 'campuses', 'selectedCampus', 'employee', 'roles', 'wizardData'));
    }

    public function wizardCampus(WizardCampusRequest $request): RedirectResponse
    {
        EmployeeWizardSession::putCampus((int) $request->validated('campus_id'));

        return redirect()
            ->route('employees.create', ['step' => 1])
            ->with('success', 'Campus selected. Continue with employee details.');
    }

    public function wizardDetails(WizardDetailsRequest $request): RedirectResponse
    {
        if (! EmployeeWizardSession::campusId()) {
            return redirect()->route('employees.create', ['step' => 0]);
        }

        EmployeeWizardSession::mergeData($request->validated());

        return redirect()
            ->route('employees.create', ['step' => 2])
            ->with('success', 'Details saved. Review and submit.');
    }

    public function wizardCancel(): RedirectResponse
    {
        EmployeeWizardSession::clear();

        return redirect()
            ->route('employees.index')
            ->with('success', 'Add employee cancelled.');
    }

    public function store(WizardReviewRequest $request): RedirectResponse
    {
        $campusId = EmployeeWizardSession::campusId();
        $data = EmployeeWizardSession::data();

        if (! $campusId || blank($data['first_name'] ?? null)) {
            return redirect()->route('employees.create', ['step' => 0]);
        }

        $campus = Campus::query()->findOrFail($campusId);

        $extended = $data['extended_profile'] ?? [];
        $extended['role_id'] = (int) $request->validated('role_id');
        $data['campus_id'] = $campusId;
        $data['campus'] = $campus->campus_code;
        $data['extended_profile'] = $extended;
        $data['is_active'] = ($data['employment_status'] ?? Employee::STATUS_ACTIVE) === Employee::STATUS_ACTIVE;
        $employmentInformations = EmployeeEmploymentSync::normalizeRecords(
            (array) ($data['employment_informations'] ?? []),
            (bool) ($data['is_hybrid'] ?? false),
        );
        $employeeSalaries = EmployeeSalarySync::normalizeRecords(
            (array) ($data['employee_salaries'] ?? []),
            (bool) ($data['is_hybrid'] ?? false),
        );
        $campusAssignments = EmployeeCampusAssignmentSync::normalizeRecords(
            (array) ($data['campus_assignments'] ?? []),
        );
        unset($data['employment_informations'], $data['employee_salaries'], $data['campus_assignments']);

        if ($campusAssignments !== []) {
            $primary = $campusAssignments[0];
            $primaryCampus = Campus::query()->find((int) $primary['campus_id']);
            $data['campus_id'] = (int) $primary['campus_id'];
            $data['campus'] = $primaryCampus?->campus_code;
            $data['college'] = $primary['college'] ?? null;
            $data['department'] = $primary['department'] ?? null;
            $data['program'] = $primary['program'] ?? null;
        }

        $data['employee_number'] = trim((string) ($data['employee_number'] ?? ''));

        try {
            Validator::make(
                ['employee_number' => $data['employee_number']],
                [
                    'employee_number' => [
                        'required',
                        'string',
                        'max:50',
                        Rule::unique(Employee::class, 'employee_number')->whereNull('deleted_at'),
                    ],
                ],
                [
                    'employee_number.unique' => 'This employee number is already assigned to another employee.',
                ],
            )->validate();
        } catch (ValidationException $exception) {
            return redirect()
                ->route('employees.create', ['step' => 1])
                ->withErrors($exception->errors())
                ->withInput($data);
        }

        $employee = Employee::query()->create($data);

        DB::transaction(function () use ($employee, $employmentInformations, $employeeSalaries, $campusAssignments, $data): void {
            EmployeeEmploymentSync::sync($employee, $employmentInformations);
            EmployeeCampusAssignmentSync::sync($employee, $campusAssignments);
            EmployeeSalarySync::sync($employee, $employeeSalaries, (bool) ($data['is_hybrid'] ?? false));
        });

        EmployeeWizardSession::clear();

        SysLogService::record(
            action: 'create',
            table: 'tbl_employees',
            recordId: $employee->employee_id,
            newValues: $employee->fresh()->logSnapshot(),
            description: 'Created employee via wizard: '.$employee->employee_number,
        );

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);
        $employee->load([
            'campus',
            'campusAssignments.campus',
            'employmentInformations.salary.payType',
            'employmentInformations.salary.basicComputation',
            'employmentInformations.salary.rateGroup',
            'employmentInformations.salary.ndRateGroup',
            'employmentInformations.salary.incomes.incomeType',
            'employmentInformations.salary.deductions.deductionType',
        ]);

        SysLogService::record(
            action: 'read',
            table: 'tbl_employees',
            recordId: $employee->employee_id,
            description: 'Viewed employee: '.$employee->employee_number,
        );

        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);
        $employee->load([
            'campus',
            'campusAssignments.campus',
            'employmentInformations.salary.payType',
            'employmentInformations.salary.basicComputation',
            'employmentInformations.salary.rateGroup',
            'employmentInformations.salary.ndRateGroup',
            'employmentInformations.salary.incomes.incomeType',
            'employmentInformations.salary.deductions.deductionType',
        ]);

        SysLogService::record(
            action: 'read',
            table: 'tbl_employees',
            recordId: $employee->employee_id,
            description: 'Opened edit page for employee: '.$employee->employee_number,
        );

        $campuses = Campus::query()
            ->where('is_active', true)
            ->orderBy('campus_name')
            ->get();

        return view('employees.edit', compact('employee', 'campuses'));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $oldValues = $employee->logSnapshot();
        $payload = $request->validated();

        if (! empty($payload['campus_id'])) {
            $campus = Campus::query()->find($payload['campus_id']);
            $payload['campus'] = $campus?->campus_code;
        }

        $employmentInformations = $request->employmentInformations();
        $employeeSalaries = $request->employeeSalaries();
        $campusAssignments = $request->campusAssignments();
        $isHybrid = (bool) ($payload['is_hybrid'] ?? false);

        DB::transaction(function () use ($employee, $payload, $employmentInformations, $employeeSalaries, $campusAssignments, $isHybrid): void {
            $employee->update($payload);
            EmployeeEmploymentSync::sync($employee, $employmentInformations);
            EmployeeCampusAssignmentSync::sync($employee, $campusAssignments);
            EmployeeSalarySync::sync($employee, $employeeSalaries, $isHybrid);
        });

        $employee->refresh();

        SysLogService::record(
            action: 'update',
            table: 'tbl_employees',
            recordId: $employee->employee_id,
            oldValues: $oldValues,
            newValues: $employee->fresh()->logSnapshot(),
            description: 'Updated employee: '.$employee->employee_number,
        );

        return redirect()
            ->route('employees.edit', [
                'employee' => $employee,
                'tab' => $request->input('active_tab', 'personal'),
            ])
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $oldValues = $employee->logSnapshot();
        $employeeNumber = $employee->employee_number;
        $employeeId = $employee->employee_id;

        $employee->delete();

        SysLogService::record(
            action: 'delete',
            table: 'tbl_employees',
            recordId: $employeeId,
            oldValues: $oldValues,
            description: 'Deleted employee: '.$employeeNumber,
        );

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee deleted successfully.');
    }
}
