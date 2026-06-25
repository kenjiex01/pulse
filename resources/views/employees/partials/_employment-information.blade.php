@php
    $isHybridChecked = (bool) old(
        'is_hybrid',
        request()->has('is_hybrid') ? request()->boolean('is_hybrid') : ($employee->is_hybrid ?? false),
    );
    $employmentRecords = old('employment_informations');

    if ($employmentRecords === null) {
        $employmentRecords = $employee->employmentInformations
            ->map(fn ($info) => [
                'user_type' => $info->user_type,
                'position' => $info->position,
                'designation' => $info->designation,
                'rank' => $info->rank,
                'employment_type' => $info->employment_type,
                'hire_date' => optional($info->hire_date)->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    if ($employmentRecords === [] || $employmentRecords === null) {
        $employmentRecords = [[
            'user_type' => '',
            'position' => '',
            'designation' => '',
            'rank' => '',
            'employment_type' => '',
            'hire_date' => '',
        ]];
    }
@endphp

<section class="employee-tab-section" data-employment-information-root data-is-hybrid="{{ $isHybridChecked ? '1' : '0' }}">
    <h2 class="mb-4 text-lg font-semibold text-gray-900">Employment Information</h2>

    <div class="mb-4">
        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 py-3">
            <input
                type="checkbox"
                name="is_hybrid"
                value="1"
                class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                data-employment-hybrid-toggle
                data-employment-hybrid-reload="{{ ($wizardMode ?? false) ? '0' : '1' }}"
                @checked($isHybridChecked)
            >
            <span>
                <span class="block text-sm font-medium text-gray-900">Hybrid</span>
                <span class="block text-xs text-gray-500">Requires one Faculty and one Staff employment record.</span>
            </span>
        </label>
        @error('is_hybrid')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        @error('employment_informations')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label for="employee_number" class="form-label">Employee Number <span class="text-red-500">*</span></label>
            <input id="employee_number" name="employee_number" type="text" value="{{ old('employee_number', $employee->employee_number ?? '') }}" required class="form-input" autocomplete="off">
            <p class="mt-1 text-xs text-gray-500">Must be unique across all active employees.</p>
            @error('employee_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="compliance_status" class="form-label">Compliance Status</label>
            <select id="compliance_status" name="compliance_status" class="form-input" data-compliance-status-select>
                @foreach (['pending' => 'Pending', 'compliant' => 'Compliant', 'overdue' => 'Overdue', 'withheld' => 'Withheld'] as $value => $label)
                    <option value="{{ $value }}" @selected(($complianceStatus ?? 'pending') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="space-y-4" data-employment-info-rows>
        @if ($isHybridChecked)
            <div class="space-y-4" data-employment-hybrid-panels>
                @include('employees.partials._employment-information-row', [
                    'index' => 0,
                    'record' => $employmentRecords[0] ?? ['user_type' => 'faculty'],
                    'formOptions' => $formOptions,
                    'isHybrid' => true,
                    'fixedCategory' => 'faculty',
                ])
                @include('employees.partials._employment-information-row', [
                    'index' => 1,
                    'record' => $employmentRecords[1] ?? ['user_type' => 'staff'],
                    'formOptions' => $formOptions,
                    'isHybrid' => true,
                    'fixedCategory' => 'staff',
                ])
            </div>
        @else
            <div data-employment-single-panel>
                @include('employees.partials._employment-information-row', [
                    'index' => 0,
                    'record' => $employmentRecords[0] ?? [],
                    'formOptions' => $formOptions,
                    'isHybrid' => false,
                ])
            </div>
        @endif
    </div>
</section>
