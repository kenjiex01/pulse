@php
    $currentStatus = $status ?: 'all';
    $currentCompliance = $compliance ?: 'all';
    $currentCampus = $campus ?: 'all';
    $currentDeptCollege = $deptCollege ?: 'all';
    $currentEmploymentCategory = $employmentCategory ?: 'all';
@endphp

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    <div class="min-w-0">
        <label for="employee-status" class="form-label">Status</label>
        <select id="employee-status" name="status" class="form-input w-full" data-live-table-filter>
            <option value="all" @selected($currentStatus === 'all')>All</option>
            <option value="active" @selected($currentStatus === 'active')>Active</option>
            <option value="inactive" @selected($currentStatus === 'inactive')>Inactive</option>
        </select>
    </div>
    <div class="min-w-0">
        <label for="employee-compliance" class="form-label">Compliance</label>
        <select id="employee-compliance" name="compliance" class="form-input w-full" data-live-table-filter>
            <option value="all" @selected($currentCompliance === 'all')>All</option>
            <option value="compliant" @selected($currentCompliance === 'compliant')>Compliant</option>
            <option value="pending" @selected($currentCompliance === 'pending')>Pending</option>
        </select>
    </div>
    <div class="min-w-0">
        <label for="employee-campus" class="form-label">Campus</label>
        <select id="employee-campus" name="campus" class="form-input w-full" data-live-table-filter>
            <option value="all" @selected($currentCampus === 'all')>All</option>
            @foreach ($campuses as $campusOption)
                <option value="{{ $campusOption->campus_id }}" @selected($currentCampus === (string) $campusOption->campus_id)>
                    {{ $campusOption->campus_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="min-w-0">
        <label for="employee-dept-college" class="form-label">Dept / College</label>
        <select id="employee-dept-college" name="dept_college" class="form-input w-full" data-live-table-filter>
            <option value="all" @selected($currentDeptCollege === 'all')>All</option>
            @if ($colleges->isNotEmpty())
                <optgroup label="Colleges">
                    @foreach ($colleges as $college)
                        @php $collegeValue = 'college:'.$college->college_name; @endphp
                        <option value="{{ $collegeValue }}" @selected($currentDeptCollege === $collegeValue)>
                            {{ $college->college_name }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
            @if ($employeeDepartments->isNotEmpty())
                <optgroup label="Departments">
                    @foreach ($employeeDepartments as $employeeDepartment)
                        @php $departmentValue = 'department:'.$employeeDepartment->department_name; @endphp
                        <option value="{{ $departmentValue }}" @selected($currentDeptCollege === $departmentValue)>
                            {{ $employeeDepartment->department_name }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        </select>
    </div>
    <div class="min-w-0">
        <label for="employee-employment-category" class="form-label">Employment Category</label>
        <select id="employee-employment-category" name="employment_category" class="form-input w-full" data-live-table-filter>
            <option value="all" @selected($currentEmploymentCategory === 'all')>All</option>
            <option value="{{ \App\Models\EmployeeEmploymentInformation::TYPE_FACULTY }}" @selected($currentEmploymentCategory === \App\Models\EmployeeEmploymentInformation::TYPE_FACULTY)>Faculty</option>
            <option value="{{ \App\Models\EmployeeEmploymentInformation::TYPE_STAFF }}" @selected($currentEmploymentCategory === \App\Models\EmployeeEmploymentInformation::TYPE_STAFF)>Staff</option>
        </select>
    </div>
</div>
