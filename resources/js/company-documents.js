import { initCompanyDocumentDesigner, initCompanyDocumentPreview } from './company-document-designer.js';
import { initSearchableSelects, refreshSearchableSelect } from './searchable-select.js';
import { initSignaturePads } from './signature-pad.js';

function initCompanyDocumentFormMemoOffense(root = document) {
    root.querySelectorAll('[data-company-document-form]').forEach((form) => {
        const typeSelect = form.querySelector('[data-company-document-type]');
        const offenseWrap = form.querySelector('[data-company-document-memo-offense]');
        const offenseField = form.querySelector('[data-company-document-offense-field]');
        const offenseSelect = form.querySelector('[data-company-document-memo-offense-select]');
        const nteCheckbox = form.querySelector('[data-company-document-is-nte]');

        if (!typeSelect || !offenseWrap || !offenseSelect) {
            return;
        }

        const sync = () => {
            const isMemo = typeSelect.value === 'memo';
            const isNte = nteCheckbox?.checked === true;

            offenseWrap.classList.toggle('hidden', !isMemo);
            offenseSelect.removeAttribute('required');

            if (!isMemo || isNte) {
                offenseSelect.value = '';
                refreshSearchableSelect(offenseSelect);
            }

            if (offenseField) {
                offenseField.classList.toggle('hidden', !isMemo || isNte);
            }
        };

        typeSelect.addEventListener('change', sync);
        nteCheckbox?.addEventListener('change', sync);
        sync();

        if (!offenseWrap.classList.contains('hidden')) {
            initSearchableSelects(form);
            refreshSearchableSelect(offenseSelect);
        }
    });
}

