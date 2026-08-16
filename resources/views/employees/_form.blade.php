@php
    $wizardMode = $wizardMode ?? false;
    $isEdit = isset($employee) && $employee->exists;
    $action = $wizardMode
        ? route('employees.wizard.details')
        : ($isEdit ? route('employees.update', $employee) : route('employees.store'));
    $activeTab = old('active_tab', request('tab', 'personal'));
    $extended = old('extended_profile', $employee->extended_profile ?? []);
    $complianceStatus = old('compliance_status', $employee->compliance_status ?? 'pending');
    $formTitle = $isEdit ? 'Edit Employee: '.$employee->full_name : 'New Employee';
    $formDescription = $isEdit
        ? 'Complete edit form aligned with Add Employee fields.'
        : 'Register a new employee with personal, employment, and contact information.';
    $backUrl = $isEdit ? route('employees.show', $employee) : route('employees.index');
    $campuses = $campuses ?? \App\Models\Campus::query()->where('is_active', true)->orderBy('campus_name')->get();
    $formCampusId = (int) old('campus_id', $employee->campus_id ?? 0);
    $formOptions = app(\App\Services\EmployeeFormOptions::class)->resolve(
        $formCampusId ?: null,
        old('region', $employee->region ?? null),
        old('province', $employee->province ?? null),
    );
    $currentCountry = old('country', $employee->country ?? 'Philippines');
    $isPhilippines = strcasecmp((string) $currentCountry, 'Philippines') === 0;
    $noMiddleNameChecked = (bool) old(
        'no_middle_name',
        request()->has('no_middle_name')
            ? request()->boolean('no_middle_name')
            : ($isEdit && blank($employee->middle_name ?? null)),
    );
@endphp

@if (! $wizardMode)
    @include('employees._form-header', [
        'isEdit' => $isEdit,
        'title' => $formTitle,
        'description' => $formDescription,
        'backUrl' => $backUrl,
    ])

    @include('employees._compliance-banner', ['status' => $complianceStatus])
@endif

