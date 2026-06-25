@php
    use App\Support\EmployeeApprovalSettings;
    use App\Support\TimekeepingEmployeeProfile;

    $formTypes = EmployeeApprovalSettings::formTypes();
    $selectedFormTypeId = (int) request('form_type_id', 0);
@endphp

<div
    class="space-y-5"
    data-employee-profile-approval-root
    data-routes-url="{{ route(TimekeepingEmployeeProfile::routeName('approval-routes'), $employee->employee_id) }}"
>
    <p class="text-sm text-gray-600">
        Please fill-up all the required (<span class="text-red-600">*</span>) fields of the form.
        Please make sure that all entries are correct before saving.
    </p>

    <div class="grid gap-4 sm:grid-cols-[140px_1fr] sm:items-center">
        <label for="employee-profile-form-type-{{ $employee->employee_id }}" class="text-sm font-medium text-gray-700">
            Form Type <span class="text-red-600">*</span>
        </label>
        <select
            id="employee-profile-form-type-{{ $employee->employee_id }}"
            class="form-input max-w-xs"
            data-employee-profile-form-type
        >
            <option value="0" @selected($selectedFormTypeId === 0)>All Form Types</option>
            @foreach ($formTypes as $formType)
                <option
                    value="{{ $formType->user_request_type_id }}"
                    @selected($selectedFormTypeId === (int) $formType->user_request_type_id)
                >{{ $formType->user_request_type }}</option>
            @endforeach
        </select>
    </div>

    <div data-employee-profile-approval-routes>
        @include('timekeeping.employee-profile._tab-approval-routes', [
            'employee' => $employee,
            'formTypeId' => $selectedFormTypeId,
            'steps' => EmployeeApprovalSettings::stepsFor($employee, $selectedFormTypeId),
        ])
    </div>
</div>
