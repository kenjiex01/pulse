@php
    use App\Support\TimekeepingTemplate as TimekeepingTemplateSupport;
@endphp

<div data-live-table-total-update data-total="{{ $records->total() }}" hidden></div>

<div class="datatable-skolaris-table-wrap">
    <div class="overflow-x-auto">
        <table class="table-skolaris min-w-[760px]">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Template Name</th>
                    <th>Content</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $record->timekeeping_template_id }}</td>
                        <td class="text-gray-700">{{ $record->templateType?->template ?? '—' }}</td>
                        <td class="max-w-xs truncate text-gray-600" title="{{ $record->content }}">{{ $record->content }}</td>
                        <td>
                            @if ($record->is_active)
                                <span class="font-medium text-gray-900">Active</span>
                            @else
                                <span class="text-gray-500">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('timekeeping-policy.update')
                                    <button type="button" data-modal-open="timekeeping-template-edit-{{ $record->timekeeping_template_id }}" class="btn-icon" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <form method="POST" action="{{ route(TimekeepingTemplateSupport::routeName('toggle-status'), $record->timekeeping_template_id) }}" class="inline">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="btn-icon {{ $record->is_active ? '!text-green-600 hover:!text-green-700 hover:bg-green-50' : '!text-gray-400 hover:!text-gray-500 hover:bg-gray-50' }}"
                                            title="{{ $record->is_active ? 'Set inactive' : 'Set active' }}"
                                        >
                                            @if ($record->is_active)
                                                <svg class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="5"/><circle cx="17" cy="12" r="3" fill="currentColor" stroke="none"/></svg>
                                            @else
                                                <svg class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="5"/><circle cx="7" cy="12" r="3" fill="currentColor" stroke="none"/></svg>
                                            @endif
                                        </button>
                                    </form>
                                @endcan
                                @if (! $record->is_active)
                                    @can('timekeeping-policy.delete')
                                        <form
                                            method="POST"
                                            action="{{ route(TimekeepingTemplateSupport::routeName('destroy'), $record->timekeeping_template_id) }}"
                                            onsubmit="return confirm('Delete this inactive template?')"
                                            class="inline"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" title="Delete">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-sm text-gray-500">No templates found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="datatable-skolaris-pagination mt-4">
    @include('partials.data-table-pagination', ['paginator' => $records])
</div>

<div data-live-table-modals>
    @foreach ($records as $record)
        @can('timekeeping-policy.update')
            @include('partials.modal', [
                'id' => 'timekeeping-template-edit-'.$record->timekeeping_template_id,
                'title' => 'Edit Template',
                'description' => 'Update email notification template content',
                'panelClass' => 'modal-panel-lg',
                'open' => (string) ($openEditId ?? '') === (string) $record->timekeeping_template_id,
                'body' => view('timekeeping.templates._form', [
                    'record' => $record->loadMissing('templateType'),
                    'isEdit' => true,
                    'formContext' => 'edit-timekeeping-template-'.$record->timekeeping_template_id,
                    'templateTypes' => $templateTypes,
                ])->render(),
            ])
        @endcan
    @endforeach
</div>
