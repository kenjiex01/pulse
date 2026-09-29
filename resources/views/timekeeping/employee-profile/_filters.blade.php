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
        <option value="{{ \App\Models\EmployeeEmploymentInformation::TYPE_STAFF }}" @selected($currentEmploymentCategory === \App\Models\EmployeeEmploymentInformation::TYPE_STAFF)>Staff</option>
        <option value="{{ \App\Models\EmployeeEmploymentInformation::TYPE_FACULTY }}" @selected($currentEmploymentCategory === \App\Models\EmployeeEmploymentInformation::TYPE_FACULTY)>Faculty</option>
    </select>
</div>
