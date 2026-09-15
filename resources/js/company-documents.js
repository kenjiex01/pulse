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
    initSearchableSelects(document.querySelector('#company-document-create-modal') ?? document);
    initSignaturePads();
});

export { initCompanyDocumentFormMemoOffense };
