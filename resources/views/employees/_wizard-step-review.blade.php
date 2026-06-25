@php
    $data = $wizardData ?? [];
    $employmentRecords = $data['employment_informations'] ?? [];
    $userTypeLabels = ['faculty' => 'Faculty', 'staff' => 'Staff', 'admin' => 'Admin'];
@endphp

@if ($selectedCampus)
    <div class="relative mb-6 overflow-hidden rounded-lg border-2 border-blue-600 bg-[#00A3E6]/5 shadow-md">
        <div class="absolute top-3 right-3 z-10">
            <div class="rounded-full bg-[#00A3E6] p-1.5">
                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row">
            <div class="flex h-32 w-full shrink-0 items-center justify-center bg-gradient-to-br from-blue-900 to-blue-800 sm:h-auto sm:w-1/4">
                <svg class="h-12 w-12 text-white/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div class="min-w-0 flex-1 p-4">
                <h3 class="text-lg font-bold text-gray-900">{{ $selectedCampus->campus_name }}</h3>
                <p class="text-xs font-medium text-gray-500">{{ $selectedCampus->campus_code }}</p>
                @if ($selectedCampus->address)
                    <p class="mt-2 text-xs text-gray-600">{{ $selectedCampus->address }}</p>
                @endif
                <span class="mt-3 inline-flex items-center rounded-full bg-[#00A3E6] px-2 py-0.5 text-xs font-semibold text-white">Selected</span>
            </div>
        </div>
    </div>
@endif

<h2 class="mb-6 text-2xl font-bold text-gray-900">Review Your Information</h2>

<div class="mb-6 space-y-6">
    <div class="rounded-lg bg-gray-50 p-6">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">Employment Details</h3>
        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div><dt class="text-sm text-gray-600">Campus</dt><dd class="font-medium text-gray-900">{{ $selectedCampus->campus_name ?? '—' }}</dd></div>
            <div><dt class="text-sm text-gray-600">Hybrid</dt><dd class="font-medium text-gray-900">{{ ! empty($data['is_hybrid']) ? 'Yes' : 'No' }}</dd></div>
            <div><dt class="text-sm text-gray-600">Employee Number</dt><dd class="font-medium text-gray-900">{{ $data['employee_number'] ?? '—' }}</dd></div>
            <div><dt class="text-sm text-gray-600">Department</dt><dd class="font-medium text-gray-900">{{ $data['department'] ?? '—' }}</dd></div>
        </dl>

        @if (! empty($employmentRecords))
            <div class="mt-4 space-y-3 border-t border-gray-200 pt-4">
                @foreach ($employmentRecords as $index => $record)
                    <div class="rounded-md border border-gray-200 bg-white p-4">
                        <p class="mb-2 text-sm font-semibold text-gray-900">
                            Employment Information {{ count($employmentRecords) > 1 ? '#'.($index + 1) : '' }}
                        </p>
                        <dl class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div><dt class="text-sm text-gray-600">Category</dt><dd class="font-medium text-gray-900">{{ $userTypeLabels[$record['user_type'] ?? ''] ?? '—' }}</dd></div>
                            <div><dt class="text-sm text-gray-600">Position</dt><dd class="font-medium text-gray-900">{{ $record['position'] ?? '—' }}</dd></div>
                            <div><dt class="text-sm text-gray-600">Employment Type</dt><dd class="font-medium text-gray-900">{{ $record['employment_type'] ?? '—' }}</dd></div>
                        </dl>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-lg bg-gray-50 p-6">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">Personal Information</h3>
        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div><dt class="text-sm text-gray-600">Full Name</dt><dd class="font-medium text-gray-900">{{ trim(implode(' ', array_filter([$data['first_name'] ?? '', $data['middle_name'] ?? '', $data['last_name'] ?? '', $data['suffix'] ?? '']))) }}</dd></div>
            <div><dt class="text-sm text-gray-600">Email</dt><dd class="font-medium text-gray-900">{{ $data['email'] ?? '—' }}</dd></div>
            <div><dt class="text-sm text-gray-600">Phone</dt><dd class="font-medium text-gray-900">{{ $data['phone'] ?? '—' }}</dd></div>
            <div><dt class="text-sm text-gray-600">Employment Status</dt><dd class="font-medium capitalize text-gray-900">{{ $data['employment_status'] ?? 'active' }}</dd></div>
        </dl>
    </div>
</div>

<form method="POST" action="{{ route('employees.store') }}" class="space-y-4">
    @csrf

    <div class="employee-tab-section">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">System Access</h3>
        <div class="max-w-md">
            <label for="role_id" class="form-label">System Role <span class="text-red-500">*</span></label>
            <select id="role_id" name="role_id" class="form-input" required>
                <option value="">Select a role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('role_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="mt-2 text-xs text-gray-500">Assign the system role for this employee account, matching the skolaris add employee review step.</p>
        </div>
    </div>

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-between">
        <a href="{{ route('employees.create', ['step' => 1]) }}" class="btn-secondary w-full sm:w-auto">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>
        <button type="submit" class="btn-primary w-full sm:w-auto">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Create Employee
        </button>
    </div>
</form>
