@php
    $extended = old('extended_profile', $extended ?? []);
    $familyBackground = $extended['family_background'] ?? [];
    $familyMembers = array_values($extended['family_members'] ?? []);
    $employmentHistory = array_values($extended['employment_history'] ?? []);
    $exams = array_values($extended['exams'] ?? []);
    $seminars = array_values($extended['seminars'] ?? []);
    $awards = array_values($extended['awards'] ?? []);
    $references = array_values($extended['references'] ?? []);
    $skillsProfile = $extended['skills_profile'] ?? [];
    $generalInformation = $extended['general_information'] ?? [];
    $skills = $skillsProfile['skills'] ?? [];
    $familyGroups = [
        'brother' => 'Brothers',
        'sister' => 'Sisters',
        'child' => 'Children',
    ];
    $collectionCounts = [
        'family_members' => count($familyMembers),
        'employment_history' => count($employmentHistory),
        'exams' => count($exams),
        'seminars' => count($seminars),
        'awards' => count($awards),
        'references' => count($references),
    ];
@endphp

<div class="space-y-5" data-extended-profile-root data-extended-indexes='@json($collectionCounts)'>
    <div>
        <h2 class="text-lg font-semibold text-gray-900">Extended Profile</h2>
        <p class="text-xs text-gray-500">Dynamic fields grouped by category and saved in structured format.</p>
    </div>

    <details class="rounded-md border border-gray-200 p-4" open>
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Family Background</summary>
        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach ([
                'father_name' => "Father's Name",
                'mother_name' => "Mother's Name",
                'father_company' => 'Father Company',
                'mother_company' => 'Mother Company',
                'father_occupation' => 'Father Occupation',
                'mother_occupation' => 'Mother Occupation',
                'parents_address' => "Parent's Address",
                'spouse_last_name' => 'Spouse Last Name',
                'spouse_first_name' => 'Spouse First Name',
                'spouse_middle_name' => 'Spouse Middle Name',
                'spouse_occupation' => 'Spouse Occupation',
                'spouse_company' => 'Spouse Company',
            ] as $field => $label)
                <div class="{{ $field === 'parents_address' ? 'md:col-span-2' : '' }}">
                    <label class="form-label">{{ $label }}</label>
                    <input type="text" name="extended_profile[family_background][{{ $field }}]" value="{{ old("extended_profile.family_background.$field", $familyBackground[$field] ?? '') }}" class="form-input">
                </div>
            @endforeach
            <div>
                <label class="form-label">Date Married</label>
                <input type="date" name="extended_profile[family_background][date_married]" value="{{ old('extended_profile.family_background.date_married', $familyBackground['date_married'] ?? '') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Number of Children</label>
                <input type="number" min="0" name="extended_profile[family_background][number_of_children]" value="{{ old('extended_profile.family_background.number_of_children', $familyBackground['number_of_children'] ?? '') }}" class="form-input">
            </div>
        </div>
    </details>

    <details class="rounded-md border border-gray-200 p-4" open>
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Family Members</summary>
        <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach ($familyGroups as $type => $label)
                <div class="space-y-3" data-extended-group="family_members" data-relationship-type="{{ $type }}">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-sm font-medium text-gray-700">
                            {{ $label }}
                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Full name required</span>
                        </h4>
                        <button type="button" class="btn-secondary !px-2 !py-1.5 text-xs" data-extended-add="family_members" data-relationship-type="{{ $type }}">Add</button>
                    </div>
                    <div class="space-y-3" data-extended-rows="family_members" data-relationship-type="{{ $type }}">
                        @foreach ($familyMembers as $index => $member)
                            @if (($member['relationship_type'] ?? '') === $type)
                                @include('employees.partials._extended-family-member-row', ['index' => $index, 'member' => $member, 'type' => $type])
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </details>

    <details class="rounded-md border border-gray-200 p-4" open>
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Employment History</summary>
        <div class="mt-3 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Company + Position required</span>
                <button type="button" class="btn-secondary !px-2 !py-1.5 text-xs" data-extended-add="employment_history">Add</button>
            </div>
            <div class="space-y-3" data-extended-rows="employment_history">
                @foreach ($employmentHistory as $index => $item)
                    @include('employees.partials._extended-employment-history-row', ['index' => $index, 'item' => $item])
                @endforeach
            </div>
        </div>
    </details>

    <details class="rounded-md border border-gray-200 p-4" open>
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Exams, Seminars, Awards, References</summary>
        <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ([
                'exams' => ['label' => 'Exams', 'hint' => 'Title required', 'fields' => ['exam_type' => 'Exam Type', 'title' => 'Title', 'date_taken' => 'Date Taken', 'rating' => 'Rating'], 'types' => ['date_taken' => 'date']],
                'seminars' => ['label' => 'Seminars', 'hint' => 'Course/topic required', 'fields' => ['inclusive_dates' => 'Inclusive Dates', 'course_topic' => 'Course Topic', 'conducted_by' => 'Conducted By', 'venue' => 'Venue'], 'types' => []],
                'awards' => ['label' => 'Awards', 'hint' => 'Title required', 'fields' => ['title' => 'Title', 'sponsoring_institution' => 'Sponsoring Institution', 'award_year' => 'Award Year'], 'types' => []],
                'references' => ['label' => 'References', 'hint' => 'Name + Contact required', 'fields' => ['name' => 'Name', 'company_employed_with' => 'Company', 'occupation_position' => 'Occupation/Position', 'contact_numbers' => 'Contact Numbers'], 'types' => []],
            ] as $collection => $config)
                <div class="space-y-2 rounded-md border border-gray-200 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $config['label'] }}</h3>
                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">{{ $config['hint'] }}</span>
                        <button type="button" class="btn-secondary !px-2 !py-1.5 text-xs" data-extended-add="{{ $collection }}">Add</button>
                    </div>
                    <div class="space-y-2" data-extended-rows="{{ $collection }}">
                        @foreach ($$collection as $index => $item)
                            @include('employees.partials._extended-collection-row', [
                                'collection' => $collection,
                                'index' => $index,
                                'item' => $item,
                                'fields' => $config['fields'],
                                'types' => $config['types'],
                            ])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </details>

    <details class="rounded-md border border-gray-200 p-4" open>
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Skills Profile</summary>
        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
                <label class="form-label">Computer Skills (comma-separated)</label>
                <input type="text" name="extended_profile[skills_profile][computer_text]" value="{{ old('extended_profile.skills_profile.computer_text', is_array($skills['computer'] ?? null) ? implode(', ', $skills['computer']) : '') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Technical Skills (comma-separated)</label>
                <input type="text" name="extended_profile[skills_profile][technical_text]" value="{{ old('extended_profile.skills_profile.technical_text', is_array($skills['technical'] ?? null) ? implode(', ', $skills['technical']) : '') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Talents (comma-separated)</label>
                <input type="text" name="extended_profile[skills_profile][talents_text]" value="{{ old('extended_profile.skills_profile.talents_text', is_array($skills['talents'] ?? null) ? implode(', ', $skills['talents']) : '') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Other Skills</label>
                <input type="text" name="extended_profile[skills_profile][other_skills]" value="{{ old('extended_profile.skills_profile.other_skills', $skillsProfile['other_skills'] ?? '') }}" class="form-input">
            </div>
        </div>
    </details>

    <details class="rounded-md border border-gray-200 p-4" open>
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">General Information</summary>
        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach ([
                'has_physical_defect_or_disability' => 'Physical defect/disability',
                'has_major_operations_or_illness' => 'Major operations/illness',
                'has_nervous_disorder' => 'Nervous disorder',
                'has_relative_in_icct' => 'Relative in ICCT',
                'has_been_suspended_or_discharged' => 'Suspended/discharged',
                'has_labor_union_participation' => 'Labor union participation',
                'has_administrative_civil_criminal_case' => 'Admin/civil/criminal case',
            ] as $field => $label)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="extended_profile[general_information][{{ $field }}]" value="0">
                    <input type="checkbox" name="extended_profile[general_information][{{ $field }}]" value="1" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" @checked(old("extended_profile.general_information.$field", $generalInformation[$field] ?? false))>
                    {{ $label }}
                </label>
            @endforeach
            <div>
                <label class="form-label">Relative Name</label>
                <input type="text" name="extended_profile[general_information][relative_in_icct_name]" value="{{ old('extended_profile.general_information.relative_in_icct_name', $generalInformation['relative_in_icct_name'] ?? '') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Relative Relationship</label>
                <input type="text" name="extended_profile[general_information][relative_in_icct_relationship]" value="{{ old('extended_profile.general_information.relative_in_icct_relationship', $generalInformation['relative_in_icct_relationship'] ?? '') }}" class="form-input">
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Organization Memberships</label>
                <input type="text" name="extended_profile[general_information][organization_memberships]" value="{{ old('extended_profile.general_information.organization_memberships', $generalInformation['organization_memberships'] ?? '') }}" class="form-input">
            </div>
        </div>
    </details>

    <template data-extended-template="family_members">
        @include('employees.partials._extended-family-member-row', ['index' => '__INDEX__', 'member' => [], 'type' => '__TYPE__'])
    </template>
    <template data-extended-template="employment_history">
        @include('employees.partials._extended-employment-history-row', ['index' => '__INDEX__', 'item' => []])
    </template>
    <template data-extended-template="exams">
        @include('employees.partials._extended-collection-row', [
            'collection' => 'exams',
            'index' => '__INDEX__',
            'item' => [],
            'fields' => ['exam_type' => 'Exam Type', 'title' => 'Title', 'date_taken' => 'Date Taken', 'rating' => 'Rating'],
            'types' => ['date_taken' => 'date'],
        ])
    </template>
    <template data-extended-template="seminars">
        @include('employees.partials._extended-collection-row', [
            'collection' => 'seminars',
            'index' => '__INDEX__',
            'item' => [],
            'fields' => ['inclusive_dates' => 'Inclusive Dates', 'course_topic' => 'Course Topic', 'conducted_by' => 'Conducted By', 'venue' => 'Venue'],
            'types' => [],
        ])
    </template>
    <template data-extended-template="awards">
        @include('employees.partials._extended-collection-row', [
            'collection' => 'awards',
            'index' => '__INDEX__',
            'item' => [],
            'fields' => ['title' => 'Title', 'sponsoring_institution' => 'Sponsoring Institution', 'award_year' => 'Award Year'],
            'types' => [],
        ])
    </template>
    <template data-extended-template="references">
        @include('employees.partials._extended-collection-row', [
            'collection' => 'references',
            'index' => '__INDEX__',
            'item' => [],
            'fields' => ['name' => 'Name', 'company_employed_with' => 'Company', 'occupation_position' => 'Occupation/Position', 'contact_numbers' => 'Contact Numbers'],
            'types' => [],
        ])
    </template>
</div>
