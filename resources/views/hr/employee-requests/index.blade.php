@extends('layouts.app')

@section('title', 'Employee Request — '.config('app.name'))

@section('content')
    @include('partials.flash')

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-[#0B318F] sm:text-2xl lg:text-3xl">Employee Request</h1>
            <p class="mt-1 text-xs text-gray-600 sm:text-sm">Pull approved forms from Skolaris. Amendments replace that day's time logs. Job orders are filed as overtime.</p>
        </div>
        <form method="POST" action="{{ route('employee-requests.pull') }}">
            @csrf
            <button type="submit" class="btn-primary w-full sm:w-auto">Pull approved</button>
        </form>
    </div>

    <form method="GET" action="{{ route('employee-requests.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <input
            type="search"
            name="search"
            value="{{ $search }}"
            placeholder="Request no., prepared by, department..."
            class="form-input w-full sm:max-w-md"
        >
        <select name="type" class="form-input w-full sm:w-56">
            <option value="">All types</option>
            @foreach ($types as $option)
                <option value="{{ $option->request_type_code }}" @selected($type === $option->request_type_code)>{{ $option->request_type_name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-secondary w-full sm:w-auto">Load</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-[#0B318F] text-left text-xs font-semibold uppercase tracking-wide text-white">
                    <tr>
                        <th class="px-4 py-3">Request</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Prepared by</th>
                        <th class="px-4 py-3">Approvers</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Effect</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($requests as $row)
                        @php
                            $approverNames = collect($row->approvers ?? [])->pluck('name')->filter()->implode(', ');
                        @endphp
                        <tr>
                            <td class="px-4 py-3 align-top">
                                <div class="font-semibold text-[#0B318F]">{{ $row->request_number }}</div>
                                <div class="text-xs text-gray-500">{{ $row->campus_name }}</div>
                            </td>
                            <td class="px-4 py-3 align-top">{{ $row->request_type_name }}</td>
                            <td class="px-4 py-3 align-top">
                                <div>{{ $row->prepared_by_name }}</div>
                                <div class="text-xs text-gray-500">{{ $row->prepared_by_employee_number }}</div>
                            </td>
                            <td class="px-4 py-3 align-top text-gray-700">{{ $approverNames !== '' ? $approverNames : '—' }}</td>
                            <td class="px-4 py-3 align-top">{{ $row->department ?: '—' }}</td>
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Approved</span>
                            </td>
                            <td class="px-4 py-3 align-top text-gray-700">{{ $row->apply_summary ?: '—' }}</td>
                            <td class="px-4 py-3 align-top text-right">
                                <button type="button" class="btn-secondary" data-modal-open="employee-request-view-{{ $row->employee_request_id }}">View</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">No approved forms pulled yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>

    @foreach ($requests as $row)
        @include('partials.modal', [
            'id' => 'employee-request-view-'.$row->employee_request_id,
            'title' => $row->request_number,
            'description' => $row->request_type_name,
            'panelClass' => 'max-w-2xl',
            'body' => view('hr.employee-requests._show', ['request' => $row])->render(),
        ])
    @endforeach
@endsection
