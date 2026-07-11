@php
    $assignmentRecords = old('campus_assignments');

    if ($assignmentRecords === null) {
        $assignmentRecords = $employee->campusAssignments
            ->map(fn ($assignment) => [
                'campus_id' => $assignment->campus_id,
                'biometric_id' => $assignment->biometric_id,
                'college' => $assignment->college,
                'department' => $assignment->department,
                'program' => $assignment->program,
            ])
            ->values()
            ->all();
    }

    if ($assignmentRecords === [] || $assignmentRecords === null) {
        $assignmentRecords = [[
            'campus_id' => old('campus_id', $employee->campus_id ?? ($wizardCampusId ?? '')),
            'biometric_id' => '',
            'college' => old('college', $employee->college ?? ''),
            'department' => old('department', $employee->department ?? ''),
            'program' => old('program', $employee->program ?? ''),
        ]];
    }
@endphp

<section class="employee-tab-section" data-campus-assignments-root>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Assignment & Organization</h2>
            <p class="mt-1 text-sm text-gray-500">Assign one or more campuses per employee. Biometric ID is used for DTR uploads at that campus.</p>
        </div>
        <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" data-campus-assignment-add>
            Add Assignment
        </button>
    </div>

    @error('campus_assignments')<p class="mb-3 text-xs text-red-600">{{ $message }}</p>@enderror

    <div class="space-y-4" data-campus-assignment-rows>
        @foreach ($assignmentRecords as $index => $record)
            @include('employees.partials._campus-assignment-row', [
                'index' => $index,
                'record' => $record,
                'campuses' => $campuses,
                'formOptions' => $formOptions,
                'wizardMode' => $wizardMode ?? false,
                'wizardCampusId' => $wizardCampusId ?? null,
                'canRemove' => count($assignmentRecords) > 1,
            ])
        @endforeach
    </div>

    <template data-campus-assignment-row-template>
        @include('employees.partials._campus-assignment-row', [
            'index' => '__INDEX__',
            'record' => [],
            'campuses' => $campuses,
            'formOptions' => $formOptions,
            'wizardMode' => $wizardMode ?? false,
            'wizardCampusId' => $wizardCampusId ?? null,
            'canRemove' => true,
        ])
    </template>
</section>
