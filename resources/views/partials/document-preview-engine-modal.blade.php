<div
    id="document-preview-engine-modal"
    class="modal-overlay hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="document-preview-engine-modal-title"
    data-document-preview-engine-status-url="{{ route('document-preview.engine.status') }}"
    data-document-preview-engine-install-url="{{ route('document-preview.engine.install') }}"
>
    <div class="modal-backdrop" data-modal-close aria-hidden="true"></div>
    <div class="modal-panel max-w-md">
        <div class="modal-header">
            <div class="min-w-0">
                <h2 id="document-preview-engine-modal-title" class="text-lg font-bold text-[#0B318F]">Document preview engine</h2>
                <p class="mt-0.5 text-sm text-gray-500">Needed once for Word/Excel files with charts or images.</p>
            </div>
            <button type="button" class="modal-close-btn" data-modal-close aria-label="Close">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body space-y-3">
            <p class="text-sm text-gray-700" data-document-preview-engine-message>
                Download LibreOffice into app storage (~290–360 MB). This is a one-time download and works offline afterward.
            </p>
            <p class="text-xs text-gray-500" data-document-preview-engine-meta></p>
            <div class="flex flex-wrap justify-end gap-2 pt-2">
                <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                <button type="button" class="btn-primary" data-document-preview-engine-install>Download &amp; install</button>
            </div>
        </div>
    </div>
</div>
