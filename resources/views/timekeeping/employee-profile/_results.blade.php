@php
    use App\Support\TimekeepingEmployeeProfile;
@endphp

<div data-live-table-total-update data-total="{{ $employees->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[960px]">
            <thead>
                <tr>
                    <th>Employee No.</th>
                    <th>First Name</th>
                    <th>Middle Name</th>
                    <th>Last Name</th>
                    <th>Holiday Group</th>
                    <th>Shift Code</th>
                    <th>Policy Group</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    @php
                        $setup = $employee->timekeepingSetup;
                        $isComplete = TimekeepingEmployeeProfile::isSetupComplete($employee);
                    @endphp
                    <tr>
                        <td class="font-medium text-gray-900">{{ $employee->employee_number }}</td>
                        <td class="text-gray-700">{{ $employee->first_name }}</td>
                        <td class="text-gray-600">{{ $employee->middle_name ?: '—' }}</td>
                        <td class="text-gray-700">{{ $employee->last_name }}</td>
                        <td class="text-gray-600">{{ $setup?->holidayGroup?->description ?? '—' }}</td>
                        <td class="text-gray-600">{{ $setup?->shiftCode?->description ?? '—' }}</td>
                        <td class="text-gray-600">{{ $setup?->policy?->policy_name ?? '—' }}</td>
                        <td>
                            @if ($isComplete)
                                <span class="badge-success">Setup Complete</span>
                            @else
                                <span class="badge-muted">Needs Setup</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" data-modal-open="employee-profile-view-{{ $employee->employee_id }}" class="btn-icon" title="View">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                @can('employee-profile.update')
                                    <button type="button" data-modal-open="employee-profile-setup-{{ $employee->employee_id }}" class="btn-icon" title="{{ $isComplete ? 'Edit Setup' : 'Setup' }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-sm text-gray-500">No employees found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="datatable-skolaris-pagination mt-4">
    @include('partials.data-table-pagination', ['paginator' => $employees])
</div>

<div data-live-table-modals>
    @foreach ($employees as $employee)
        @include('partials.modal', [
            'id' => 'employee-profile-view-'.$employee->employee_id,
            'title' => 'Employee Profile',
            'description' => $employee->full_name.' ('.$employee->employee_number.')',
            'open' => (string) ($openViewEmployeeId ?? '') === (string) $employee->employee_id,
            'panelClass' => 'max-w-[96vw]',
            'body' => view('timekeeping.employee-profile._show-content', [
                'employee' => $employee,
                'formOptions' => $formOptions,
            ])->render(),
        ])

        @can('employee-profile.update')
            @include('partials.modal', [
                'id' => 'employee-profile-setup-'.$employee->employee_id,
                'title' => $employee->hasTimekeepingSetup() ? 'Edit Timekeeping Settings' : 'Timekeeping Setup',
                'description' => $employee->full_name.' ('.$employee->employee_number.')',
                'open' => (string) ($openSetupEmployeeId ?? '') === (string) $employee->employee_id
                    || (old('form_context') === 'setup-employee-'.$employee->employee_id && $errors->any()),
                'panelClass' => 'max-w-[96vw]',
                'body' => view('timekeeping.employee-profile._setup-form', [
                    'employee' => $employee,
                    'formOptions' => $formOptions,
                ])->render(),
            ])
        @endcan
    @endforeach
</div>