function initCompanyDocumentSendPicker(picker) {
    if (!picker || picker.dataset.companyDocumentSendReady === '1') {
        return;
    }

    picker.dataset.companyDocumentSendReady = '1';

    const selectAll = picker.querySelector('[data-company-document-send-select-all]');
    const countLabel = picker.querySelector('[data-employee-multiselect-count]');

    const updateSelectedCount = () => {
        const checked = picker.querySelectorAll('[data-employee-multiselect-row]:checked').length;

        if (countLabel) {
            countLabel.textContent = `${checked} selected`;
        }

        return checked;
    };

    picker.querySelectorAll('[data-employee-multiselect-row]').forEach((checkbox) => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    selectAll?.addEventListener('change', () => {
        picker.querySelectorAll('[data-employee-multiselect-row]').forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
        updateSelectedCount();
    });

    const searchInput = picker.querySelector('[data-employee-multiselect-search]');
    searchInput?.addEventListener('input', () => {
        const term = (searchInput.value || '').trim().toLowerCase();

        picker.querySelectorAll('[data-employee-multiselect-item]').forEach((item) => {
            const haystack = item.dataset.employeeSearchText || '';
            item.hidden = term !== '' && !haystack.includes(term);
        });
    });

    updateSelectedCount();
}

const readJsonResponse = async (response) => {
    const raw = await response.text();

    if (!raw) {
        return {};
    }

    try {
        return JSON.parse(raw);
    } catch {
        if (response.status === 419) {
            throw new Error('Your session expired. Refresh the page and try again.');
        }

        throw new Error('Unexpected server response. Refresh the page and try again.');
    }
};

function initCompanyDocumentSendForms(root = document) {
    root.querySelectorAll('[data-company-document-send-form]').forEach((form) => {
        if (!(form instanceof HTMLFormElement) || form.dataset.companyDocumentSendBound === '1') {
            return;
        }

        form.dataset.companyDocumentSendBound = '1';

        const sendOneUrl = form.dataset.sendOneUrl ?? '';
        const batchCompleteUrl = form.dataset.sendBatchCompleteUrl ?? '';
        const indexUrl = form.dataset.sendIndexUrl ?? window.location.href;
        const documentName = form.dataset.documentName ?? 'document';
        const submitButton = form.querySelector('button[type="submit"]');
        const csrf = form.querySelector('input[name="_token"]')?.value
            ?? document.querySelector('meta[name="csrf-token"]')?.content
            ?? '';

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const selectedRows = Array.from(form.querySelectorAll('[data-employee-multiselect-row]:checked'));

            if (selectedRows.length === 0) {
                window.alert('Select at least one employee.');

                return;
            }

            const total = selectedRows.length;
            const confirmMessage = total === 1
                ? `Send "${documentName}" to 1 employee?`
                : `Send "${documentName}" to ${total} employees?`;

            if (!window.confirm(confirmMessage)) {
                return;
            }

            if (!sendOneUrl) {
                window.alert('Send URL is not configured.');

                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
            }

            const failures = [];
            let sent = 0;

            window.PulseLoader?.showProgress(0, total, 'Preparing…');

            for (const checkbox of selectedRows) {
                const employeeId = checkbox.value;
                const employeeLabel = checkbox.closest('[data-employee-multiselect-item]')
                    ?.querySelector('span.text-gray-600')
                    ?.textContent
                    ?.trim()
                    ?? `Employee #${employeeId}`;

                window.PulseLoader?.showProgress(sent, total, `Sending to ${employeeLabel}…`);

                try {
                    const response = await fetch(sendOneUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            employee_id: Number(employeeId),
                        }),
                    });

                    const payload = await readJsonResponse(response);

                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message ?? `Failed to send to ${employeeLabel}.`);
                    }

                    sent += 1;
                    window.PulseLoader?.showProgress(
                        sent,
                        total,
                        payload.employee_name ? `Sent to ${payload.employee_name}` : `Sent to ${employeeLabel}`,
                    );
                } catch (error) {
                    failures.push(error.message ?? `Failed to send to ${employeeLabel}.`);
                }
            }

            if (sent > 0 && batchCompleteUrl) {
                try {
                    await fetch(batchCompleteUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            sent,
                            total,
                        }),
                    });
                } catch {
                    // Send logs already exist per employee; audit summary is best-effort.
                }
            }

            window.PulseLoader?.hide();

            if (submitButton) {
                submitButton.disabled = false;
            }

            let notice = '';

            if (failures.length === 0) {
                notice = `Document sent to ${sent} employee${sent === 1 ? '' : 's'}.`;
            } else if (sent > 0) {
                notice = `Document sent to ${sent} of ${total}. ${failures.slice(0, 2).join(' ')}`;
            } else {
                window.alert(failures[0] ?? 'Unable to send documents.');

                return;
            }

            const redirectUrl = new URL(indexUrl, window.location.origin);
            redirectUrl.searchParams.set('send_notice', notice);

            window.location.assign(redirectUrl.toString());
        });
    });
}

function initCompanyDocumentSendModals(root = document) {
    root.querySelectorAll('[data-company-document-send-open]').forEach((button) => {
        button.addEventListener('click', () => {
            window.setTimeout(() => {
                const modalId = button.getAttribute('data-modal-open');
                const modal = modalId ? document.getElementById(modalId) : null;

                modal?.querySelectorAll('[data-company-document-send-picker]').forEach(initCompanyDocumentSendPicker);
                initCompanyDocumentSendForms(modal ?? document);
            }, 0);
        });
    });

    root.querySelectorAll('[data-company-document-send-picker]').forEach(initCompanyDocumentSendPicker);
    initCompanyDocumentSendForms(root);
}

function initCompanyDocumentModals(root = document) {
    root.querySelectorAll('[data-modal-open="company-document-create-modal"]').forEach((button) => {
        button.addEventListener('click', () => {
            window.setTimeout(() => {
                const modal = document.getElementById('company-document-create-modal');
                if (modal) {
                    initCompanyDocumentFormMemoOffense(modal);
                    initSearchableSelects(modal);
                }
            }, 0);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-company-document-designer]').forEach(initCompanyDocumentDesigner);
    document.querySelectorAll('[data-company-document-preview]').forEach(initCompanyDocumentPreview);
    initCompanyDocumentFormMemoOffense();
    initCompanyDocumentModals();
    initCompanyDocumentSendModals();
    initSearchableSelects(document.querySelector('#company-document-create-modal') ?? document);
    initSignaturePads();
});

export { initCompanyDocumentFormMemoOffense };
