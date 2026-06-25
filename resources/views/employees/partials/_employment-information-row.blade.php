@php
    $index = $index ?? 0;
    $record = $record ?? [];
    $isHybrid = $isHybrid ?? false;
    $categoryLabels = ['faculty' => 'Faculty', 'staff' => 'Staff', 'admin' => 'Admin'];
    $fixedCategory = $fixedCategory ?? null;
    $selectedCategory = $fixedCategory ?: old("employment_informations.$index.user_type", $record['user_type'] ?? '');
    $panelTitle = $fixedCategory
        ? ($categoryLabels[$fixedCategory] ?? ucfirst($fixedCategory)).' Employment'
        : 'Employment Information';
@endphp

<div
    class="rounded-lg border border-gray-200 bg-gray-50/70 p-4"
    data-employment-info-panel
    data-employment-index="{{ $index }}"
    @if ($fixedCategory) data-fixed-category="{{ $fixedCategory }}" @endif
>
    <h3 class="mb-4 text-sm font-semibold text-gray-900">{{ $panelTitle }}</h3>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="form-label">Category (User Type) <span class="text-red-500">*</span></label>
            @if ($fixedCategory)
                <input type="hidden" name="employment_informations[{{ $index }}][user_type]" value="{{ $fixedCategory }}" data-employment-category-input>
                <p class="mt-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-900">{{ $categoryLabels[$fixedCategory] ?? ucfirst($fixedCategory) }}</p>
                <p class="mt-1 text-xs text-gray-500">Fixed for hybrid employment.</p>
            @else
                <select name="employment_informations[{{ $index }}][user_type]" class="form-input" data-employment-category-input required>
                    <option value="">Select Category</option>
                    @foreach ($categoryLabels as $value => $label)
                        <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @endif
            @error("employment_informations.$index.user_type")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2">
            <label class="form-label">Position</label>
            <select name="employment_informations[{{ $index }}][position]" class="form-input">
                <option value="">Select Position</option>
                @foreach ($formOptions['positions'] as $position)
                    <option value="{{ $position->position_name }}" @selected(old("employment_informations.$index.position", $record['position'] ?? '') === $position->position_name)>
                        {{ $position->position_name }}
                    </option>
                @endforeach
                @php $legacyPosition = old("employment_informations.$index.position", $record['position'] ?? ''); @endphp
                @if ($legacyPosition && ! $formOptions['positions']->contains('position_name', $legacyPosition))
                    <option value="{{ $legacyPosition }}" selected>{{ $legacyPosition }} (Legacy)</option>
                @endif
            </select>
        </div>

        <div>
            <label class="form-label">Designation</label>
            <select name="employment_informations[{{ $index }}][designation]" class="form-input">
                <option value="">Select Designation</option>
                @foreach ($formOptions['designations'] as $designation)
                    <option value="{{ $designation->designation_name }}" @selected(old("employment_informations.$index.designation", $record['designation'] ?? '') === $designation->designation_name)>
                        {{ $designation->designation_name }}
                    </option>
                @endforeach
                @php $legacyDesignation = old("employment_informations.$index.designation", $record['designation'] ?? ''); @endphp
                @if ($legacyDesignation && ! $formOptions['designations']->contains('designation_name', $legacyDesignation))
                    <option value="{{ $legacyDesignation }}" selected>{{ $legacyDesignation }} (Legacy)</option>
                @endif
            </select>
        </div>

        <div>
            <label class="form-label">Rank</label>
            <select name="employment_informations[{{ $index }}][rank]" class="form-input">
                <option value="">Select Rank</option>
                @foreach ($formOptions['ranks'] as $rank)
                    <option value="{{ $rank->rank_name }}" @selected(old("employment_informations.$index.rank", $record['rank'] ?? '') === $rank->rank_name)>
                        {{ $rank->rank_name }}
                    </option>
                @endforeach
                @php $legacyRank = old("employment_informations.$index.rank", $record['rank'] ?? ''); @endphp
                @if ($legacyRank && ! $formOptions['ranks']->contains('rank_name', $legacyRank))
                    <option value="{{ $legacyRank }}" selected>{{ $legacyRank }} (Legacy)</option>
                @endif
            </select>
        </div>

        <div>
            <label class="form-label">Employment Type</label>
            <select name="employment_informations[{{ $index }}][employment_type]" class="form-input">
                <option value="">Select Employment Type</option>
                @foreach ($formOptions['employmentTypes'] as $employmentType)
                    <option value="{{ $employmentType->type_name }}" @selected(old("employment_informations.$index.employment_type", $record['employment_type'] ?? '') === $employmentType->type_name)>
                        {{ $employmentType->type_name }}
                    </option>
                @endforeach
                @php $legacyEmploymentType = old("employment_informations.$index.employment_type", $record['employment_type'] ?? ''); @endphp
                @if ($legacyEmploymentType && ! $formOptions['employmentTypes']->contains('type_name', $legacyEmploymentType))
                    <option value="{{ $legacyEmploymentType }}" selected>{{ $legacyEmploymentType }} (Legacy)</option>
                @endif
            </select>
        </div>

        <div>
            <label class="form-label">Hire Date</label>
            <input
                type="date"
                name="employment_informations[{{ $index }}][hire_date]"
                value="{{ old("employment_informations.$index.hire_date", isset($record['hire_date']) ? (\Illuminate\Support\Carbon::parse($record['hire_date'])->format('Y-m-d')) : '') }}"
                class="form-input"
            >
        </div>
    </div>
</div>
