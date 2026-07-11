@php
    $config = config("timekeeping_policy.equivalents.$type");
    $primaryKey = $config['primary_key'];
    $modalPrefix = "timekeeping-equivalent-$type";
    $openCreate = ($openCreateEquivalent ?? null) === $type
        || ((optional($errors)->any() && old('form_context') === "create-$type") || request()->boolean('create') && request('equivalent') === $type);
    $leaveTypeId = $leaveTypeId ?? null;
@endphp

<div class="mt-6 rounded-xl border border-gray-200 bg-white p-6 @if(($type ?? '') === 'breaks') !mt-4 !border-0 !p-0 !shadow-none @endif">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">{{ $config['name'] }}s</h3>
            @if ($type === 'tardiness')
                <p class="mt-1 text-xs text-gray-500">Late conversion rules — e.g. 1–5 min → 5 min, 16+ min → absent.</p>
            @endif
        </div>
        @can('timekeeping-policy.create')
            <button type="button" class="btn-secondary" data-modal-open="{{ $modalPrefix }}-create">
                Add Equivalent
            </button>
        @endcan
    </div>

    <div class="datatable-skolaris-table-wrap">
        <div class="overflow-x-auto">
            <table class="table-skolaris min-w-[640px]">
                <thead>
                    <tr>
                        <th>From (minutes)</th>
                        <th>To (minutes)</th>
                        <th>Equivalent (minutes)</th>
                        @if ($config['supports_marks_absent'] ?? false)
                            <th>Absent</th>
                        @endif
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td class="font-medium text-gray-900">{{ \App\Support\TimekeepingPolicy::formatMinutes($record->time_from) }}</td>
                            <td class="text-gray-600">{{ \App\Support\TimekeepingPolicy::formatMinutes($record->time_to) }}</td>
                            <td class="text-gray-600">
                                @if (($config['supports_marks_absent'] ?? false) && $record->marks_absent)
                                    <span class="text-red-600">Absent</span>
                                @else
                                    {{ \App\Support\TimekeepingPolicy::formatMinutes($record->equivalent) }}
                                @endif
                            </td>
                            @if ($config['supports_marks_absent'] ?? false)
                                <td class="text-gray-600">{{ $record->marks_absent ? 'Yes' : '—' }}</td>
                            @endif
                            <td>
                                <div class="flex items-center justify-end gap-1.5">
                                    @can('timekeeping-policy.update')
                                        <button type="button" data-modal-open="{{ $modalPrefix }}-edit-{{ $record->{$primaryKey} }}" class="btn-icon" title="Edit">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                    @endcan
                                    @can('timekeeping-policy.delete')
                                        <form method="POST" action="{{ route(\App\Support\TimekeepingPolicy::routeName('equivalents.destroy'), ['policy' => $policy->timekeeping_policy_id, 'type' => $type, 'record' => $record->{$primaryKey}]) }}" onsubmit="return confirm('Delete this equivalent?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            @if ($leaveTypeId)
                                                <input type="hidden" name="leave_type_id" value="{{ $leaveTypeId }}">
                                            @endif
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
                            <td colspan="{{ ($config['supports_marks_absent'] ?? false) ? 5 : 4 }}" class="py-8 text-center text-sm text-gray-500">No equivalents defined yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@can('timekeeping-policy.create')
    @include('partials.modal', [
        'id' => "$modalPrefix-create",
        'title' => 'Add '.$config['name'],
        'description' => 'Create a new equivalent range',
        'open' => $openCreate,
        'body' => view('timekeeping.policy._equivalent-form', [
            'policy' => $policy,
            'type' => $type,
            'config' => $config,
            'record' => null,
            'isEdit' => false,
            'formContext' => "create-$type",
            'leaveTypeId' => $leaveTypeId,
            'availableLeaveTypes' => $availableLeaveTypes ?? [],
        ])->render(),
    ])
@endcan

@foreach ($records as $record)
    @can('timekeeping-policy.update')
        @include('partials.modal', [
            'id' => "$modalPrefix-edit-{$record->{$primaryKey}}",
            'title' => 'Edit '.$config['name'],
            'description' => 'Update equivalent range',
            'open' => ($openEditEquivalent['type'] ?? null) === $type && (string) ($openEditEquivalent['id'] ?? '') === (string) $record->{$primaryKey},
            'body' => view('timekeeping.policy._equivalent-form', [
                'policy' => $policy,
                'type' => $type,
                'config' => $config,
                'record' => $record,
                'isEdit' => true,
                'formContext' => "edit-$type-{$record->{$primaryKey}}",
                'leaveTypeId' => $leaveTypeId ?? $record->leave_type_id ?? null,
                'availableLeaveTypes' => $availableLeaveTypes ?? [],
            ])->render(),
        ])
    @endcan
@endforeach
