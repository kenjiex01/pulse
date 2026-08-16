@php
    $credentials = $isEdit
        ? ($employee->relationLoaded('credentials') ? $employee->credentials : $employee->credentials()->get())
        : collect();
@endphp

<div class="employee-tab-panel {{ ($wizardMode || $activeTab === 'credentials') ? '' : 'hidden' }}" @unless($wizardMode) data-employee-tab-panel="credentials" @endunless>
    <section class="employee-tab-section">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Credentials</h2>
                <p class="mt-1 text-sm text-gray-500">Uploaded credential files for this employee.</p>
            </div>
            @if ($isEdit)
                <button
                    type="button"
                    class="btn-secondary"
                    data-modal-open="employee-credential-add-modal"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add
                </button>
            @endif
        </div>

        @unless ($isEdit)
            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-600">
                Save the employee first to upload credential files. This tab stays available so you can return here after creating the record.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Document Type</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3">Uploaded</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($credentials as $credential)
                            @php
                                $credentialPreviewKind = app(\App\Services\EmployeeCredentialPreviewService::class)->kind($credential);
                                $credentialLabel = $credential->displayLabel();
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ $credential->documentType?->type_name ?? '—' }}
                                    @if ($credential->documentType?->is_required)
                                        <span class="ml-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">Required</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $credential->description ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $credential->original_filename }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $credential->humanFileSize() }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $credential->created_at?->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="btn-icon"
                                            title="Preview"
                                            data-modal-open="employee-credential-preview-modal"
                                            data-credential-preview-url="{{ route('employees.credentials.preview', [$employee, $credential]) }}"
                                            data-credential-content-url="{{ route('employees.credentials.content', [$employee, $credential]) }}"
                                            data-credential-preview-kind="{{ $credentialPreviewKind }}"
                                            data-credential-preview-title="{{ $credentialLabel }}"
                                            data-credential-preview-filename="{{ $credential->original_filename }}"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>
                                        <a
                                            href="{{ route('employees.credentials.download', [$employee, $credential]) }}"
                                            class="btn-icon"
                                            title="Download"
                                            data-no-loader
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        </a>
                                        <button
                                            type="submit"
                                            class="btn-icon text-red-600 hover:bg-red-50"
                                            title="Delete"
                                            form="destroy-employee-credential-{{ $credential->employee_credential_id }}"
                                            onclick="return confirm('Remove this credential file?');"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No credential files yet. Click Add to upload one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endunless
    </section>
</div>
