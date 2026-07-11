@php
    $familyBackground = $employee->extended('family_background', []);
    $familyMembers = $employee->extended('family_members', []);
    $employmentHistory = $employee->extended('employment_history', []);
    $exams = $employee->extended('exams', []);
    $seminars = $employee->extended('seminars', []);
    $awards = $employee->extended('awards', []);
    $references = $employee->extended('references', []);
    $skillsProfile = $employee->extended('skills_profile', []);
    $generalInformation = $employee->extended('general_information', []);
@endphp

<div class="space-y-6">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 sm:p-5">
        <h3 class="mb-3 text-base font-semibold text-gray-900">HR Snapshot</h3>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Employee #</p>
                <p class="text-sm font-semibold text-gray-900">{{ $employee->employee_number }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Employment Status</p>
                <p class="text-sm font-semibold capitalize text-gray-900">{{ $employee->employment_status }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Campus</p>
                <p class="text-sm font-semibold text-gray-900">{{ $employee->displayValue($employee->campus_name) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Department / College</p>
                <p class="text-sm font-semibold text-gray-900">{{ $employee->displayValue($employee->college ?: $employee->department) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Position</p>
                <p class="text-sm font-semibold text-gray-900">{{ $employee->displayValue($employee->position) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Employment Type</p>
                <p class="text-sm font-semibold text-gray-900">{{ $employee->displayValue($employee->employment_type) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Email</p>
                <p class="break-words text-sm font-semibold text-gray-900">{{ $employee->displayValue($employee->email) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Primary Phone</p>
                <p class="text-sm font-semibold text-gray-900">{{ $employee->displayValue($employee->phone) }}</p>
            </div>
        </div>
    </div>

    <div class="card-panel">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">A. Identity Profile</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><p class="text-sm text-gray-600">First Name</p><p class="mt-1 font-medium text-gray-900">{{ $employee->first_name }}</p></div>
            <div><p class="text-sm text-gray-600">Middle Name</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->middle_name) }}</p></div>
            <div><p class="text-sm text-gray-600">Last Name</p><p class="mt-1 font-medium text-gray-900">{{ $employee->last_name }}</p></div>
            <div><p class="text-sm text-gray-600">Full Name</p><p class="mt-1 font-medium text-gray-900">{{ $employee->full_name }}</p></div>
            <div><p class="text-sm text-gray-600">Email</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->email) }}</p></div>
            <div><p class="text-sm text-gray-600">Phone</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->phone) }}</p></div>
        </div>
    </div>

    <div class="card-panel">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">B. Demographics & Statutory IDs</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><p class="text-sm text-gray-600">Birth Date</p><p class="mt-1 font-medium text-gray-900">{{ $employee->birth_date?->format('M d, Y') ?: '—' }}</p></div>
            <div><p class="text-sm text-gray-600">Place of Birth</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->place_of_birth) }}</p></div>
            <div><p class="text-sm text-gray-600">Gender</p><p class="mt-1 font-medium capitalize text-gray-900">{{ $employee->displayValue($employee->gender) }}</p></div>
            <div><p class="text-sm text-gray-600">Civil Status</p><p class="mt-1 font-medium capitalize text-gray-900">{{ $employee->displayValue($employee->civil_status) }}</p></div>
            <div><p class="text-sm text-gray-600">Nationality</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->nationality) }}</p></div>
            <div><p class="text-sm text-gray-600">Religion</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->religion) }}</p></div>
            <div><p class="text-sm text-gray-600">Language / Dialect</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->language_dialect) }}</p></div>
            <div><p class="text-sm text-gray-600">Height (cm)</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->height_cm) }}</p></div>
            <div><p class="text-sm text-gray-600">Weight (kg)</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->weight_kg) }}</p></div>
            <div><p class="text-sm text-gray-600">TIN</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->tin_number) }}</p></div>
            <div><p class="text-sm text-gray-600">SSS</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->sss_number) }}</p></div>
            <div><p class="text-sm text-gray-600">PhilHealth</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->philhealth_number) }}</p></div>
            <div><p class="text-sm text-gray-600">Pag-IBIG</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->pagibig_number) }}</p></div>
            <div><p class="text-sm text-gray-600">GSIS</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->gsis_number) }}</p></div>
            <div><p class="text-sm text-gray-600">Tax Status</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->tax_status) }}</p></div>
        </div>
    </div>

    <div class="card-panel">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">C. Employment & Assignment</h3>
        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><p class="text-sm text-gray-600">Employee Number</p><p class="mt-1 font-medium text-gray-900">{{ $employee->employee_number }}</p></div>
            <div><p class="text-sm text-gray-600">Hybrid</p><p class="mt-1 font-medium text-gray-900">{{ $employee->is_hybrid ? 'Yes' : 'No' }}</p></div>
            <div><p class="text-sm text-gray-600">Program</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->program) }}</p></div>
            <div><p class="text-sm text-gray-600">Department</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->department) }}</p></div>
            <div><p class="text-sm text-gray-600">College</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->college) }}</p></div>
            <div><p class="text-sm text-gray-600">Campus</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->campus_name) }}</p></div>
            <div>
                <p class="text-sm text-gray-600">Employment Status</p>
                <p class="mt-1">
                    <span class="capitalize {{ $employee->employment_status === 'active' ? 'badge-success' : 'badge-muted' }}">{{ $employee->employment_status }}</span>
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Compliance</p>
                <p class="mt-1">@include('employees._compliance-badge', ['status' => $employee->compliance_status])</p>
            </div>
        </div>

        @if ($employee->employmentInformations->isNotEmpty())
            <div class="space-y-4 border-t border-gray-100 pt-4">
                @foreach ($employee->employmentInformations as $index => $employmentInfo)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <h4 class="mb-3 text-sm font-semibold text-gray-900">
                            Employment Information {{ $employee->employmentInformations->count() > 1 ? '#'.($index + 1) : '' }}
                            @if ($employmentInfo->user_type_label)
                                <span class="font-normal text-gray-600">({{ $employmentInfo->user_type_label }})</span>
                            @endif
                        </h4>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div><p class="text-sm text-gray-600">Category</p><p class="mt-1 font-medium text-gray-900">{{ $employmentInfo->user_type_label }}</p></div>
                            <div><p class="text-sm text-gray-600">Position</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->position) }}</p></div>
                            <div><p class="text-sm text-gray-600">Designation</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->designation) }}</p></div>
                            <div><p class="text-sm text-gray-600">Rank</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->rank) }}</p></div>
                            <div><p class="text-sm text-gray-600">Employment Type</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->employment_type) }}</p></div>
                            <div><p class="text-sm text-gray-600">Hire Date</p><p class="mt-1 font-medium text-gray-900">{{ $employmentInfo->hire_date?->format('M d, Y') ?: '—' }}</p></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500">No employment information records yet.</p>
        @endif

        @if ($employee->campusAssignments->isNotEmpty())
            <div class="space-y-4 border-t border-gray-100 pt-4">
                <h4 class="text-sm font-semibold text-gray-900">Campus Assignments</h4>
                @foreach ($employee->campusAssignments as $assignment)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <p class="mb-3 text-sm font-semibold text-gray-900">
                            {{ $assignment->campus?->campus_name ?? 'Campus' }}
                            @if ($assignment->is_primary)
                                <span class="ml-1 text-xs font-normal text-gray-500">(Primary)</span>
                            @endif
                        </p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div><p class="text-sm text-gray-600">Biometric ID</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($assignment->biometric_id) }}</p></div>
                            <div><p class="text-sm text-gray-600">College</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($assignment->college) }}</p></div>
                            <div><p class="text-sm text-gray-600">Department</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($assignment->department) }}</p></div>
                            <div><p class="text-sm text-gray-600">Program</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($assignment->program) }}</p></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($employee->employmentInformations->contains(fn ($info) => $info->salary))
        <div class="card-panel">
            <h3 class="mb-4 text-lg font-semibold text-gray-900">D. Employee Salary</h3>
            <div class="space-y-4">
                @foreach ($employee->employmentInformations as $employmentInfo)
                    @continue(! $employmentInfo->salary)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <h4 class="mb-3 text-sm font-semibold text-gray-900">
                            {{ $employee->is_hybrid ? $employmentInfo->user_type_label.' Salary' : 'Current Salary' }}
                        </h4>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div><p class="text-sm text-gray-600">Date Effective</p><p class="mt-1 font-medium text-gray-900">{{ $employmentInfo->salary->date_effective?->format('M d, Y') ?: '—' }}</p></div>
                            <div><p class="text-sm text-gray-600">Pay Type</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->salary->payType?->pay_type) }}</p></div>
                            <div><p class="text-sm text-gray-600">Basic Computation</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->salary->basicComputation?->basic_computation) }}</p></div>
                            <div><p class="text-sm text-gray-600">Rate Group</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->salary->rateGroup?->description) }}</p></div>
                            <div><p class="text-sm text-gray-600">Days Per Period</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->salary->days_per_period) }}</p></div>
                            <div><p class="text-sm text-gray-600">Hours Per Day</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->salary->hours_per_day) }}</p></div>
                            <div><p class="text-sm text-gray-600">Use Basic Income as Hourly Rate</p><p class="mt-1 font-medium text-gray-900">{{ $employmentInfo->salary->use_basic_income_as_hourly_rate ? 'Yes' : 'No' }}</p></div>
                            <div><p class="text-sm text-gray-600">Night Diff. Rate Group</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employmentInfo->salary->ndRateGroup?->description) }}</p></div>
                            <div><p class="text-sm text-gray-600">Hourly Rate</p><p class="mt-1 font-medium text-gray-900">@php($hourlyRate = $employmentInfo->salary->hourlyRate()){{ $hourlyRate !== null ? number_format($hourlyRate, 2) : '—' }}</p></div>
                        </div>

                        @if ($employmentInfo->salary->incomes->isNotEmpty())
                            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                                <table class="table-skolaris min-w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-2 text-left">Income</th>
                                            <th class="px-3 py-2 text-right">Taxable</th>
                                            <th class="px-3 py-2 text-right">Non-Taxable</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($employmentInfo->salary->incomes as $income)
                                            <tr>
                                                <td class="px-3 py-2">{{ $income->incomeType?->description ?: '—' }}</td>
                                                <td class="px-3 py-2 text-right">{{ number_format((float) $income->taxable, 2) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number_format((float) $income->non_taxable, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($employmentInfo->salary->deductions->isNotEmpty())
                            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                                <table class="table-skolaris min-w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-2 text-left">Deduction</th>
                                            <th class="px-3 py-2 text-right">Employee Amount</th>
                                            <th class="px-3 py-2 text-right">Employer Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($employmentInfo->salary->deductions as $deduction)
                                            <tr>
                                                <td class="px-3 py-2">{{ $deduction->deductionType?->description ?: '—' }}</td>
                                                <td class="px-3 py-2 text-right">{{ number_format((float) $deduction->employee_amount, 2) }}</td>
                                                <td class="px-3 py-2 text-right">{{ number_format((float) $deduction->employer_amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card-panel">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">E. Contact & Emergency</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><p class="text-sm text-gray-600">Primary Phone</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->phone) }}</p></div>
            <div><p class="text-sm text-gray-600">Home Phone</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->home_phone) }}</p></div>
            <div><p class="text-sm text-gray-600">Work Phone</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->work_phone) }}</p></div>
            <div><p class="text-sm text-gray-600">Fax Number</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->fax_number) }}</p></div>
            <div><p class="text-sm text-gray-600">Emergency Contact Name</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->emergency_contact_name) }}</p></div>
            <div><p class="text-sm text-gray-600">Emergency Relationship</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->emergency_contact_relationship) }}</p></div>
            <div><p class="text-sm text-gray-600">Emergency Contact Phone</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->emergency_contact_phone) }}</p></div>
            <div><p class="text-sm text-gray-600">Emergency Contact Email</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->emergency_contact_email) }}</p></div>
            <div class="sm:col-span-2"><p class="text-sm text-gray-600">Emergency Contact Address</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->emergency_contact_address) }}</p></div>
        </div>
    </div>

    <div class="card-panel">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">F. Address</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><p class="text-sm text-gray-600">Address Line</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->address_line) }}</p></div>
            <div><p class="text-sm text-gray-600">Country</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->country) }}</p></div>
            <div><p class="text-sm text-gray-600">Region</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->region) }}</p></div>
            <div><p class="text-sm text-gray-600">Province</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->province) }}</p></div>
            <div><p class="text-sm text-gray-600">City / Municipality</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->city_municipality) }}</p></div>
            <div><p class="text-sm text-gray-600">Barangay</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->barangay) }}</p></div>
            <div><p class="text-sm text-gray-600">Postal Code</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->postal_code) }}</p></div>
        </div>
    </div>

    <div class="card-panel">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">G. Family, Background & Professional Records</h3>

        @if (! empty($familyBackground))
            <details class="mb-4 rounded-lg border border-gray-200" open>
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900">Family Background</summary>
                <div class="border-t border-gray-100 px-4 py-3">
                    <dl class="grid gap-2 sm:grid-cols-2">
                        @foreach ($familyBackground as $key => $value)
                            <div>
                                <dt class="text-xs uppercase text-gray-500">{{ str_replace('_', ' ', $key) }}</dt>
                                <dd class="text-sm text-gray-900">{{ is_array($value) ? json_encode($value) : ($value ?: '—') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </details>
        @endif

        @foreach ([
            'Family Members' => $familyMembers,
            'Employment History' => $employmentHistory,
            'Exams' => $exams,
            'Seminars' => $seminars,
            'Awards' => $awards,
            'References' => $references,
        ] as $title => $items)
            @if (! empty($items))
                <details class="mb-4 rounded-lg border border-gray-200">
                    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900">{{ $title }} ({{ count($items) }})</summary>
                    <div class="space-y-3 border-t border-gray-100 px-4 py-3">
                        @foreach ($items as $index => $item)
                            <div class="rounded-md bg-gray-50 p-3 text-sm">
                                <p class="mb-2 font-medium text-gray-700">#{{ $index + 1 }}</p>
                                <dl class="grid gap-1 sm:grid-cols-2">
                                    @foreach ((array) $item as $key => $value)
                                        <div>
                                            <dt class="text-xs text-gray-500">{{ str_replace('_', ' ', $key) }}</dt>
                                            <dd class="text-gray-900">{{ $value ?: '—' }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        @endforeach

        @if (! empty($skillsProfile))
            <details class="mb-4 rounded-lg border border-gray-200">
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900">Skills Profile</summary>
                <div class="border-t border-gray-100 px-4 py-3">
                    <dl class="grid gap-2 sm:grid-cols-2">
                        @foreach (['computer' => 'Computer Skills', 'technical' => 'Technical Skills', 'talents' => 'Talents'] as $key => $label)
                            @if (! empty($skillsProfile['skills'][$key]))
                                <div>
                                    <dt class="text-xs text-gray-500">{{ $label }}</dt>
                                    <dd class="text-sm text-gray-900">{{ is_array($skillsProfile['skills'][$key]) ? implode(', ', $skillsProfile['skills'][$key]) : $skillsProfile['skills'][$key] }}</dd>
                                </div>
                            @endif
                        @endforeach
                        @if (! empty($skillsProfile['other_skills']))
                            <div>
                                <dt class="text-xs text-gray-500">Other Skills</dt>
                                <dd class="text-sm text-gray-900">{{ $skillsProfile['other_skills'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </details>
        @endif

        @if (! empty($generalInformation))
            <details class="rounded-lg border border-gray-200">
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-900">General Information</summary>
                <div class="border-t border-gray-100 px-4 py-3">
                    <dl class="grid gap-2 sm:grid-cols-2">
                        @foreach ($generalInformation as $key => $value)
                            <div>
                                <dt class="text-xs text-gray-500">{{ str_replace('_', ' ', $key) }}</dt>
                                <dd class="text-sm text-gray-900">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?: '—') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </details>
        @endif

        @if (empty($familyBackground) && empty($familyMembers) && empty($employmentHistory) && empty($exams) && empty($seminars) && empty($awards) && empty($references) && empty($skillsProfile) && empty($generalInformation))
            <p class="text-sm text-gray-500">No extended profile records yet.</p>
        @endif
    </div>

    <div class="card-panel">
        <h3 class="mb-4 text-lg font-semibold text-gray-900">H. System Access</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><p class="text-sm text-gray-600">User Type</p><p class="mt-1 font-medium text-gray-900">{{ $employee->displayValue($employee->user_type_label) }}</p></div>
            <div>
                <p class="text-sm text-gray-600">Account Status</p>
                <p class="mt-1">
                    <span class="{{ $employee->is_active ? 'badge-success' : 'badge-muted' }}">{{ $employee->is_active ? 'Active' : 'Inactive' }}</span>
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Confidential</p>
                <p class="mt-1">
                    @if ($employee->is_confidential)
                        <span class="badge-brand">Yes</span>
                    @else
                        <span class="badge-muted">No</span>
                    @endif
                </p>
            </div>
            <div><p class="text-sm text-gray-600">Created at</p><p class="mt-1 font-medium text-gray-900">{{ $employee->created_at?->format('M d, Y h:i A') }}</p></div>
        </div>
    </div>
</div>
