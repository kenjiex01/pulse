<dl class="space-y-3 text-sm">
    <div>
        <dt class="font-medium text-gray-500">Original date</dt>
        <dd class="mt-0.5 text-gray-900">{{ $log->original_dt_datetime?->format('M j, Y') ?? '—' }}</dd>
    </div>
    <div>
        <dt class="font-medium text-gray-500">Original time</dt>
        <dd class="mt-0.5 text-gray-900">{{ $log->original_dt_datetime?->format('g:i A') ?? '—' }}</dd>
    </div>
    <div>
        <dt class="font-medium text-gray-500">Original type</dt>
        <dd class="mt-0.5 text-gray-900">{{ $log->original_is_in ? 'Time In' : 'Time Out' }}</dd>
    </div>
    <div>
        <dt class="font-medium text-gray-500">Current values</dt>
        <dd class="mt-0.5 text-gray-900">
            {{ $log->dt_datetime?->format('M j, Y g:i A') ?? '—' }}
            · {{ $log->is_in ? 'Time In' : 'Time Out' }}
        </dd>
    </div>
    @if ($log->edited_at)
        <div>
            <dt class="font-medium text-gray-500">Last edited</dt>
            <dd class="mt-0.5 text-gray-900">{{ $log->edited_at->format('M j, Y g:i A') }}</dd>
        </div>
    @endif
</dl>

<div class="mt-4 flex justify-end border-t border-gray-100 pt-4">
    <button type="button" class="btn-secondary" data-modal-close>Close</button>
</div>
