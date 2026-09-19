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

const closeMemoModal = (modal) => {
    if (!modal) {
        return;
    }

    modal.classList.add('hidden');

    if (document.querySelectorAll('.modal-overlay:not(.hidden)').length === 0) {
        document.body.classList.remove('modal-open');
    }
};

let pendingMemoSendForm = null;

const buildMemoSendConfirmMessage = (form, root) => {
    const kind = form.dataset.memoSendKind || 'single';

    if (kind === 'batch') {
        const count = root.querySelectorAll('[data-memo-row-checkbox]:checked').length;

        return count === 1
            ? 'Send memo to 1 selected employee?'
            : `Send memo to ${count} selected employees?`;
    }

    const employeeName = form.dataset.employeeName || 'this employee';

    if (kind === 'detail') {
        const checkedDates = form.querySelectorAll('[data-memo-detail-checkbox]:checked').length;
        const totalDates = form.querySelectorAll('[data-memo-detail-checkbox]').length;
        const dateCount = checkedDates > 0 ? checkedDates : totalDates;

        return dateCount === 1
            ? `Send memo to ${employeeName} for 1 day?`
            : `Send memo to ${employeeName} for ${dateCount} day(s)?`;
    }

    return `Send memo to ${employeeName}?`;
};

const wireSendConfirm = (root) => {
    const confirmModal = document.getElementById('memo-send-confirm-modal');
    const messageEl = confirmModal?.querySelector('[data-memo-send-confirm-message]');
    const proceedButton = confirmModal?.querySelector('[data-memo-send-confirm-proceed]');
    const cancelButton = confirmModal?.querySelector('[data-memo-send-confirm-cancel]');

    if (!confirmModal || !messageEl || !proceedButton || root.dataset.memoSendConfirmBound === 'true') {
        return;
    }

    root.dataset.memoSendConfirmBound = 'true';

    root.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-memo-send-form]')) {
            return;
        }

        if (form.dataset.memoSendConfirmed === 'true') {
            delete form.dataset.memoSendConfirmed;

            return;
        }

        event.preventDefault();
        event.stopPropagation();

        pendingMemoSendForm = form;
        messageEl.textContent = buildMemoSendConfirmMessage(form, root);
        openMemoModal(confirmModal);
    }, true);

    proceedButton.addEventListener('click', () => {
        if (!(pendingMemoSendForm instanceof HTMLFormElement)) {
            return;
        }

        const form = pendingMemoSendForm;
        pendingMemoSendForm = null;
        closeMemoModal(confirmModal);
        form.dataset.memoSendConfirmed = 'true';
        form.requestSubmit();
    });

    confirmModal.addEventListener('click', (event) => {
        if (event.target.closest('[data-modal-close]')) {
            pendingMemoSendForm = null;
        }
    });
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
    wireSendConfirm(root);

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
