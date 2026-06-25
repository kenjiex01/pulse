@php
    $currentStatus = $status ?: 'all';
    $currentCompliance = $compliance ?: 'all';
@endphp

<div class="w-full sm:w-48">
    <label for="employee-status" class="form-label">Status</label>
    <select id="employee-status" name="status" class="form-input" data-live-table-filter>
        <option value="all" @selected($currentStatus === 'all')>All</option>
        <option value="active" @selected($currentStatus === 'active')>Active</option>
        <option value="inactive" @selected($currentStatus === 'inactive')>Inactive</option>
    </select>
</div>
<div class="w-full sm:w-48">
    <label for="employee-compliance" class="form-label">Compliance</label>
    <select id="employee-compliance" name="compliance" class="form-input" data-live-table-filter>
        <option value="all" @selected($currentCompliance === 'all')>All</option>
        <option value="compliant" @selected($currentCompliance === 'compliant')>Compliant</option>
        <option value="pending" @selected($currentCompliance === 'pending')>Pending</option>
        <option value="overdue" @selected($currentCompliance === 'overdue')>Overdue</option>
        <option value="withheld" @selected($currentCompliance === 'withheld')>Withheld</option>
    </select>
</div>
