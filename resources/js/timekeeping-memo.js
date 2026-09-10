import { initCompanyDocumentPreview } from './company-document-designer.js';

const openMemoModal = (modal) => {
    if (!modal) {
        return;
    }

    document.querySelectorAll('.modal-overlay:not(.hidden)').forEach((openModalEl) => {
        if (openModalEl !== modal) {
            openModalEl.classList.add('hidden');
        }
    });

    modal.classList.remove('hidden');
    document.body.classList.add('modal-open');
};

const updateBatchButtonState = (root) => {
    const button = root.querySelector('[data-memo-batch-send]');
    const checkboxes = root.querySelectorAll('[data-memo-row-checkbox]');
    if (!button) {
        return;
    }

    const anyChecked = Array.from(checkboxes).some((checkbox) => checkbox.checked);
    button.disabled = !anyChecked;
};

const wireBatchForm = (root) => {
    const form = root.querySelector('[data-memo-batch-form]');
    if (!form || form.dataset.memoBatchBound === 'true') {
        return;
    }

    form.dataset.memoBatchBound = 'true';

    root.querySelector('[data-memo-select-all]')?.addEventListener('change', (event) => {
        const checked = event.target.checked;
        root.querySelectorAll('[data-memo-row-checkbox]').forEach((checkbox) => {
            checkbox.checked = checked;
        });
        updateBatchButtonState(root);
    });

    root.querySelectorAll('[data-memo-row-checkbox]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => updateBatchButtonState(root));
    });

    updateBatchButtonState(root);
};

const wireDetailCheckboxes = (container) => {
    const selectAll = container.querySelector('[data-memo-detail-select-all]');
    const checkboxes = container.querySelectorAll('[data-memo-detail-checkbox]');

    selectAll?.addEventListener('change', (event) => {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = event.target.checked;
        });
    });
};

const buildDetailPreviewUrl = (baseUrl, container) => {
    const url = new URL(baseUrl, window.location.origin);
    const form = container.querySelector('[data-memo-detail-send-form]');

    if (!form) {
        return url.toString();
    }

    const checked = form.querySelectorAll('[data-memo-detail-checkbox]:checked');
    checked.forEach((checkbox) => {
        url.searchParams.append('work_dates[]', checkbox.value);
    });

    return url.toString();
};

const openDetailsModal = async (button, host) => {
    const url = button.dataset.url;
    if (!url || !host) {
        return;
    }

    host.innerHTML = '<div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500">Loading...</div>';

    const response = await fetch(url, {
        headers: {
            Accept: 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        host.innerHTML = '<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">Unable to load details.</div>';
        return;
    }

    host.innerHTML = await response.text();
    wireDetailCheckboxes(host);
    openMemoModal(document.getElementById('memo-details-modal'));
};

const openPreviewModal = async (url, host) => {
    if (!url || !host) {
        return;
    }

    host.innerHTML = '<div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500">Loading preview...</div>';

    const response = await fetch(url, {
        headers: {
            Accept: 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        host.innerHTML = '<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">Unable to load memo preview.</div>';
        return;
    }

    host.innerHTML = await response.text();
    host.querySelectorAll('[data-company-document-preview]:not([data-preview-static])').forEach(initCompanyDocumentPreview);
    openMemoModal(document.getElementById('memo-preview-modal'));
};

export const reinitTimekeepingMemoTable = (container) => {
    const root = container?.closest('[data-memo-root]') ?? document.querySelector('[data-memo-root]');
    wireBatchForm(root);
};

export const initTimekeepingMemo = () => {
    const root = document.querySelector('[data-memo-root]');
    if (!root) {
        return;
    }

    wireBatchForm(root);

    if (root.dataset.memoDelegated !== 'true') {
        root.dataset.memoDelegated = 'true';

        root.addEventListener('click', (event) => {
            const viewButton = event.target.closest('[data-memo-view]');
            if (viewButton) {
                event.preventDefault();
                openDetailsModal(viewButton, document.getElementById('memo-details-modal-host'));
                return;
            }

            const previewButton = event.target.closest('[data-memo-preview]');
            if (previewButton) {
                event.preventDefault();
                openPreviewModal(previewButton.dataset.url, document.getElementById('memo-preview-modal-host'));
            }
        });
    }

    const detailsHost = document.getElementById('memo-details-modal-host');
    if (detailsHost && detailsHost.dataset.memoDetailDelegated !== 'true') {
        detailsHost.dataset.memoDetailDelegated = 'true';

        detailsHost.addEventListener('click', (event) => {
            const previewButton = event.target.closest('[data-memo-detail-preview]');
            if (!previewButton) {
                return;
            }

            event.preventDefault();
            const url = buildDetailPreviewUrl(previewButton.dataset.url, detailsHost);
            openPreviewModal(url, document.getElementById('memo-preview-modal-host'));
        });
    }
};
