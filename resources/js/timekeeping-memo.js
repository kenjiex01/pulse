import { initCompanyDocumentPreview } from './company-document-designer.js';
import { pulseTableSkeletonHtml } from './table-skeleton.js';

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
        root.querySelectorAll('[data-memo-row-checkbox]:not(:disabled)').forEach((checkbox) => {
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

    host.innerHTML = pulseTableSkeletonHtml(5, 6);

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

    host.innerHTML = pulseTableSkeletonHtml(4, 5);

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

const initMemoClientTable = (root) => {
    const tableRoot = root.querySelector('[data-client-table]');
    const results = tableRoot?.querySelector('[data-live-table-results]');

    if (!tableRoot || !results || tableRoot.dataset.memoClientBound === 'true') {
        return;
    }

    tableRoot.dataset.memoClientBound = 'true';

    const searchInput = tableRoot.querySelector('[data-client-table-search]');
    const perPageSelect = tableRoot.querySelector('[data-client-table-per-page]');
    const typeFilter = tableRoot.querySelector('[data-sent-memo-type]');
    const controlFilter = tableRoot.querySelector('[data-sent-control-number]');
    const totalDisplay = tableRoot.querySelector('[data-live-table-total]');
    const totalLabel = totalDisplay?.dataset.totalLabel || 'employees';
    const emptyState = results.querySelector('[data-memo-client-empty]');
    const pager = results.querySelector('[data-memo-client-pager]');
    const tableWrap = results.querySelector('table')?.closest('.overflow-hidden');
    let page = 1;

    const allRows = () => Array.from(results.querySelectorAll('[data-memo-row]'));

    const rowMatches = (row) => {
        const term = (searchInput?.value || '').trim().toLowerCase();
        const type = typeFilter?.value || '';
        const control = (controlFilter?.value || '').trim().toLowerCase();
        const haystack = (row.dataset.memoSearch || '').toLowerCase();
        const typeOk = type === '' || row.dataset.memoFormId === type;
        const controlOk = control === '' || (row.dataset.memoControl || '').includes(control);

        return typeOk && controlOk && (term === '' || haystack.includes(term));
    };

    const renderPager = (matchedCount, perPage) => {
        if (!pager) {
            return;
        }

        if (matchedCount === 0) {
            pager.innerHTML = '';

            return;
        }

        const pageCount = Math.max(1, Math.ceil(matchedCount / perPage));
        const from = (page - 1) * perPage + 1;
        const to = Math.min(matchedCount, page * perPage);
        const button = (action, label, disabled) => `
            <button type="button" class="pagination-skolaris-btn${disabled ? ' opacity-40' : ''}" data-memo-page="${action}" ${disabled ? 'disabled' : ''}>${label}</button>
        `;

        pager.innerHTML = `
            <div class="pagination-skolaris">
                <div class="pagination-skolaris-info">Showing ${from}–${to} of ${matchedCount.toLocaleString()} results</div>
                <div class="pagination-skolaris-nav">
                    ${button('first', 'First', page <= 1)}
                    ${button('prev', 'Previous', page <= 1)}
                    <span class="pagination-skolaris-page">Page ${page} of ${pageCount}</span>
                    ${button('next', 'Next', page >= pageCount)}
                    ${button('last', 'Last', page >= pageCount)}
                </div>
            </div>
        `;
    };

    const applyClientPage = () => {
        const perPage = Math.max(1, Number(perPageSelect?.value || 10));
        const matched = allRows().filter(rowMatches);
        const pageCount = Math.max(1, Math.ceil(matched.length / perPage) || 1);

        if (page > pageCount) {
            page = pageCount;
        }

        const start = (page - 1) * perPage;
        const visible = new Set(matched.slice(start, start + perPage));

        allRows().forEach((row) => {
            const show = visible.has(row);
            row.classList.toggle('hidden', !show);
            row.querySelectorAll('[data-memo-row-checkbox]').forEach((checkbox) => {
                checkbox.disabled = !show;

                if (!show) {
                    checkbox.checked = false;
                }
            });
        });

        tableWrap?.classList.toggle('hidden', matched.length === 0 && allRows().length > 0);
        emptyState?.classList.toggle('hidden', matched.length !== 0 || allRows().length === 0);

        if (totalDisplay) {
            totalDisplay.textContent = `Total: ${matched.length.toLocaleString()} ${totalLabel}`;
        }

        const selectAll = root.querySelector('[data-memo-select-all]');

        if (selectAll) {
            selectAll.checked = false;
        }

        renderPager(matched.length, perPage);
        updateBatchButtonState(root);
    };

    searchInput?.addEventListener('input', () => {
        page = 1;
        applyClientPage();
    });

    typeFilter?.addEventListener('change', () => {
        page = 1;
        applyClientPage();
    });

    controlFilter?.addEventListener('input', () => {
        page = 1;
        applyClientPage();
    });

    perPageSelect?.addEventListener('change', () => {
        page = 1;
        applyClientPage();
    });

    pager?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-memo-page]');

        if (!(button instanceof HTMLButtonElement) || button.disabled) {
            return;
        }

        const perPage = Math.max(1, Number(perPageSelect?.value || 10));
        const matchedCount = allRows().filter(rowMatches).length;
        const pageCount = Math.max(1, Math.ceil(matchedCount / perPage) || 1);

        if (button.dataset.memoPage === 'first') {
            page = 1;
        } else if (button.dataset.memoPage === 'prev') {
            page = Math.max(1, page - 1);
        } else if (button.dataset.memoPage === 'next') {
            page = Math.min(pageCount, page + 1);
        } else if (button.dataset.memoPage === 'last') {
            page = pageCount;
        }

        applyClientPage();
    });

    applyClientPage();
};

export const reinitTimekeepingMemoTable = (container) => {
    const root = container?.closest('[data-memo-root]') ?? document.querySelector('[data-memo-root]');
    wireBatchForm(root);
    if (root) {
        initMemoClientTable(root);
    }
};

export const initTimekeepingMemo = () => {
    const root = document.querySelector('[data-memo-root]');
    if (!root) {
        return;
    }

    wireBatchForm(root);
    initMemoClientTable(root);
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