<form
    method="POST"
    action="{{ $action }}"
    id="employee-form"
    class="space-y-4 {{ $wizardMode ? 'employee-wizard-details-form' : '' }}"
    @if ($wizardMode) data-employee-wizard-form data-wizard-campus-id="{{ $formCampusId }}" @else data-employee-form data-employee-form-tabs novalidate @endif
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    @if (! $wizardMode)
        <input type="hidden" name="active_tab" value="{{ $activeTab }}" data-employee-active-tab>
        @include('employees._tabs-nav', [
            'activeTab' => $activeTab,
            'showHistoryTab' => $isEdit,
        ])
    @else
        <h2 class="text-2xl font-bold text-gray-900">Employee Details</h2>
        <p class="text-sm text-gray-600">Complete all sections below. Role assignment is on the review step.</p>
    @endif

    <div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'personal') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="personal" @endunless>
        <section class="employee-tab-section">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Personal Information</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="first_name" class="form-label">First Name <span class="text-red-500">*</span></label>
                    <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $employee->first_name ?? '') }}" required class="form-input">
                    @error('first_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="last_name" class="form-label">Last Name <span class="text-red-500">*</span></label>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $employee->last_name ?? '') }}" required class="form-input">
                    @error('last_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="middle_name" class="form-label">
                        Middle Name
                        <span
                            class="text-red-500 {{ $noMiddleNameChecked ? 'hidden' : '' }}"
                            data-middle-name-required-marker
                        >*</span>
                    </label>
                    <input
                        id="middle_name"
                        name="middle_name"
                        type="text"
                        value="{{ old('middle_name', $employee->middle_name ?? '') }}"
                        class="form-input"
                        data-middle-name-input
                        @disabled($noMiddleNameChecked)
                    >
                    <label class="mt-2 flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            name="no_middle_name"
                            value="1"
                            class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]"
                            data-no-middle-name-toggle
                            @checked($noMiddleNameChecked)
                        >
                        <span class="text-sm text-gray-700">No middle name</span>
                    </label>
                    @error('middle_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="suffix" class="form-label">Suffix</label>
                    <input id="suffix" name="suffix" type="text" value="{{ old('suffix', $employee->suffix ?? '') }}" class="form-input" placeholder="Jr., Sr., III, etc.">
                </div>
                <div>
                    <label for="birth_date" class="form-label">Birth Date</label>
                    <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', optional($employee->birth_date ?? null)->format('Y-m-d')) }}" class="form-input">
                </div>
                <div>
                    <label for="place_of_birth" class="form-label">Place of Birth</label>
                    <input id="place_of_birth" name="place_of_birth" type="text" value="{{ old('place_of_birth', $employee->place_of_birth ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="gender" class="form-label">Sex</label>
                    <select id="gender" name="gender" class="form-input">
                        <option value="">Select Gender</option>
                        @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('gender', $employee->gender ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="civil_status" class="form-label">Civil Status</label>
                    <select id="civil_status" name="civil_status" class="form-input">
                        <option value="">Select Civil Status</option>
                        @foreach (['single', 'married', 'widowed', 'separated', 'divorced'] as $status)
                            <option value="{{ $status }}" @selected(old('civil_status', $employee->civil_status ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="nationality" class="form-label">Nationality</label>
                    <input id="nationality" name="nationality" type="text" value="{{ old('nationality', $employee->nationality ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="religion" class="form-label">Religion</label>
                    <input id="religion" name="religion" type="text" value="{{ old('religion', $employee->religion ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="language_dialect" class="form-label">Language / Dialect</label>
                    <input id="language_dialect" name="language_dialect" type="text" value="{{ old('language_dialect', $employee->language_dialect ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="height_cm" class="form-label">Height (cm)</label>
                    <input id="height_cm" name="height_cm" type="number" step="0.01" value="{{ old('height_cm', $employee->height_cm ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="weight_kg" class="form-label">Weight (kg)</label>
                    <input id="weight_kg" name="weight_kg" type="number" step="0.01" value="{{ old('weight_kg', $employee->weight_kg ?? '') }}" class="form-input">
                </div>
            </div>

            <h3 class="mb-3 mt-6 text-sm font-semibold text-gray-800">Statutory IDs</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @php
                    $govIdFields = [
                        'tin_number' => ['label' => 'TIN', 'type' => \App\Support\GovernmentIdNumbers::TYPE_TIN],
                        'sss_number' => ['label' => 'SSS', 'type' => \App\Support\GovernmentIdNumbers::TYPE_SSS],
                        'philhealth_number' => ['label' => 'PhilHealth', 'type' => \App\Support\GovernmentIdNumbers::TYPE_PHILHEALTH],
                        'pagibig_number' => ['label' => 'Pag-IBIG', 'type' => \App\Support\GovernmentIdNumbers::TYPE_PAGIBIG],
                    ];
                @endphp
                @foreach ($govIdFields as $field => $meta)
                    @php
                        $rawGovIdValue = old($field, $employee->{$field} ?? '');
                        $displayGovIdValue = filled($rawGovIdValue)
                            ? \App\Support\GovernmentIdNumbers::format($rawGovIdValue, $meta['type'])
                            : '';
                    @endphp
                    <div>
                        <label for="{{ $field }}" class="form-label">{{ $meta['label'] }}</label>
                        <input
                            id="{{ $field }}"
                            name="{{ $field }}"
                            type="text"
                            value="{{ $displayGovIdValue }}"
                            class="form-input"
                            inputmode="numeric"
                            autocomplete="off"
                            data-gov-id-input
                            data-gov-id-type="{{ $meta['type'] }}"
                        >
                    </div>
                @endforeach
                <div>
                    <label for="gsis_number" class="form-label">GSIS</label>
                    <input id="gsis_number" name="gsis_number" type="text" value="{{ old('gsis_number', $employee->gsis_number ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="tax_status" class="form-label">Tax Status</label>
                    <input id="tax_status" name="tax_status" type="text" value="{{ old('tax_status', $employee->tax_status ?? '') }}" class="form-input">
                </div>
            </div>
        </section>
    </div>

    <div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'assignment') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="assignment" @endunless>
        @include('employees.partials._campus-assignments', [
            'employee' => $employee,
            'campuses' => $campuses,
            'formOptions' => $formOptions,
            'wizardMode' => $wizardMode,
            'wizardCampusId' => $formCampusId,
        ])
    </div>

    <div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'employment') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="employment" @endunless>
        @include('employees.partials._employment-information', [
            'employee' => $employee,
            'formOptions' => $formOptions,
            'complianceStatus' => $complianceStatus,
            'wizardMode' => $wizardMode,
        ])
    </div>

    <div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'salary') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="salary" @endunless>
        @include('employees.partials._employee-salary', [
            'employee' => $employee,
            'formOptions' => $formOptions,
        ])
    </div>

    <div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'contact') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="contact" @endunless>
        <section class="employee-tab-section">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Contact Information</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="email" class="form-label">Email <span class="text-red-500">*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $employee->email ?? '') }}" required class="form-input">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="form-label">Primary Phone <span class="text-red-500">*</span></label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $employee->phone ?? '') }}" required class="form-input">
                    @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="home_phone" class="form-label">Home Phone</label>
                    <input id="home_phone" name="home_phone" type="text" value="{{ old('home_phone', $employee->home_phone ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="work_phone" class="form-label">Work Phone</label>
                    <input id="work_phone" name="work_phone" type="text" value="{{ old('work_phone', $employee->work_phone ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="fax_number" class="form-label">Fax Number</label>
                    <input id="fax_number" name="fax_number" type="text" value="{{ old('fax_number', $employee->fax_number ?? '') }}" class="form-input">
                </div>
            </div>

            <h3 class="mb-3 mt-6 text-sm font-semibold text-gray-800">Emergency Contact</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="emergency_contact_name" class="form-label">Name</label>
                    <input id="emergency_contact_name" name="emergency_contact_name" type="text" value="{{ old('emergency_contact_name', $employee->emergency_contact_name ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="emergency_contact_relationship" class="form-label">Relationship</label>
                    <input id="emergency_contact_relationship" name="emergency_contact_relationship" type="text" value="{{ old('emergency_contact_relationship', $employee->emergency_contact_relationship ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="emergency_contact_phone" class="form-label">Phone</label>
                    <input id="emergency_contact_phone" name="emergency_contact_phone" type="text" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="emergency_contact_email" class="form-label">Email</label>
                    <input id="emergency_contact_email" name="emergency_contact_email" type="email" value="{{ old('emergency_contact_email', $employee->emergency_contact_email ?? '') }}" class="form-input">
                </div>
                <div class="md:col-span-2">
                    <label for="emergency_contact_address" class="form-label">Address</label>
                    <textarea id="emergency_contact_address" name="emergency_contact_address" rows="2" class="form-input min-h-[80px] py-2">{{ old('emergency_contact_address', $employee->emergency_contact_address ?? '') }}</textarea>
                </div>
            </div>
        </section>
    </div>

    <div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'address') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="address" @endunless>
        <section class="employee-tab-section" data-employee-address-root data-provinces-url="{{ route('employees.lookups.provinces') }}" data-cities-url="{{ route('employees.lookups.cities') }}">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Home Address</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="country" class="form-label">Country</label>
                    <select id="country" name="country" class="form-input" data-address-country>
                        <option value="">Select Country</option>
                        @foreach ($formOptions['countries'] as $country)
                            <option value="{{ $country->country_name }}" data-is-philippines="{{ strcasecmp($country->country_name, 'Philippines') === 0 ? '1' : '0' }}" @selected(strcasecmp(old('country', $employee->country ?? 'Philippines'), $country->country_name) === 0)>
                                {{ $country->country_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="address_line" class="form-label">No./Street</label>
                    <input id="address_line" name="address_line" type="text" value="{{ old('address_line', $employee->address_line ?? '') }}" class="form-input" placeholder="Street address, building, house number">
                </div>

                <div data-address-ph-fields class="{{ $isPhilippines ? '' : 'hidden' }} contents">
                    <div>
                        <label for="region" class="form-label">Region</label>
                        <select id="region" name="region" class="form-input" data-address-region @disabled(! $isPhilippines)>
                            <option value="">Select Region</option>
                            @foreach ($formOptions['regions'] as $region)
                                <option value="{{ $region->region_name }}" data-region-id="{{ $region->region_id }}" @selected(old('region', $employee->region ?? '') === $region->region_name)>
                                    {{ $region->region_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="province" class="form-label">Province</label>
                        <select id="province" name="province" class="form-input" data-address-province @disabled(! $isPhilippines || ! $formOptions['selectedRegionId'])>
                            <option value="">Select Province</option>
                            @foreach ($formOptions['provinces'] as $province)
                                <option value="{{ $province->province_name }}" data-province-id="{{ $province->province_id }}" @selected(old('province', $employee->province ?? '') === $province->province_name)>
                                    {{ $province->province_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="city_municipality" class="form-label">City / Municipality</label>
                        <select id="city_municipality" name="city_municipality" class="form-input" data-address-city @disabled(! $isPhilippines || ! $formOptions['selectedProvinceId'])>
                            <option value="">Select City</option>
                            @foreach ($formOptions['cities'] as $city)
                                <option value="{{ $city->city_name }}" data-postal-code="{{ $city->postal_code }}" @selected(old('city_municipality', $employee->city_municipality ?? '') === $city->city_name)>
                                    {{ $city->city_name }} ({{ $city->type }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div data-address-intl-fields class="{{ $isPhilippines ? 'hidden' : '' }} contents">
                    <div>
                        <label for="region_intl" class="form-label">Region / State</label>
                        <input id="region_intl" type="text" value="{{ $isPhilippines ? '' : old('region', $employee->region ?? '') }}" class="form-input" data-address-intl-name="region" @disabled($isPhilippines)>
                    </div>
                    <div>
                        <label for="province_intl" class="form-label">Province / State</label>
                        <input id="province_intl" type="text" value="{{ $isPhilippines ? '' : old('province', $employee->province ?? '') }}" class="form-input" data-address-intl-name="province" @disabled($isPhilippines)>
                    </div>
                    <div>
                        <label for="city_municipality_intl" class="form-label">City / Municipality</label>
                        <input id="city_municipality_intl" type="text" value="{{ $isPhilippines ? '' : old('city_municipality', $employee->city_municipality ?? '') }}" class="form-input" data-address-intl-name="city_municipality" @disabled($isPhilippines)>
                    </div>
                </div>

                <div>
                    <label for="barangay" class="form-label">Barangay</label>
                    <input id="barangay" name="barangay" type="text" value="{{ old('barangay', $employee->barangay ?? '') }}" class="form-input" placeholder="Barangay name">
                </div>
                <div>
                    <label for="postal_code" class="form-label">Postal Code</label>
                    <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $employee->postal_code ?? '') }}" class="form-input">
                </div>
            </div>
        </section>
    </div>

    <div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'extended') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="extended" @endunless>
        <section class="employee-tab-section">
            @include('employees._extended-profile', ['extended' => $extended])
        </section>
    </div>

    <div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'access') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="access" @endunless>
        <section class="employee-tab-section">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Account Access</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="employment_status" class="form-label">Account Status <span class="text-red-500">*</span></label>
                    <select id="employment_status" name="employment_status" class="form-input" required>
                        @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('employment_status', $employee->employment_status ?? 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">User Type</label>
                    <p class="mt-2 text-sm text-gray-700">
                        @if ($employee->is_hybrid)
                            Hybrid (Faculty & Staff)
                        @elseif ($employee->user_type_label)
                            {{ $employee->user_type_label }}
                        @else
                            —
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-gray-500">{{ $wizardMode ? 'Set employment information in the Employment section above.' : 'Set employment information in the Employment tab.' }}</p>
                </div>
            </div>

            <div class="mt-4">
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 py-3">
                    <input type="checkbox" name="is_confidential" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(old('is_confidential', $employee->is_confidential ?? false))>
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Confidential Record</span>
                        <span class="block text-xs text-gray-500">Mark this employee record as confidential.</span>
                    </span>
                </label>
            </div>

            @unless ($wizardMode)
                <p class="mt-3 text-xs text-gray-500">Category and role are editable in the Employment tab to match the Add Employee flow.</p>
            @endunless
        </section>
    </div>

    @unless ($wizardMode)
        @include('employees.partials._credentials-tab', [
            'employee' => $employee,
            'isEdit' => $isEdit,
            'activeTab' => $activeTab,
            'wizardMode' => false,
        ])

        @include('employees.partials._loans-tab', [
            'employee' => $employee,
            'isEdit' => $isEdit,
            'activeTab' => $activeTab,
            'wizardMode' => false,
        ])
    @endunless

    @if ($isEdit)
        <div
            class="employee-tab-panel {{ $activeTab === 'history' ? '' : 'hidden' }}"
            data-employee-tab-panel="history"
            data-employee-profile-lazy-panel
            data-lazy-url="{{ route('employees.history', ['employee' => $employee->employee_id, 'page' => request('page')]) }}"
            @if ($activeTab === 'history') data-lazy-pending="true" @endif
        >
            <div class="py-6 text-center text-sm text-gray-500">
                {{ $activeTab === 'history' ? 'Loading change history…' : 'Open this tab to load change history.' }}
            </div>
        </div>
    @endif

    <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-between">
        @if ($wizardMode)
            <a href="{{ route('employees.create', ['step' => 0]) }}" class="btn-secondary w-full sm:w-auto">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back
            </a>
            <button type="submit" class="btn-primary w-full sm:w-auto">
                Continue to Review
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        @else
            <a href="{{ $backUrl }}" class="btn-secondary w-full sm:w-auto">Cancel</a>
            <button type="submit" form="employee-form" class="btn-primary w-full sm:w-auto">
                {{ $isEdit ? 'Save Changes' : 'Create Employee' }}
            </button>
        @endif
    </div>
</form>

@if ($isEdit && ! $wizardMode)
    @include('employees.partials._credentials-modals', ['employee' => $employee])
    @include('employees.partials._loans-modals', ['employee' => $employee])
@endif
