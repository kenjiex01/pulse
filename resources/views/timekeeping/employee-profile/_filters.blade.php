@php
    $currentEmploymentCategory = $employmentCategory ?? 'all';
@endphp

<div class="min-w-0">
    <label for="employee-profile-employment-category" class="form-label">Category</label>
    <select
        id="employee-profile-employment-category"
        name="employment_category"
        class="form-input w-full"
        data-live-table-filter
    >
        <option value="all" @selected($currentEmploymentCategory === 'all')>All</option>
        @foreach (\App\Models\EmployeeCategory::optionsForSelect() as $categoryCode => $categoryName)
            <option value="{{ $categoryCode }}" @selected($currentEmploymentCategory === $categoryCode)>{{ $categoryName }}</option>
        @endforeach
    </select>
</div>
