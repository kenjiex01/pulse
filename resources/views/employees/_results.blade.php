<div data-live-table-total-update data-total="{{ $employees->total() }}" hidden></div>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-4">
    @include('partials.stat-card', [
        'index' => 0,
        'title' => 'Total Employees',
        'value' => number_format($stats['total']),
        'icon' => '<svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    ])
    @include('partials.stat-card', [
        'index' => 1,
        'title' => 'Active',
        'value' => number_format($stats['active']),
        'icon' => '<svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    ])
    @include('partials.stat-card', [
        'index' => 2,
        'title' => 'Inactive',
        'value' => number_format($stats['inactive']),
        'icon' => '<svg class="h-5 w-5 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    ])
    @include('partials.stat-card', [
        'index' => 3,
        'title' => 'Pending Compliance',
        'value' => number_format($stats['pending_compliance']),
        'icon' => '<svg class="h-5 w-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    ])
</div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[960px]">
            <thead>
                <tr>
                    <th>Employee #</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Position</th>
                    <th>Dept/College</th>
                    <th>Compliance</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $employee->employee_number }}</td>
                        <td class="text-gray-600">{{ $employee->full_name }}</td>
                        <td class="text-gray-600">{{ $employee->email ?: '—' }}</td>
                        <td class="text-gray-600">{{ $employee->position ?: '—' }}</td>
                        <td class="text-gray-600">{{ $employee->college ?: $employee->department ?: ($employee->campus_name ?: '—') }}</td>
                        <td>@include('employees._compliance-badge', ['status' => $employee->compliance_status])</td>
                        <td>
                            <span class="capitalize {{ $employee->employment_status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                {{ $employee->employment_status }}
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('view', $employee)
                                    <a href="{{ route('employees.show', $employee) }}" class="btn-icon" title="View" data-no-loader>
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                @endcan
                                @can('update', $employee)
                                    <a href="{{ route('employees.edit', $employee) }}" class="btn-icon" title="Edit" data-no-loader>
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                @endcan
                                @can('delete', $employee)
                                    <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('Are you sure you want to delete this employee?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" title="Delete">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center">
                            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <p class="text-sm font-medium text-gray-500">No employees found</p>
                            <p class="mt-1 text-xs text-gray-400">
                                @can('create', \App\Models\Employee::class)
                                    <a href="{{ route('employees.create') }}" class="text-[#00A3E6] hover:underline">Add an employee</a> to get started.
                                @else
                                    No employee records available.
                                @endcan
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="datatable-skolaris-pagination mt-4">
    @include('partials.data-table-pagination', ['paginator' => $employees])
</div>
