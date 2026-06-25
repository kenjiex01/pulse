@php
    $primaryKey = 'timekeeping_policy_id';
@endphp

<div data-live-table-total-update data-total="{{ $policies->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[720px]">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($policies as $record)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ $loop->first ? 'font-medium text-gray-900' : 'text-gray-600' }}">
                                @if (($column['type'] ?? null) === 'boolean')
                                    <span class="{{ $record->{$column['key']} ? 'font-medium text-gray-900' : 'text-gray-500' }}">
                                        {{ $record->{$column['key']} ? 'Active' : 'Inactive' }}
                                    </span>
                                @else
                                    {{ $record->{$column['key']} ?: '—' }}
                                @endif
                            </td>
                        @endforeach
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('timekeeping-policy.update')
                                    <a
                                        href="{{ route(\App\Support\TimekeepingPolicy::routeName('tab'), ['policy' => $record->{$primaryKey}, 'tab' => 'tardiness-undertime']) }}"
                                        class="btn-icon"
                                        title="Configure policy"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </a>
                                    <button type="button" data-modal-open="timekeeping-policy-edit-{{ $record->{$primaryKey} }}" class="btn-icon" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="py-12 text-center text-sm text-gray-500">
                            No timekeeping policies found. Create one to get started.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="datatable-skolaris-pagination mt-4">
    @include('partials.data-table-pagination', ['paginator' => $policies])
</div>

<div data-live-table-modals>
    @foreach ($policies as $record)
        @can('timekeeping-policy.update')
            @include('partials.modal', [
                'id' => 'timekeeping-policy-edit-'.$record->{$primaryKey},
                'title' => 'Edit Timekeeping Policy',
                'description' => 'Update policy details',
                'open' => (string) ($openEditId ?? '') === (string) $record->{$primaryKey},
                'body' => view('timekeeping.policy._policy-form', [
                    'record' => $record,
                    'isEdit' => true,
                    'formContext' => 'edit-policy-'.$record->{$primaryKey},
                ])->render(),
            ])
        @endcan
    @endforeach
</div>
