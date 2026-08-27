@php
    $index = $index ?? 0;
    $record = $record ?? [];
    $selectedCampusId = old("campus_assignments.$index.campus_id", $record['campus_id'] ?? ($wizardMode && $index === 0 ? ($wizardCampusId ?? '') : ''));
    $collegeSelectId = "campus_assignment_{$index}_college";
    $programSelectId = "campus_assignment_{$index}_program";
    $oldMain = old("campus_assignments.$index.is_primary");
    $isMainAssignment = $oldMain !== null
        ? in_array($oldMain, [1, '1', true, 'on', 'yes'], true)
        : (array_key_exists('is_primary', $record)
            ? filter_var($record['is_primary'], FILTER_VALIDATE_BOOLEAN)
            : $index === 0);
@endphp

<div
    class="rounded-lg border border-gray-200 bg-gray-50/70 p-4"
    data-campus-assignment-row
    data-assignment-index="{{ $index }}"
>
    <div class="mb-4 flex items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <h3 class="text-sm font-semibold text-gray-900">Campus Assignment</h3>
            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                <input type="hidden" name="campus_assignments[{{ $index }}][is_primary]" value="0">
                <input
                    id="campus_assignment_{{ $index }}_is_primary"
                    type="checkbox"
                    name="campus_assignments[{{ $index }}][is_primary]"
                    value="1"
                    class="rounded border-gray-300 text-[#0B318F] focus:ring-[#0B318F]"
                    data-main-assignment-checkbox
                    @checked($isMainAssignment)
                >
                Main assignment
            </label>
        </div>
        @if ($canRemove ?? false)
            <button type="button" class="text-xs text-red-600 hover:text-red-800" data-campus-assignment-remove>
                Remove
            </button>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="form-label" for="campus_assignment_{{ $index }}_campus">Campus <span class="text-red-500">*</span></label>
            @if ($wizardMode && $index === 0)
                <input type="hidden" name="campus_assignments[{{ $index }}][campus_id]" value="{{ $selectedCampusId }}">
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $employee->campus_name ?? 'Selected in step 1' }}</p>
                <p class="text-xs text-gray-500">Campus was selected in the first wizard step.</p>
            @else
                <select
                    id="campus_assignment_{{ $index }}_campus"
                    name="campus_assignments[{{ $index }}][campus_id]"
                    class="form-input"
                    data-assignment-campus-select
                    required
                >
                    <option value="">Select campus</option>
                    @foreach ($campuses as $campus)
                        <option value="{{ $campus->campus_id }}" @selected((string) $selectedCampusId === (string) $campus->campus_id)>
                            {{ $campus->campus_name }} ({{ $campus->campus_code }})
                        </option>
                    @endforeach
                </select>
            @endif
            @error("campus_assignments.$index.campus_id")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="form-label" for="campus_assignment_{{ $index }}_biometric">Biometric ID <span class="text-red-500">*</span></label>
            <input
                id="campus_assignment_{{ $index }}_biometric"
                type="text"
                name="campus_assignments[{{ $index }}][biometric_id]"
                value="{{ old("campus_assignments.$index.biometric_id", $record['biometric_id'] ?? '') }}"
                class="form-input"
                maxlength="50"
                required
                placeholder="e.g. 1"
            >
            @error("campus_assignments.$index.biometric_id")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="form-label" for="{{ $collegeSelectId }}">Department / College</label>
            <select
                id="{{ $collegeSelectId }}"
                name="campus_assignments[{{ $index }}][college]"
                class="form-input"
                data-campus-filter="college"
                data-assignment-college-select
                @disabled(! $selectedCampusId)
            >
                <option value="">Select College</option>
                @foreach ($formOptions['colleges'] as $college)
                    <option
                        value="{{ $college->college_name }}"
                        data-campus-id="{{ $college->campus_id }}"
                        @selected(old("campus_assignments.$index.college", $record['college'] ?? '') === $college->college_name)
                    >
                        {{ $college->college_name }}
                    </option>
                @endforeach
                @php $legacyCollege = old("campus_assignments.$index.college", $record['college'] ?? ''); @endphp
                @if ($legacyCollege && ! $formOptions['colleges']->contains('college_name', $legacyCollege))
                    <option value="{{ $legacyCollege }}" selected>{{ $legacyCollege }} (Legacy)</option>
                @endif
            </select>
        </div>

        <div>
            <label class="form-label" for="campus_assignment_{{ $index }}_department">Employee Department</label>
            <select id="campus_assignment_{{ $index }}_department" name="campus_assignments[{{ $index }}][department]" class="form-input">
                <option value="">Select Employee Department</option>
                @foreach ($formOptions['employeeDepartments'] as $employeeDepartment)
                    <option value="{{ $employeeDepartment->department_name }}" @selected(old("campus_assignments.$index.department", $record['department'] ?? '') === $employeeDepartment->department_name)>
                        {{ $employeeDepartment->department_name }}
                    </option>
                @endforeach
                @php $legacyDepartment = old("campus_assignments.$index.department", $record['department'] ?? ''); @endphp
                @if ($legacyDepartment && ! $formOptions['employeeDepartments']->contains('department_name', $legacyDepartment))
                    <option value="{{ $legacyDepartment }}" selected>{{ $legacyDepartment }} (Legacy)</option>
                @endif
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="form-label" for="{{ $programSelectId }}">Program</label>
            <select
                id="{{ $programSelectId }}"
                name="campus_assignments[{{ $index }}][program]"
                class="form-input"
                data-campus-filter="program"
                data-assignment-program-select
                @disabled(! $selectedCampusId)
            >
                <option value="">Select Program</option>
                @foreach ($formOptions['programs'] as $program)
                    <option
                        value="{{ $program->program_name }}"
                        data-campus-id="{{ $program->campus_id }}"
                        @selected(old("campus_assignments.$index.program", $record['program'] ?? '') === $program->program_name)
                    >
                        {{ $program->program_name }}
                    </option>
                @endforeach
                @php $legacyProgram = old("campus_assignments.$index.program", $record['program'] ?? ''); @endphp
                @if ($legacyProgram && ! $formOptions['programs']->contains('program_name', $legacyProgram))
                    <option value="{{ $legacyProgram }}" selected>{{ $legacyProgram }} (Legacy)</option>
                @endif
            </select>
        </div>
    </div>
</div>
