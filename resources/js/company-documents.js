import { initCompanyDocumentDesigner, initCompanyDocumentPreview } from './company-document-designer.js';
import { initSignaturePads } from './signature-pad.js';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-company-document-designer]').forEach(initCompanyDocumentDesigner);
    document.querySelectorAll('[data-company-document-preview]').forEach(initCompanyDocumentPreview);
    initSignaturePads();
});
