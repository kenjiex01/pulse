import { initSignaturePads, renderSignaturePadHtml, renderSignatureReadonlyHtml, captureSignaturePadState, restoreSignaturePadState } from './signature-pad.js';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const parseJson = (value, fallback) => {
    try {
        return JSON.parse(value || '');
    } catch {
        return fallback;
    }
};

const slugify = (value) => String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '') || 'field';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const WIDTH_CLASS = { full: 'col-span-6', half: 'col-span-3', third: 'col-span-2' };
const CANVAS_INSET = 16;
const FIELD_GAP = 12;
const LEGAL_PAGE_WIDTH_PX = 816;
const LEGAL_PAGE_HEIGHT_PX = 1344;
const LEGAL_CONTENT_WIDTH_PX = 640;
const LEGAL_CONTENT_HEIGHT_PX = 1312;
const MIN_BOX_WIDTH = 80;
const MIN_BOX_HEIGHT = 36;
const DEFAULT_IMAGE_WIDTH = 240;
const DEFAULT_IMAGE_HEIGHT = 160;
const DEFAULT_IMAGE_OPACITY = 100;
const DEFAULT_IMAGE_ROTATE = 0;
const DEFAULT_PARAGRAPH_FONT_SIZE = 14;
const DEFAULT_PARAGRAPH_FONT_COLOR = '#4B5563';
const PARAGRAPH_FONT_FAMILIES = [
    { value: '', label: 'System default' },
    { value: 'Arial, Helvetica, sans-serif', label: 'Arial' },
    { value: '"Times New Roman", Times, serif', label: 'Times New Roman' },
    { value: 'Georgia, serif', label: 'Georgia' },
    { value: '"Courier New", Courier, monospace', label: 'Courier New' },
    { value: 'Verdana, Geneva, sans-serif', label: 'Verdana' },
    { value: 'Tahoma, Geneva, sans-serif', label: 'Tahoma' },
    { value: '"Trebuchet MS", Helvetica, sans-serif', label: 'Trebuchet MS' },
];
const PREVIEW_INPUT_CLASS = 'block w-full min-w-0 max-w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-[#00A3E6] focus:outline-none focus:ring-1 focus:ring-[#00A3E6]/30';
const LAYOUT_TYPES = new Set(['heading', 'paragraph', 'divider', 'image', 'section', 'page_break', 'merge_tag']);
const INPUT_TYPES = new Set(['short_text', 'long_text', 'number', 'email', 'phone', 'date', 'dropdown', 'radio', 'checkbox', 'yes_no', 'file_upload', 'signature']);

/** @type {Record<string, string>} */
let mergeTagSamples = {};

/** @type {Record<string, string>} */
let mergeTagLabels = {};

/** @type {HTMLInputElement|HTMLTextAreaElement|null} */
let activeTagInsertField = null;

const INLINE_TAG_PATTERN = /\{\{([a-z0-9_]+)\}\}/gi;

const inlineTagToken = (tagKey) => `{{${tagKey}}}`;

const inlineTagLabel = (tagKey) => {
    const normalized = String(tagKey || '').trim().toLowerCase();

    if (normalized && mergeTagLabels[normalized]) {
        return mergeTagLabels[normalized];
    }

    return normalized.replace(/_/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
};

const renderInlineTagChipHtml = (tagKey, label) => (
    `<span class="cd-inline-merge-tag" data-inline-tag="${escapeHtml(tagKey)}">${escapeHtml(label)}</span>`
);

const INLINE_FORMAT_TAG_PATTERN = /(<\/?(?:b|strong|i|em|u)>)/gi;

const renderFormattedTextSegment = (text) => {
    const source = String(text ?? '');

    if (source === '') {
        return '';
    }

    return source.split(INLINE_FORMAT_TAG_PATTERN).map((part) => {
        if (/^<\/?(?:b|strong|i|em|u)>$/i.test(part)) {
            return part.toLowerCase();
        }

        return escapeHtml(part);
    }).join('');
};

const wrapSelectionWithFormatTag = (field, tagName) => {
    if (!field) {
        return '';
    }

    const value = field.value ?? '';
    const start = field.selectionStart ?? 0;
    const end = field.selectionEnd ?? 0;
    const selected = value.slice(start, end);
    const open = `<${tagName}>`;
    const close = `</${tagName}>`;
    const before = value.slice(Math.max(0, start - open.length), start);
    const after = value.slice(end, end + close.length);

    if (selected !== '' && before === open && after === close) {
        const nextValue = `${value.slice(0, start - open.length)}${selected}${value.slice(end + close.length)}`;
        field.value = nextValue;
        field.focus();
        field.setSelectionRange(start - open.length, end - open.length);

        return nextValue;
    }

    const wrapped = selected === '' ? `${open}${close}` : `${open}${selected}${close}`;
    const nextValue = `${value.slice(0, start)}${wrapped}${value.slice(end)}`;
    field.value = nextValue;
    field.focus();

    if (selected === '') {
        field.setSelectionRange(start + open.length, start + open.length);
    } else {
        field.setSelectionRange(start + open.length, start + open.length + selected.length);
    }

    return nextValue;
};

const buildParagraphFormatToolbarHtml = () => `
    <div class="mb-2 flex gap-1">
        <button type="button" class="flex h-8 w-8 items-center justify-center rounded bg-[#1e2230] text-sm font-bold text-white/80 hover:bg-white/10 hover:text-white" data-format-tag="b" title="Bold">B</button>
        <button type="button" class="flex h-8 w-8 items-center justify-center rounded bg-[#1e2230] text-sm italic text-white/80 hover:bg-white/10 hover:text-white" data-format-tag="i" title="Italic">I</button>
        <button type="button" class="flex h-8 w-8 items-center justify-center rounded bg-[#1e2230] text-sm text-white/80 underline hover:bg-white/10 hover:text-white" data-format-tag="u" title="Underline">U</button>
    </div>
    <p class="mb-2 text-[10px] text-white/40">Select words, then click B, I, or U. Formatting is saved with the template.</p>
`;

const renderInlineTagsHtml = (text) => {
    const source = String(text ?? '');

    if (source === '') {
        return '';
    }

    const parts = [];
    let lastIndex = 0;
    const pattern = new RegExp(INLINE_TAG_PATTERN.source, INLINE_TAG_PATTERN.flags);
    let match = pattern.exec(source);

    while (match !== null) {
        if (match.index > lastIndex) {
            parts.push(renderFormattedTextSegment(source.slice(lastIndex, match.index)));
        }

        const tagKey = String(match[1] || '').toLowerCase();
        parts.push(renderInlineTagChipHtml(tagKey, inlineTagLabel(tagKey)));
        lastIndex = pattern.lastIndex;
        match = pattern.exec(source);
    }

    if (lastIndex < source.length) {
        parts.push(renderFormattedTextSegment(source.slice(lastIndex)));
    }

    return parts.join('');
};

const insertTextAtCursor = (field, text) => {
    if (!field) {
        return '';
    }

    const value = field.value ?? '';
    const start = field.selectionStart ?? value.length;
    const end = field.selectionEnd ?? value.length;
    const nextValue = `${value.slice(0, start)}${text}${value.slice(end)}`;
    field.value = nextValue;
    const cursor = start + text.length;
    field.focus();
    field.setSelectionRange(cursor, cursor);

    return nextValue;
};

const wireTagInsertField = (field, onChange) => {
    if (!field) {
        return;
    }

    field.addEventListener('focus', () => {
        activeTagInsertField = field;
    });
    field.addEventListener('blur', () => {
        if (activeTagInsertField === field) {
            activeTagInsertField = null;
        }
    });
    field.addEventListener('input', (event) => {
        onChange(event.target.value);
    });
};

const tagInsertTargetForElement = (element) => {
    if (['paragraph', 'heading', 'section'].includes(element.type)) {
        return { kind: 'label' };
    }

    if (['long_text', 'short_text'].includes(element.type)) {
        return { kind: 'default_text' };
    }

    return null;
};

const insertTagIntoField = (field, tagKey, onChange) => {
    const token = inlineTagToken(tagKey);
    const nextValue = insertTextAtCursor(field, token);
    onChange(nextValue);
};

const buildTagInsertControlsHtml = () => {
    const options = Object.entries(mergeTagLabels).map(([tagKey, label]) => (
        `<option value="${escapeHtml(tagKey)}">${escapeHtml(label)}</option>`
    )).join('');

    if (options === '') {
        return '';
    }

    return `
        <div class="mt-3 border-t border-white/[0.06] pt-3">
            <label class="mb-1.5 block text-[11px] font-medium text-white/70">Insert tag</label>
            <div class="flex gap-2">
                <select class="cd-dark-input min-w-0 flex-1 text-xs" data-insert-tag-select>
                    <option value="">Choose tag…</option>
                    ${options}
                </select>
                <button type="button" class="shrink-0 rounded bg-[#00A3E6] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#0099D6]" data-insert-tag-button>Insert</button>
            </div>
            <p class="mt-1.5 text-[10px] text-white/40">Inserts <code class="text-white/60">{{tag_key}}</code> at the cursor. You can also click a tag in the TAGS panel.</p>
        </div>
    `;
};

const mergeTagKey = (element) => String(element.settings?.tag_key || '').trim();

const mergeTagLabel = (element) => {
    const label = String(element.label || '').trim();

    if (label !== '') {
        return label;
    }

    const tagKey = mergeTagKey(element);

    if (tagKey === '') {
        return 'Tag';
    }

    return tagKey.replace(/_/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
};

const renderMergeTagHtml = (element) => {
    const tagKey = mergeTagKey(element);
    const label = mergeTagLabel(element);

    return `
        <span
            class="inline-flex max-w-full items-center gap-2 rounded-md border border-[#00A3E6]/25 bg-[#00A3E6]/10 px-2.5 py-1.5 text-sm font-medium text-[#0B318F]"
            data-merge-tag="${escapeHtml(tagKey)}"
            title="${escapeHtml(label)}"
        >
            <svg class="h-3.5 w-3.5 shrink-0 text-[#00A3E6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
            <span class="truncate">${escapeHtml(label)}</span>
        </span>
    `;
};

const widthRatio = (width) => {
    if (width === 'half') {
        return 0.5;
    }
    if (width === 'third') {
        return 1 / 3;
    }

    return 1;
};

const imageDimensions = (element) => {
    const settings = normalizeSettings(element.settings);

    return {
        width: Math.max(40, Number(settings.image_width ?? DEFAULT_IMAGE_WIDTH)),
        height: Math.max(40, Number(settings.image_height ?? DEFAULT_IMAGE_HEIGHT)),
    };
};

const clampImageOpacity = (value) => {
    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return DEFAULT_IMAGE_OPACITY;
    }

    return Math.min(100, Math.max(0, Math.round(numeric)));
};

const imageOpacity = (element) => clampImageOpacity(normalizeSettings(element.settings).image_opacity ?? DEFAULT_IMAGE_OPACITY);

const imageOpacityStyle = (element) => `opacity:${imageOpacity(element) / 100}`;

const clampImageRotate = (value) => {
    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return DEFAULT_IMAGE_ROTATE;
    }

    let degrees = Math.round(numeric) % 360;
    if (degrees < 0) {
        degrees += 360;
    }

    return degrees;
};

const imageRotate = (element) => clampImageRotate(normalizeSettings(element.settings).image_rotate ?? DEFAULT_IMAGE_ROTATE);

const imageRotateStyle = (element) => `transform:rotate(${imageRotate(element)}deg);transform-origin:center center;`;

const paragraphFontFamily = (element) => {
    const value = String(normalizeSettings(element.settings).font_family ?? '');

    return PARAGRAPH_FONT_FAMILIES.some((option) => option.value === value) ? value : '';
};

const paragraphFontSize = (element) => {
    const size = Number(normalizeSettings(element.settings).font_size ?? DEFAULT_PARAGRAPH_FONT_SIZE);

    if (!Number.isFinite(size)) {
        return DEFAULT_PARAGRAPH_FONT_SIZE;
    }

    return Math.max(8, Math.min(72, Math.round(size)));
};

const clampParagraphFontColor = (value) => {
    const normalized = String(value || DEFAULT_PARAGRAPH_FONT_COLOR).trim().toUpperCase();

    return /^#[0-9A-F]{6}$/.test(normalized) ? normalized : DEFAULT_PARAGRAPH_FONT_COLOR;
};

const paragraphFontColor = (element) => clampParagraphFontColor(normalizeSettings(element.settings).font_color ?? DEFAULT_PARAGRAPH_FONT_COLOR);

const paragraphTextStyleAttr = (element) => {
    const parts = [];
    const fontFamily = paragraphFontFamily(element);

    if (fontFamily) {
        parts.push(`font-family:${fontFamily}`);
    }

    parts.push(`font-size:${paragraphFontSize(element)}px`);
    parts.push(`color:${paragraphFontColor(element)}`);

    return parts.join(';');
};

const buildParagraphTypographyControlsHtml = (element) => {
    const fontFamily = paragraphFontFamily(element);
    const fontSize = paragraphFontSize(element);
    const fontColor = paragraphFontColor(element);
    const optionsHtml = PARAGRAPH_FONT_FAMILIES.map((option) => (
        `<option value="${escapeHtml(option.value)}" ${fontFamily === option.value ? 'selected' : ''}>${escapeHtml(option.label)}</option>`
    )).join('');

    return `
        <div class="border-b border-white/[0.06] px-4 py-4">
            <label class="mb-1.5 block text-[11px] font-medium text-white/70">Font</label>
            <select class="cd-dark-input mb-3 w-full" data-prop-font-family>${optionsHtml}</select>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-[10px] text-white/50">Size (px)</label>
                    <input type="number" min="8" max="72" step="1" class="cd-dark-input w-full" data-prop-font-size value="${fontSize}">
                </div>
                <div>
                    <label class="mb-1 block text-[10px] text-white/50">Color</label>
                    <input type="color" class="cd-dark-input h-9 w-full cursor-pointer p-1" data-prop-font-color value="${escapeHtml(fontColor)}">
                </div>
            </div>
        </div>
    `;
};

const estimateElementHeight = (element) => {
    const customHeight = Number(element.settings?.box_height);
    if (Number.isFinite(customHeight) && customHeight > 0) {
        return Math.max(MIN_BOX_HEIGHT, customHeight);
    }

    switch (element.type) {
        case 'image':
            return imageDimensions(element).height;
        case 'long_text':
            return (element.settings?.rows || 4) * 24 + 48;
        case 'heading':
            return 40;
        case 'paragraph': {
            const lines = String(element.label || '').split('\n');
            const lineCount = lines.reduce((count, line) => count + Math.max(1, Math.ceil(line.length / 72)), 0);

            return Math.max(56, lineCount * 22 + 16);
        }
        case 'signature':
            return 210;
        case 'merge_tag':
            return 44;
        case 'file_upload':
            return 96;
        default:
            return 76;
    }
};

const fieldWidthPx = (containerWidth, element) => {
    const usable = Math.max(containerWidth, 200);

    return Math.max(120, Math.floor(usable * widthRatio(element.width || 'full')));
};

const hasCustomBoxWidth = (element) => Number.isFinite(Number(element.settings?.box_width));

const hasCustomBoxHeight = (element) => Number.isFinite(Number(element.settings?.box_height));

const isFullWidth = (element) => {
    if (element.type === 'image' || hasCustomBoxWidth(element)) {
        return false;
    }

    return (element.width || 'full') === 'full';
};

const applyBoxSize = (element, width, height) => {
    const settings = element.settings && !Array.isArray(element.settings)
        ? element.settings
        : (element.settings = { label_align: 'top' });
    const minW = element.type === 'image' ? 40 : MIN_BOX_WIDTH;
    const minH = element.type === 'image' ? 40 : MIN_BOX_HEIGHT;
    const nextWidth = Math.max(minW, Math.round(Number(width) || minW));
    const nextHeight = Math.max(minH, Math.round(Number(height) || minH));

    if (element.type === 'image') {
        settings.image_width = nextWidth;
        settings.image_height = nextHeight;
    } else {
        settings.box_width = nextWidth;
        settings.box_height = nextHeight;
    }

    return { width: nextWidth, height: nextHeight };
};

const fieldPositionX = (element) => {
    if (isFullWidth(element)) {
        return 0;
    }

    return Number(element.settings?.pos_x ?? CANVAS_INSET);
};

const elementBoxWidth = (containerWidth, element) => {
    if (element.type === 'image') {
        return imageDimensions(element).width;
    }

    if (hasCustomBoxWidth(element)) {
        return Math.max(MIN_BOX_WIDTH, Math.round(Number(element.settings.box_width)));
    }

    return fieldWidthPx(containerWidth, element);
};

const fieldBoxSize = (containerWidth, element) => {
    if (element.type === 'image') {
        return imageDimensions(element);
    }

    return {
        width: elementBoxWidth(containerWidth, element),
        height: hasCustomBoxHeight(element)
            ? Math.max(MIN_BOX_HEIGHT, Math.round(Number(element.settings.box_height)))
            : estimateElementHeight(element),
    };
};

const elementBoxWidthCss = (containerWidth, element) => {
    if (element.type === 'image' || hasCustomBoxWidth(element)) {
        return `${elementBoxWidth(containerWidth, element)}px`;
    }

    if (isFullWidth(element)) {
        return '100%';
    }

    if ((element.width || 'full') === 'half') {
        return '50%';
    }

    if ((element.width || 'full') === 'third') {
        return '33.333%';
    }

    return `${fieldWidthPx(containerWidth, element)}px`;
};

const imageAssetUrl = (assetUrlBase, path) => {
    if (!path || !assetUrlBase) {
        return '';
    }

    const separator = assetUrlBase.includes('?') ? '&' : '?';

    return `${assetUrlBase}${separator}path=${encodeURIComponent(path)}`;
};

const asObject = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return { ...value };
    }

    return {};
};

const normalizeSettings = (settings) => {
    if (!settings || Array.isArray(settings)) {
        return { label_align: 'top' };
    }

    return {
        label_align: 'top',
        ...settings,
    };
};

const ensureSettingsObject = (element) => {
    element.settings = normalizeSettings(element.settings);

    return element.settings;
};

const hasSavedPosition = (element) => {
    const settings = normalizeSettings(element.settings);

    return Number.isFinite(Number(settings.pos_x)) && Number.isFinite(Number(settings.pos_y));
};

const PALETTE_ICONS = {
    heading: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h10M4 18h14"/></svg>',
    paragraph: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h12M4 18h10"/></svg>',
    divider: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 12h16"/></svg>',
    image: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    section: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>',
    page_break: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>',
    short_text: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h10"/></svg>',
    long_text: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h12"/></svg>',
    number: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M7 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>',
    email: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
    date: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    dropdown: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>',
    radio: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>',
    checkbox: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    yes_no: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>',
    file_upload: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>',
    signature: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>',
    merge_tag: '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>',
};

const makeElement = (type, label = '', tagKey = '') => {
    const base = {
        type,
        label: label || '',
        field_key: '',
        is_required: false,
        width: 'full',
        placeholder: '',
        help_text: '',
        options: {},
        validation: {},
        conditional: {},
        settings: { label_align: 'top' },
    };

    switch (type) {
        case 'heading':
            return { ...base, label: 'Section title' };
        case 'paragraph':
            return { ...base, label: 'Add descriptive text here.' };
        case 'section':
            return { ...base, label: 'New section' };
        case 'short_text':
            return { ...base, label: label || 'Short text', placeholder: 'Enter text...' };
        case 'long_text':
            return { ...base, label: label || 'Long text', settings: { label_align: 'top', rows: 4 } };
        case 'number':
            return { ...base, label: label || 'Number' };
        case 'email':
            return { ...base, label: label || 'Email', placeholder: 'name@example.com' };
        case 'date':
            return { ...base, label: label || 'Date' };
        case 'dropdown':
            return {
                ...base,
                label: label || 'Dropdown',
                options: { choices: [{ label: 'Option 1', value: 'option_1' }, { label: 'Option 2', value: 'option_2' }] },
            };
        case 'radio':
            return {
                ...base,
                label: label || 'Choose one',
                options: { choices: [{ label: 'Option 1', value: 'option_1' }, { label: 'Option 2', value: 'option_2' }] },
            };
        case 'checkbox':
            return { ...base, label: label || 'I agree to the terms' };
        case 'yes_no':
            return { ...base, label: label || 'Yes or No?' };
        case 'file_upload':
            return { ...base, label: label || 'Upload a file' };
        case 'signature':
            return { ...base, label: label || 'Signature' };
        case 'merge_tag':
            return {
                ...base,
                label: label || 'Merge tag',
                settings: {
                    label_align: 'top',
                    tag_key: tagKey,
                },
            };
        case 'image':
            return {
                ...base,
                label: label || 'Image',
                settings: {
                    label_align: 'top',
                    image_width: DEFAULT_IMAGE_WIDTH,
                    image_height: DEFAULT_IMAGE_HEIGHT,
                    image_opacity: DEFAULT_IMAGE_OPACITY,
                    image_rotate: DEFAULT_IMAGE_ROTATE,
                },
                options: {},
            };
        default:
            return { ...base, label: label || type.replace(/_/g, ' ') };
    }
};

const ensureFieldKey = (element, existing) => {
    if (element.field_key) {
        return element.field_key;
    }
    const base = slugify(element.label || element.type);
    let key = base;
    let index = 2;
    while (existing.includes(key)) {
        key = `${base}_${index}`;
        index += 1;
    }
    return key;
};

const fieldLabelHtml = (element) => {
    const required = element.is_required ? '<span class="text-red-500">*</span>' : '';

    return `<span class="text-sm font-medium text-gray-700">${escapeHtml(element.label || '')} ${required}</span>`;
};

const wrapLabeledField = (element, controlHtml) => {
    const align = element.settings?.label_align || 'top';
    const label = fieldLabelHtml(element);

    if (align === 'left') {
        return `<div class="flex h-full min-h-0 w-full min-w-0 items-start gap-3"><div class="w-36 shrink-0 pt-2">${label}</div><div class="flex min-h-0 min-w-0 flex-1 flex-col">${controlHtml}</div></div>`;
    }

    if (align === 'right') {
        return `<div class="flex h-full min-h-0 w-full min-w-0 flex-row-reverse items-start gap-3"><div class="w-36 shrink-0 pt-2 text-right">${label}</div><div class="flex min-h-0 min-w-0 flex-1 flex-col">${controlHtml}</div></div>`;
    }

    return `<div class="flex h-full min-h-0 w-full min-w-0 flex-col"><label class="mb-1 block shrink-0 text-sm font-medium text-gray-700">${escapeHtml(element.label || '')} ${element.is_required ? '<span class="text-red-500">*</span>' : ''}</label><div class="flex min-h-0 min-w-0 flex-1 flex-col">${controlHtml}</div></div>`;
};

const previewKeyFor = (element, index) => element.field_key || `preview_${index}`;

const previewValueFor = (previewValues, element, index) => {
    const key = previewKeyFor(element, index);
    return previewValues[key] ?? '';
};

const wireSignaturePadEvents = (container) => {
    if (!container) {
        return;
    }

    container.querySelectorAll('[data-signature-pad]').forEach((pad) => {
        ['mousedown', 'click', 'focus', 'pointerdown', 'touchstart'].forEach((eventName) => {
            pad.addEventListener(eventName, (event) => event.stopPropagation());
        });
    });
};

const persistSignaturePreviewValues = async (container, previewValues) => {
    if (!container) {
        return;
    }

    const pads = container.querySelectorAll('[data-signature-pad][data-preview-key]');
    await Promise.all([...pads].map(async (pad) => {
        const key = pad.dataset.previewKey;
        if (!key) {
            return;
        }

        const state = await captureSignaturePadState(pad);
        if (state) {
            previewValues[key] = state;
        } else {
            delete previewValues[key];
        }
    }));
};

const restoreSignaturePreviewValues = async (container, previewValues) => {
    if (!container) {
        return;
    }

    const pads = container.querySelectorAll('[data-signature-pad][data-preview-key]');
    await Promise.all([...pads].map(async (pad) => {
        const key = pad.dataset.previewKey;
        if (!key || !previewValues[key]) {
            return;
        }

        await restoreSignaturePadState(pad, previewValues[key]);
    }));
};

const bindSignaturePreviewPersistence = (container, previewValues) => {
    if (!container || container.dataset.signaturePreviewBound === 'true') {
        return;
    }

    container.dataset.signaturePreviewBound = 'true';
    container.addEventListener('signature-pad:change', (event) => {
        const pad = event.target.closest('[data-signature-pad]');
        const key = pad?.dataset.previewKey;
        if (!key) {
            return;
        }

        const state = event.detail?.state ?? null;
        if (state) {
            previewValues[key] = state;
        } else {
            delete previewValues[key];
        }
    });
};

const mountSignaturePads = (container) => {
    if (!container) {
        return;
    }

    initSignaturePads(container);
    wireSignaturePadEvents(container);
};

const previewControlAttrs = (element, index) => {
    const key = previewKeyFor(element, index);
    return `data-preview-input data-preview-key="${escapeHtml(key)}" data-preview-control`;
};

const renderFieldPreview = (element, index, previewValues = {}, assetUrlBase = '', options = {}) => {
    const readonlyPreview = Boolean(options.readonlyPreview);
    const label = escapeHtml(element.label || '');
    const defaultText = String(element.settings?.default_text ?? '');
    const previewValue = String(previewValueFor(previewValues, element, index) ?? '');
    const value = escapeHtml(previewValue !== '' ? previewValue : defaultText);
    const helpHtml = element.help_text
        ? `<p class="mt-1 text-xs text-gray-500">${escapeHtml(element.help_text)}</p>`
        : '';

    switch (element.type) {
        case 'heading':
            return `<h3 class="cd-designer-text-block w-full max-w-full break-words text-lg font-semibold text-gray-900">${renderInlineTagsHtml(element.label || '')}</h3>`;
        case 'paragraph':
            return `<p class="cd-designer-text-block cd-designer-paragraph w-full max-w-full whitespace-pre-wrap" style="${paragraphTextStyleAttr(element)}">${renderInlineTagsHtml(element.label || '')}</p>`;
        case 'divider':
            return '<hr class="border-gray-200">';
        case 'section':
            return `<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700 break-words">${label}</div>`;
        case 'page_break':
            return '<div class="rounded border border-dashed border-gray-300 py-2 text-center text-xs uppercase tracking-wide text-gray-400">Page break</div>';
        case 'merge_tag':
            return renderMergeTagHtml(element);
        case 'image': {
            const { width, height } = imageDimensions(element);
            const path = element.options?.path;
            if (path) {
                const src = imageAssetUrl(assetUrlBase, path);
        return `<div class="cd-designer-image-preview relative overflow-visible bg-transparent" style="width:${width}px;height:${height}px;${imageOpacityStyle(element)}" data-image-preview="${index}">
                    <img src="${escapeHtml(src)}" alt="${escapeHtml(element.options?.original_filename || element.label || 'Image')}" class="pointer-events-none h-full w-full bg-transparent object-contain" style="${imageRotateStyle(element)}" draggable="false">
                </div>`;
            }

            return `<label class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 text-xs text-gray-500 transition hover:border-[#00A3E6] hover:bg-[#00A3E6]/5 hover:text-[#00A3E6]" style="width:${width}px;height:${height}px;${imageOpacityStyle(element)}" data-image-upload="${index}">
                <svg class="mb-1 h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Click to upload image</span>
                <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" data-image-file="${index}">
            </label>`;
        }
        case 'long_text': {
            const content = previewValue !== '' ? previewValue : defaultText;

            if (readonlyPreview && content !== '') {
                return `${wrapLabeledField(element, `<div class="${PREVIEW_INPUT_CLASS.replace('focus:border-[#00A3E6] focus:outline-none focus:ring-1 focus:ring-[#00A3E6]/30', '')} min-h-[6rem] whitespace-pre-wrap">${renderInlineTagsHtml(content)}</div>`)}${helpHtml}`;
            }

            return `${wrapLabeledField(element, `<textarea rows="${element.settings?.rows || 4}" ${previewControlAttrs(element, index)} class="${PREVIEW_INPUT_CLASS}">${escapeHtml(content)}</textarea>`)}${helpHtml}`;
        }
        case 'short_text': {
            const content = previewValue !== '' ? previewValue : defaultText;

            if (readonlyPreview && content !== '') {
                return `${wrapLabeledField(element, `<div class="${PREVIEW_INPUT_CLASS.replace('focus:border-[#00A3E6] focus:outline-none focus:ring-1 focus:ring-[#00A3E6]/30', '')} whitespace-pre-wrap">${renderInlineTagsHtml(content)}</div>`)}${helpHtml}`;
            }

            return `${wrapLabeledField(element, `<input type="text" value="${escapeHtml(content)}" placeholder="${escapeHtml(element.placeholder || '')}" ${previewControlAttrs(element, index)} class="${PREVIEW_INPUT_CLASS}">`)}${helpHtml}`;
        }
        case 'radio': {
            const choices = element.options?.choices || [];
            const selected = previewValueFor(previewValues, element, index);
            const name = `preview_${previewKeyFor(element, index)}`;
            const items = choices.map((choice) => {
                const choiceValue = escapeHtml(choice.value || choice.label || '');
                const checked = selected === (choice.value || choice.label) ? 'checked' : '';
                return `
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700" data-preview-control>
                        <input type="radio" name="${name}" value="${choiceValue}" ${previewControlAttrs(element, index)} class="text-[#00A3E6]" ${checked}>
                        ${escapeHtml(choice.label || choice.value)}
                    </label>
                `;
            }).join('');
            return `${wrapLabeledField(element, `<div class="flex flex-wrap gap-4 pt-1">${items}</div>`)}${helpHtml}`;
        }
        case 'checkbox': {
            const checked = previewValueFor(previewValues, element, index) ? 'checked' : '';
            return `<label class="inline-flex items-center gap-2 text-sm text-gray-700" data-preview-control><input type="checkbox" ${previewControlAttrs(element, index)} class="rounded border-gray-300 text-[#00A3E6]" ${checked}>${fieldLabelHtml(element)}</label>${helpHtml}`;
        }
        case 'yes_no': {
            const selected = previewValueFor(previewValues, element, index);
            const name = `preview_${previewKeyFor(element, index)}`;
            return `${wrapLabeledField(element, `
                <div class="flex gap-4 pt-1 text-sm text-gray-700">
                    <label class="inline-flex items-center gap-2" data-preview-control><input type="radio" name="${name}" value="yes" ${previewControlAttrs(element, index)} class="text-[#00A3E6]" ${selected === 'yes' ? 'checked' : ''}>Yes</label>
                    <label class="inline-flex items-center gap-2" data-preview-control><input type="radio" name="${name}" value="no" ${previewControlAttrs(element, index)} class="text-[#00A3E6]" ${selected === 'no' ? 'checked' : ''}>No</label>
                </div>
            `)}${helpHtml}`;
        }
        case 'dropdown': {
            const choices = element.options?.choices || [];
            const selected = previewValueFor(previewValues, element, index);
            const options = ['<option value="">Select...</option>'].concat(choices.map((choice) => {
                const choiceValue = escapeHtml(choice.value || choice.label || '');
                const choiceLabel = escapeHtml(choice.label || choice.value || '');
                const selectedAttr = selected === (choice.value || choice.label) ? 'selected' : '';
                return `<option value="${choiceValue}" ${selectedAttr}>${choiceLabel}</option>`;
            })).join('');
            return `${wrapLabeledField(element, `<select ${previewControlAttrs(element, index)} class="${PREVIEW_INPUT_CLASS}">${options}</select>`)}${helpHtml}`;
        }
        case 'file_upload':
            return `${wrapLabeledField(element, `<input type="file" ${previewControlAttrs(element, index)} class="${PREVIEW_INPUT_CLASS}">`)}${helpHtml}`;
        case 'signature': {
            if (readonlyPreview) {
                const signatureKey = previewKeyFor(element, index);
                const signatureState = previewValues[signatureKey] || element.settings?.signature_preview || null;

                return `${wrapLabeledField(element, renderSignatureReadonlyHtml(signatureState))}${helpHtml}`;
            }

            return `${wrapLabeledField(element, renderSignaturePadHtml({
                required: Boolean(element.is_required),
                previewKey: previewKeyFor(element, index),
            }))}${helpHtml}`;
        }
        case 'date':
            return `${wrapLabeledField(element, `<input type="date" value="${value}" ${previewControlAttrs(element, index)} class="${PREVIEW_INPUT_CLASS}">`)}${helpHtml}`;
        case 'number':
            return `${wrapLabeledField(element, `<input type="number" value="${value}" placeholder="${escapeHtml(element.placeholder || '')}" ${previewControlAttrs(element, index)} class="${PREVIEW_INPUT_CLASS}">`)}${helpHtml}`;
        case 'email':
            return `${wrapLabeledField(element, `<input type="email" value="${value}" placeholder="${escapeHtml(element.placeholder || '')}" ${previewControlAttrs(element, index)} class="${PREVIEW_INPUT_CLASS}">`)}${helpHtml}`;
        case 'phone':
            return `${wrapLabeledField(element, `<input type="tel" value="${value}" placeholder="${escapeHtml(element.placeholder || '')}" ${previewControlAttrs(element, index)} class="${PREVIEW_INPUT_CLASS}">`)}${helpHtml}`;
        default:
            return `${wrapLabeledField(element, `<input type="text" value="${value}" placeholder="${escapeHtml(element.placeholder || '')}" ${previewControlAttrs(element, index)} class="${PREVIEW_INPUT_CLASS}">`)}${helpHtml}`;
    }
};

export const initCompanyDocumentDesigner = (root) => {
    if (!root || root.dataset.designerInit === 'true') {
        return;
    }

    root.dataset.designerInit = 'true';

    const saveElementsUrl = root.dataset.saveElementsUrl || '';
    const saveApprovalsUrl = root.dataset.saveApprovalsUrl || '';
    const uploadImageUrl = root.dataset.uploadImageUrl || '';
    const assetUrl = root.dataset.assetUrl || '';
    const palette = parseJson(root.dataset.palette, {});
    mergeTagSamples = parseJson(root.dataset.mergeTagSamples, {});
    mergeTagLabels = parseJson(root.dataset.mergeTagLabels, {});
    let elements = parseJson(root.dataset.initialElements, []).map((element) => {
        const normalized = {
            ...element,
            settings: normalizeSettings(element.settings),
            options: asObject(element.options),
        };

        if (isFullWidth(normalized)) {
            normalized.settings.pos_x = 0;
        }

        return normalized;
    });
    const previewValues = {};
    elements.forEach((element, index) => {
        if (element.type !== 'signature' || !element.settings?.signature_preview) {
            return;
        }

        const key = previewKeyFor(element, index);
        previewValues[key] = element.settings.signature_preview;
    });
    let steps = parseJson(root.dataset.initialSteps, []);
    let selectedIdx = null;
    let activePaletteCat = Object.keys(palette)[0] || 'BASIC';
    let activeTab = 'build';
    let dragPaletteType = null;
    let dragPaletteTagKey = null;
    let pointerDrag = null;

    const canvas = root.querySelector('[data-designer-canvas]');
    const paletteTabs = root.querySelector('[data-palette-tabs]');
    const paletteList = root.querySelector('[data-palette-list]');
    const paletteSearch = root.querySelector('[data-palette-search]');
    const propertiesPanel = root.querySelector('[data-properties-panel]');
    const propertiesTitle = root.querySelector('[data-properties-title]');
    const propertiesBody = root.querySelector('[data-properties-body]');
    const canvasWrap = root.querySelector('[data-designer-canvas-wrap]');
    const stepsRoot = root.querySelector('[data-approval-steps]');
    const stepTemplate = root.querySelector('[data-approval-step-template]');

    const typeLabel = (type) => type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

    const setActiveTab = (tab) => {
        activeTab = tab;
        root.querySelectorAll('[data-designer-tab]').forEach((button) => {
            const active = button.dataset.designerTab === tab;
            button.classList.toggle('bg-white/15', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('text-white/85', !active);
            button.classList.toggle('cd-designer-top-tab-active', active);
        });

        root.querySelectorAll('[data-designer-panel]').forEach((panel) => {
            const show = panel.dataset.designerPanel === tab;
            panel.classList.toggle('hidden', !show);
            if (panel.dataset.designerPanel === 'approvals') {
                panel.classList.toggle('flex', show);
            }
        });
    };

    const showProperties = (show) => {
        if (!propertiesPanel) {
            return;
        }
        propertiesPanel.classList.toggle('hidden', !show);
        propertiesPanel.classList.toggle('sm:flex', show);
    };

    const getCanvasInner = () => canvas?.querySelector('[data-designer-canvas-inner]');

    const getCanvasInnerWidth = () => getCanvasInner()?.clientWidth || LEGAL_CONTENT_WIDTH_PX;

    const legalPageCountForHeight = (maxBottom) => Math.max(1, Math.ceil(Math.max(LEGAL_CONTENT_HEIGHT_PX, maxBottom) / LEGAL_CONTENT_HEIGHT_PX));

    const renderLegalPageGuides = (pageCount) => {
        if (pageCount <= 1) {
            return '';
        }

        return Array.from({ length: pageCount - 1 }, (_, index) => {
            const y = (index + 1) * LEGAL_CONTENT_HEIGHT_PX;

            return `<div class="cd-legal-page-break" style="top:${y}px;">Page ${index + 2}</div>`;
        }).join('');
    };

    const ensureElementPosition = (element, index) => {
        ensureSettingsObject(element);
        if (hasSavedPosition(element)) {
            return;
        }

        let y = CANVAS_INSET;
        for (let i = 0; i < index; i += 1) {
            const prev = elements[i];
            if (hasSavedPosition(prev)) {
                y = Math.max(y, Number(prev.settings.pos_y) + estimateElementHeight(prev) + FIELD_GAP);
            } else {
                y += estimateElementHeight(prev) + FIELD_GAP;
            }
        }

        element.settings.pos_x = isFullWidth(element) ? 0 : CANVAS_INSET;
        element.settings.pos_y = y;
    };

    const ensureAllPositions = () => {
        elements.forEach((element, index) => ensureElementPosition(element, index));
    };

    const updateCanvasMinHeight = () => {
        const inner = getCanvasInner();
        if (!inner) {
            return;
        }

        let maxBottom = 360;
        elements.forEach((element) => {
            const y = Number(element.settings?.pos_y ?? 0);
            maxBottom = Math.max(maxBottom, y + estimateElementHeight(element) + 48);
        });
        inner.style.minHeight = `${maxBottom}px`;
    };

    const placeNewElement = (element, x = null, y = null) => {
        ensureSettingsObject(element);
        if (x != null && y != null) {
            element.settings.pos_x = Math.max(0, Math.round(x));
            element.settings.pos_y = Math.max(0, Math.round(y));
            return;
        }

        let maxBottom = CANVAS_INSET;
        elements.forEach((existing) => {
            if (hasSavedPosition(existing)) {
                maxBottom = Math.max(maxBottom, Number(existing.settings.pos_y) + estimateElementHeight(existing) + FIELD_GAP);
            }
        });
        element.settings.pos_x = isFullWidth(element) ? 0 : CANVAS_INSET;
        element.settings.pos_y = maxBottom;
    };

    const finishPointerDrag = () => {
        if (!pointerDrag) {
            return;
        }

        const { index, moved, wrap, onMove, onUp } = pointerDrag;
        document.removeEventListener('pointermove', onMove);
        document.removeEventListener('pointerup', onUp);
        document.removeEventListener('pointercancel', onUp);
        document.body.classList.remove('cd-designer-dragging');
        wrap?.classList.remove('opacity-80', 'ring-2', 'ring-[#00A3E6]/40', 'shadow-lg');

        if (!moved) {
            selectedIdx = index;
        }

        pointerDrag = null;
        updateCanvasMinHeight();
        renderAll();
    };

    const startPointerDrag = (index, event) => {
        const inner = getCanvasInner();
        const wraps = [...inner?.querySelectorAll('[data-canvas-wrap]') || []];
        const wrap = wraps[index];
        const element = elements[index];
        if (!wrap || !element || !inner) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const wrapRect = wrap.getBoundingClientRect();
        const innerRect = inner.getBoundingClientRect();
        const startX = event.clientX;
        const startY = event.clientY;

        pointerDrag = {
            index,
            moved: false,
            wrap,
            element,
            innerRect,
            offsetX: event.clientX - wrapRect.left,
            offsetY: event.clientY - wrapRect.top,
            onMove: (moveEvent) => {
                if (!pointerDrag) {
                    return;
                }

                if (!pointerDrag.moved) {
                    const dx = Math.abs(moveEvent.clientX - startX);
                    const dy = Math.abs(moveEvent.clientY - startY);
                    if (dx < 4 && dy < 4) {
                        return;
                    }
                    pointerDrag.moved = true;
                    document.body.classList.add('cd-designer-dragging');
                    wrap.classList.add('opacity-80', 'ring-2', 'ring-[#00A3E6]/40', 'shadow-lg');
                }

                const widthPx = wrap.getBoundingClientRect().width || elementBoxWidth(pointerDrag.innerRect.width, element);
                const rawX = moveEvent.clientX - pointerDrag.innerRect.left - pointerDrag.offsetX;
                const rawY = moveEvent.clientY - pointerDrag.innerRect.top - pointerDrag.offsetY;
                const maxX = Math.max(0, pointerDrag.innerRect.width - widthPx);
                const settings = ensureSettingsObject(element);
                settings.pos_x = Math.round(Math.max(0, Math.min(rawX, maxX)));
                settings.pos_y = Math.round(Math.max(0, rawY));
                wrap.style.left = `${settings.pos_x}px`;
                wrap.style.top = `${settings.pos_y}px`;
            },
            onUp: () => finishPointerDrag(),
        };

        document.addEventListener('pointermove', pointerDrag.onMove);
        document.addEventListener('pointerup', pointerDrag.onUp);
        document.addEventListener('pointercancel', pointerDrag.onUp);
    };

    const startBoxResize = (index, event) => {
        const inner = getCanvasInner();
        const wraps = [...inner?.querySelectorAll('[data-canvas-wrap]') || []];
        const wrap = wraps[index];
        const element = elements[index];
        if (!wrap || !element || !inner) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const wrapRect = wrap.getBoundingClientRect();
        const innerRect = inner.getBoundingClientRect();
        const startX = event.clientX;
        const startY = event.clientY;

        pointerDrag = {
            mode: 'resize',
            index,
            moved: false,
            wrap,
            element,
            innerRect,
            startWidth: wrapRect.width,
            startHeight: wrapRect.height,
            startX,
            startY,
            onMove: (moveEvent) => {
                if (!pointerDrag) {
                    return;
                }

                pointerDrag.moved = true;
                document.body.classList.add('cd-designer-dragging');
                wrap.classList.add('opacity-80', 'ring-2', 'ring-[#00A3E6]/40', 'shadow-lg');

                const maxWidth = Math.max(MIN_BOX_WIDTH, pointerDrag.innerRect.width - (parseFloat(wrap.style.left) || 0));
                const nextWidth = Math.min(maxWidth, pointerDrag.startWidth + (moveEvent.clientX - pointerDrag.startX));
                const nextHeight = pointerDrag.startHeight + (moveEvent.clientY - pointerDrag.startY);
                const size = applyBoxSize(element, nextWidth, nextHeight);

                wrap.style.width = `${size.width}px`;
                wrap.style.maxWidth = '100%';
                wrap.style.height = `${size.height}px`;

                const preview = wrap.querySelector('[data-image-preview], [data-image-upload]');
                if (preview && element.type === 'image') {
                    preview.style.width = `${size.width}px`;
                    preview.style.height = `${size.height}px`;
                }

                const widthInput = propertiesBody?.querySelector('[data-prop-box-width], [data-prop-image-width]');
                const heightInput = propertiesBody?.querySelector('[data-prop-box-height], [data-prop-image-height]');
                if (widthInput) {
                    widthInput.value = String(size.width);
                }
                if (heightInput) {
                    heightInput.value = String(size.height);
                }
            },
            onUp: () => finishPointerDrag(),
        };

        document.addEventListener('pointermove', pointerDrag.onMove);
        document.addEventListener('pointerup', pointerDrag.onUp);
        document.addEventListener('pointercancel', pointerDrag.onUp);
    };

    const startImageResize = (index, event) => startBoxResize(index, event);

    const uploadImageForElement = async (index, file) => {
        const element = elements[index];
        if (!element || !file || !uploadImageUrl) {
            return;
        }

        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch(uploadImageUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            window.alert(data.message || 'Unable to upload image.');
            return;
        }

        element.options = {
            ...asObject(element.options),
            path: data.path,
            original_filename: data.original_filename,
        };
        selectedIdx = index;
        renderAll();
    };

    const insertTagIntoSelectedElement = (tagKey) => {
        if (selectedIdx == null || !tagKey) {
            return false;
        }

        const element = elements[selectedIdx];
        const target = tagInsertTargetForElement(element);

        if (target === null) {
            return false;
        }

        if (target.kind === 'label') {
            const field = propertiesBody?.querySelector('[data-prop="label"][data-tag-insert-target]')
                || propertiesBody?.querySelector('[data-prop="label"]');

            if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
                insertTagIntoField(field, tagKey, (nextValue) => {
                    element.label = nextValue;
                    renderCanvas();
                });

                return true;
            }

            element.label = `${element.label || ''}${inlineTagToken(tagKey)}`;
            renderProperties();
            renderCanvas();

            return true;
        }

        if (target.kind === 'default_text') {
            const settings = ensureSettingsObject(element);
            const field = propertiesBody?.querySelector('[data-prop="default_text"][data-tag-insert-target]')
                || propertiesBody?.querySelector('[data-prop="default_text"]');

            if (field instanceof HTMLTextAreaElement) {
                insertTagIntoField(field, tagKey, (nextValue) => {
                    settings.default_text = nextValue;
                    renderCanvas();
                });

                return true;
            }

            settings.default_text = `${settings.default_text || ''}${inlineTagToken(tagKey)}`;
            renderProperties();
            renderCanvas();

            return true;
        }

        return false;
    };

    const addElement = (type, label, x = null, y = null, tagKey = '') => {
        const element = makeElement(type, label, tagKey);
        const keys = elements.map((el) => el.field_key).filter(Boolean);
        if (INPUT_TYPES.has(type) || (!LAYOUT_TYPES.has(type) && type !== 'merge_tag')) {
            element.field_key = ensureFieldKey(element, keys);
        }
        if (type === 'merge_tag' && label) {
            element.label = label;
        }
        placeNewElement(element, x, y);
        elements.push(element);
        selectedIdx = elements.length - 1;
        renderAll();
    };

    const renderPalette = () => {
        if (!paletteTabs || !paletteList) {
            return;
        }

        const query = (paletteSearch?.value || '').trim().toLowerCase();
        const categories = Object.keys(palette);

        if (!query) {
            paletteTabs.innerHTML = categories.map((cat) => {
                const active = cat === activePaletteCat;
                return `
                    <button type="button" class="relative flex-1 py-3 text-[10px] font-bold uppercase tracking-[0.12em] transition-colors ${active ? 'text-white' : 'text-white/40 hover:text-white/70'}" data-palette-cat="${escapeHtml(cat)}">
                        ${escapeHtml(cat)}
                        ${active ? '<span class="absolute inset-x-1 bottom-0 h-[3px] rounded-t-sm bg-gradient-to-r from-[#00A3E6] to-[#0099D6]"></span>' : ''}
                    </button>
                `;
            }).join('');
        } else {
            paletteTabs.innerHTML = '';
        }

        const items = query
            ? categories.flatMap((cat) => (palette[cat] || []).filter((item) => {
                const labelMatch = item.label.toLowerCase().includes(query);
                const typeMatch = item.type.includes(query);
                const tagMatch = String(item.tag_key || '').includes(query);

                return labelMatch || typeMatch || tagMatch;
            }))
            : (palette[activePaletteCat] || []);

        paletteList.innerHTML = items.length === 0
            ? '<p class="px-4 py-8 text-center text-xs text-white/40">No elements found.</p>'
            : items.map((item) => `
                <button
                    type="button"
                    draggable="true"
                    class="group flex min-h-[46px] w-full cursor-grab items-stretch border-b border-white/[0.04] text-left text-white/90 transition-colors hover:bg-[#00A3E6] hover:text-white active:cursor-grabbing"
                    data-palette-add="${escapeHtml(item.type)}"
                    data-palette-label="${escapeHtml(item.label)}"
                    ${item.tag_key ? `data-palette-tag-key="${escapeHtml(item.tag_key)}"` : ''}
                >
                    <span class="flex w-12 shrink-0 items-center justify-center text-white/50 group-hover:text-white">${PALETTE_ICONS[item.type] || PALETTE_ICONS.short_text}</span>
                    <span class="flex flex-1 items-center px-2 text-sm font-medium">${escapeHtml(item.label)}</span>
                </button>
            `).join('');

        paletteTabs.querySelectorAll('[data-palette-cat]').forEach((button) => {
            button.addEventListener('click', () => {
                activePaletteCat = button.dataset.paletteCat || activePaletteCat;
                renderPalette();
            });
        });

        paletteList.querySelectorAll('[data-palette-add]').forEach((button) => {
            button.addEventListener('dragstart', (event) => {
                dragPaletteType = button.dataset.paletteAdd;
                dragPaletteTagKey = button.dataset.paletteTagKey || '';
                event.dataTransfer.setData('application/x-company-doc-element', button.dataset.paletteAdd || '');
                event.dataTransfer.setData('application/x-company-doc-tag-key', dragPaletteTagKey);
            });
            button.addEventListener('click', () => {
                const paletteTagKey = button.dataset.paletteTagKey || '';

                if (paletteTagKey && insertTagIntoSelectedElement(paletteTagKey)) {
                    return;
                }

                if (activeTagInsertField && paletteTagKey) {
                    const event = new Event('input', { bubbles: true });
                    insertTextAtCursor(activeTagInsertField, inlineTagToken(paletteTagKey));
                    activeTagInsertField.dispatchEvent(event);

                    return;
                }

                addElement(
                    button.dataset.paletteAdd || 'short_text',
                    button.dataset.paletteLabel || '',
                    null,
                    null,
                    paletteTagKey,
                );
            });
        });
    };

    const renderCanvas = async () => {
        if (!canvas) {
            return;
        }

        if (elements.length === 0) {
            canvas.innerHTML = `
                <div class="rounded-lg border-2 border-dashed border-gray-200 p-12 text-center" data-canvas-empty>
                    <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    <p class="text-sm font-medium text-gray-500">Drag elements from the left panel</p>
                    <p class="mt-1 text-xs text-gray-400">Drop anywhere on the canvas — drag fields freely to align X and Y</p>
                </div>
            `;
            return;
        }

        await persistSignaturePreviewValues(canvas, previewValues);
        ensureAllPositions();
        const innerWidth = getCanvasInnerWidth();
        let maxBottom = 360;
        elements.forEach((element) => {
            const y = Number(element.settings?.pos_y ?? 0);
            maxBottom = Math.max(maxBottom, y + estimateElementHeight(element) + 48);
        });

        const pageCount = legalPageCountForHeight(maxBottom);
        const innerHeight = pageCount * LEGAL_CONTENT_HEIGHT_PX;

        canvas.innerHTML = `<div class="cd-legal-paper"><div class="cd-designer-canvas-inner relative" data-designer-canvas-inner style="min-height:${innerHeight}px;">${renderLegalPageGuides(pageCount)}${elements.map((element, index) => {
            const selected = selectedIdx === index;
            const box = fieldBoxSize(innerWidth, element);
            const widthCss = elementBoxWidthCss(innerWidth, element);
            const x = fieldPositionX(element);
            const y = Number(element.settings?.pos_y ?? CANVAS_INSET);
            const lockedHeight = element.type === 'image' || hasCustomBoxHeight(element);
            const heightStyle = lockedHeight ? `height:${box.height}px;` : '';
            const fieldPaddingClass = element.type === 'image' ? 'p-0' : 'p-2';
            const fieldOverflowClass = element.type === 'image' ? 'overflow-visible' : 'overflow-auto';
            const boxLeft = isFullWidth(element) && element.type !== 'image' ? '0' : `${x}px`;
            return `
                <div
                    class="group absolute box-border min-w-0"
                    style="left:${boxLeft};top:${y}px;width:${widthCss};${heightStyle}max-width:100%;z-index:${selected ? 30 : 10}"
                    data-canvas-wrap="${index}"
                >
                    <div
                        class="relative h-full w-full max-w-full min-w-0 cursor-grab rounded transition-all duration-150 active:cursor-grabbing ${selected ? `ring-2 ring-[#00A3E6]${element.type === 'image' ? '' : ' bg-[#00A3E6]/[0.04]'}` : 'hover:ring-1 hover:ring-[#00A3E6]/50'}"
                        data-canvas-index="${index}"
                    >
                        <div class="cd-designer-field-body ${fieldPaddingClass} ${fieldOverflowClass} h-full min-h-0 w-full max-w-full">${renderFieldPreview(element, index, previewValues, assetUrl)}</div>
                        <div
                            role="button"
                            tabindex="0"
                            class="cd-designer-resize-handle absolute bottom-1 right-1 z-50 flex h-5 w-5 cursor-se-resize items-center justify-center rounded-sm bg-[#00A3E6] text-white shadow ${selected ? '' : 'pointer-events-none opacity-0 group-hover:pointer-events-auto group-hover:opacity-100'}"
                            data-box-resize="${index}"
                            title="Drag to resize width and height"
                            aria-label="Drag to resize"
                        >
                            <svg class="pointer-events-none h-3 w-3" viewBox="0 0 10 10" fill="currentColor"><path d="M9 1v8H1l8-8z"/></svg>
                        </div>
                    </div>
                    <div class="absolute right-1 top-1/2 z-40 flex -translate-y-1/2 flex-col items-center gap-2 ${selected ? '' : 'pointer-events-none opacity-0 group-hover:pointer-events-auto group-hover:opacity-100'}" data-canvas-actions="${index}">
                        <div
                            role="button"
                            tabindex="0"
                            class="flex h-8 w-8 cursor-grab touch-none select-none items-center justify-center rounded-full border border-gray-200 bg-white text-gray-400 shadow-sm hover:border-gray-300 hover:text-gray-600 active:cursor-grabbing"
                            data-drag-handle="${index}"
                            title="Drag to move"
                            aria-label="Drag to move"
                        >
                            <svg class="pointer-events-none h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                        </div>
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full bg-[#3c4252] text-white shadow-sm hover:bg-[#4a5168]" data-select-element="${index}" title="Properties">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full bg-red-500 text-white shadow-sm hover:bg-red-600" data-remove-element="${index}" title="Delete">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('')}</div></div>`;

        canvas.querySelectorAll('[data-canvas-index]').forEach((row) => {
            const index = Number(row.dataset.canvasIndex);
            row.addEventListener('pointerdown', (event) => {
                if (event.target.closest('[data-preview-control], [data-preview-input], [data-signature-pad], button, a, textarea, input, select, [data-drag-handle], [data-select-element], [data-remove-element], [data-image-upload], [data-image-file], [data-box-resize], [data-image-resize], label[data-image-upload]')) {
                    return;
                }
                if (event.button !== 0) {
                    return;
                }
                startPointerDrag(index, event);
            });
        });

        canvas.querySelectorAll('[data-preview-input]').forEach((input) => {
            const key = input.dataset.previewKey;
            if (!key) {
                return;
            }

            ['mousedown', 'click', 'focus', 'pointerdown'].forEach((eventName) => {
                input.addEventListener(eventName, (event) => event.stopPropagation());
            });

            const syncValue = () => {
                if (input.type === 'checkbox') {
                    previewValues[key] = input.checked;
                    return;
                }
                if (input.type === 'file') {
                    previewValues[key] = input.files?.[0]?.name || '';
                    return;
                }
                previewValues[key] = input.value;
            };

            input.addEventListener('input', syncValue);
            input.addEventListener('change', syncValue);
        });

        canvas.querySelectorAll('[data-drag-handle]').forEach((handle) => {
            handle.addEventListener('pointerdown', (event) => {
                if (event.button !== 0) {
                    return;
                }
                startPointerDrag(Number(handle.dataset.dragHandle), event);
            });
        });

        canvas.querySelectorAll('[data-select-element]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                selectedIdx = Number(button.dataset.selectElement);
                renderAll();
            });
        });

        canvas.querySelectorAll('[data-remove-element]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const index = Number(button.dataset.removeElement);
                elements.splice(index, 1);
                selectedIdx = selectedIdx === index ? null : (selectedIdx != null && selectedIdx > index ? selectedIdx - 1 : selectedIdx);
                renderAll();
            });
        });

        canvas.querySelectorAll('[data-image-file]').forEach((input) => {
            input.addEventListener('change', () => {
                const index = Number(input.dataset.imageFile);
                const file = input.files?.[0];
                if (file) {
                    uploadImageForElement(index, file);
                }
            });
            ['mousedown', 'click', 'focus', 'pointerdown'].forEach((eventName) => {
                input.addEventListener(eventName, (event) => event.stopPropagation());
            });
        });

        canvas.querySelectorAll('[data-image-upload]').forEach((label) => {
            ['pointerdown', 'mousedown', 'click'].forEach((eventName) => {
                label.addEventListener(eventName, (event) => event.stopPropagation());
            });
        });

        canvas.querySelectorAll('[data-box-resize], [data-image-resize]').forEach((handle) => {
            handle.addEventListener('pointerdown', (event) => {
                if (event.button !== 0) {
                    return;
                }
                startBoxResize(Number(handle.dataset.boxResize || handle.dataset.imageResize), event);
            });
        });

        mountSignaturePads(canvas);
        await restoreSignaturePreviewValues(canvas, previewValues);
        updateCanvasMinHeight();
    };

    bindSignaturePreviewPersistence(canvas, previewValues);

    canvas?.addEventListener('signature-pad:change', (event) => {
        const pad = event.target.closest('[data-signature-pad]');
        const key = pad?.dataset.previewKey;

        if (!key) {
            return;
        }

        const elementIndex = elements.findIndex((element, index) => previewKeyFor(element, index) === key);

        if (elementIndex < 0) {
            return;
        }

        const settings = ensureSettingsObject(elements[elementIndex]);
        const state = event.detail?.state ?? null;

        if (state) {
            settings.signature_preview = state;
        } else {
            delete settings.signature_preview;
        }
    });

    const syncSignaturePreviewSettings = async () => {
        await persistSignaturePreviewValues(canvas, previewValues);

        elements.forEach((element, index) => {
            if (element.type !== 'signature') {
                return;
            }

            const key = previewKeyFor(element, index);
            const state = previewValues[key] ?? null;
            const settings = ensureSettingsObject(element);

            if (state) {
                settings.signature_preview = state;
            } else {
                delete settings.signature_preview;
            }
        });
    };

    const renderProperties = () => {
        if (selectedIdx == null || !elements[selectedIdx]) {
            showProperties(false);
            return;
        }

        const element = elements[selectedIdx];
        const isInput = INPUT_TYPES.has(element.type);
        const isMergeTag = element.type === 'merge_tag';
        const hasChoices = ['dropdown', 'radio'].includes(element.type);
        showProperties(true);

        if (propertiesTitle) {
            propertiesTitle.textContent = isMergeTag ? 'Tag Properties' : `${typeLabel(element.type)} Properties`;
        }

        if (!propertiesBody) {
            return;
        }

        if (isMergeTag) {
            const canvasWidth = getCanvasInnerWidth();
            const boxSize = fieldBoxSize(canvasWidth, element);

            propertiesBody.innerHTML = `
                <div class="border-b border-white/[0.06] px-4 py-4">
                    <label class="mb-1.5 block text-[11px] font-medium text-white/70">Tag</label>
                    <p class="text-sm font-medium text-white">${escapeHtml(element.label || '')}</p>
                    <p class="mt-1 font-mono text-[10px] text-white/40">${escapeHtml(mergeTagKey(element))}</p>
                </div>
                <div class="border-b border-white/[0.06] px-4 py-4">
                    <label class="mb-1.5 block text-[11px] font-medium text-white/70">Displayed text</label>
                    <p class="text-sm text-white/90">${escapeHtml(mergeTagLabel(element))}</p>
                    <p class="mt-2 text-[10px] text-white/40">Tag name is shown in design and preview. Live values appear when the document is generated for an employee.</p>
                </div>
                <div class="border-b border-white/[0.06] px-4 py-4">
                    <label class="mb-1.5 block text-[11px] font-medium text-white/70">Size</label>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-[10px] text-white/50">Width (px)</label>
                            <input type="number" min="${MIN_BOX_WIDTH}" step="1" class="cd-dark-input w-full" data-prop-box-width value="${boxSize.width}">
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] text-white/50">Height (px)</label>
                            <input type="number" min="${MIN_BOX_HEIGHT}" step="1" class="cd-dark-input w-full" data-prop-box-height value="${boxSize.height}">
                        </div>
                    </div>
                </div>
            `;

            propertiesBody.querySelector('[data-prop-box-width]')?.addEventListener('input', (event) => {
                applyBoxSize(element, event.target.value, boxSize.height);
                renderCanvas();
            });
            propertiesBody.querySelector('[data-prop-box-height]')?.addEventListener('input', (event) => {
                applyBoxSize(element, boxSize.width, event.target.value);
                renderCanvas();
            });

            return;
        }

        const choicesHtml = hasChoices
            ? (element.options?.choices || []).map((choice, choiceIndex) => `
                <div class="mb-2 flex gap-2">
                    <input type="text" class="cd-dark-input flex-1" data-choice-label="${choiceIndex}" value="${escapeHtml(choice.label || '')}" placeholder="Label">
                    <button type="button" class="text-xs text-red-400 hover:text-red-300" data-remove-choice="${choiceIndex}">×</button>
                </div>
            `).join('')
            : '';

        const isParagraph = element.type === 'paragraph';
        const supportsInlineTags = ['paragraph', 'heading', 'long_text', 'short_text'].includes(element.type);
        const tagInsertControlsHtml = supportsInlineTags ? buildTagInsertControlsHtml() : '';
        const labelFieldHtml = isParagraph
            ? `${buildParagraphFormatToolbarHtml()}<textarea rows="8" class="cd-dark-input min-h-[140px] w-full resize-y whitespace-pre-wrap" data-prop="label" data-tag-insert-target>${escapeHtml(element.label || '')}</textarea>
                <p class="mt-1.5 text-[10px] text-white/40">Press Enter to start a new line. Insert tags inline with your text.</p>
                ${tagInsertControlsHtml}`
            : element.type === 'heading'
                ? `<input type="text" class="cd-dark-input w-full" data-prop="label" data-tag-insert-target value="${escapeHtml(element.label || '')}">
                ${tagInsertControlsHtml}`
                : `<input type="text" class="cd-dark-input w-full" data-prop="label" value="${escapeHtml(element.label || '')}">`;
        const defaultTextFieldHtml = ['long_text', 'short_text'].includes(element.type)
            ? `<div class="border-b border-white/[0.06] px-4 py-4">
                <label class="mb-1.5 block text-[11px] font-medium text-white/70">Default text</label>
                <textarea rows="6" class="cd-dark-input min-h-[120px] w-full resize-y whitespace-pre-wrap" data-prop="default_text" data-tag-insert-target>${escapeHtml(element.settings?.default_text || '')}</textarea>
                <p class="mt-1.5 text-[10px] text-white/40">Optional pre-filled value on the form. Tags can be mixed with typed text.</p>
                ${tagInsertControlsHtml}
            </div>`
            : '';

        const canvasWidth = getCanvasInnerWidth();
        const boxSize = fieldBoxSize(canvasWidth, element);
        const presetIsActive = (preset) => {
            if (hasCustomBoxWidth(element)) {
                const expected = preset === 'full'
                    ? canvasWidth
                    : (preset === 'half' ? Math.floor(canvasWidth * 0.5) : Math.floor(canvasWidth / 3));

                return Math.abs(boxSize.width - expected) <= 2;
            }

            return (element.width || 'full') === preset;
        };

        const paragraphTypographyHtml = isParagraph ? buildParagraphTypographyControlsHtml(element) : '';

        propertiesBody.innerHTML = `
            <div class="border-b border-white/[0.06] px-4 py-4">
                <label class="mb-1.5 block text-[11px] font-medium text-white/70">${isParagraph ? 'Paragraph text' : element.type === 'long_text' || element.type === 'short_text' ? 'Field label' : 'Field Label'}</label>
                ${labelFieldHtml}
            </div>
            ${paragraphTypographyHtml}
            ${defaultTextFieldHtml}
            ${isInput && element.type !== 'checkbox' ? `
            <div class="border-b border-white/[0.06] px-4 py-4">
                <div class="mb-0.5 text-[11px] font-bold text-white/90">Label Alignment</div>
                <div class="mb-2.5 text-[10px] text-white/40">Select how the label text is aligned horizontally</div>
                <div class="grid grid-cols-3 gap-1">
                    ${['left', 'right', 'top'].map((align) => `
                        <button type="button" class="rounded py-1.5 text-[10px] font-bold uppercase transition ${(element.settings?.label_align || 'top') === align ? 'bg-[#00A3E6] text-white' : 'bg-[#1e2230] text-white/50 hover:bg-white/10 hover:text-white'}" data-prop-label-align="${align}">${align}</button>
                    `).join('')}
                </div>
            </div>` : ''}
            ${element.type !== 'page_break' ? `
            <div class="border-b border-white/[0.06] px-4 py-4">
                <label class="mb-1.5 block text-[11px] font-medium text-white/70">Size</label>
                ${element.type !== 'image' ? `
                <div class="mb-2 grid grid-cols-3 gap-1">
                    ${['full', 'half', 'third'].map((width) => `
                        <button type="button" class="rounded py-1.5 text-[10px] font-bold uppercase transition ${presetIsActive(width) ? 'bg-[#00A3E6] text-white' : 'bg-[#1e2230] text-white/50 hover:bg-white/10 hover:text-white'}" data-prop-width="${width}">${width}</button>
                    `).join('')}
                </div>` : ''}
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-[10px] text-white/50">Width (px)</label>
                        <input type="number" min="${element.type === 'image' ? 40 : MIN_BOX_WIDTH}" step="1" class="cd-dark-input w-full" data-prop-box-width value="${boxSize.width}">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] text-white/50">Height (px)</label>
                        <input type="number" min="${element.type === 'image' ? 40 : MIN_BOX_HEIGHT}" step="1" class="cd-dark-input w-full" data-prop-box-height value="${boxSize.height}">
                    </div>
                </div>
                <p class="mt-2 text-[10px] text-white/40">Drag the blue corner handle on the canvas to resize freely.</p>
            </div>` : ''}
            ${element.type === 'image' ? `
            <div class="border-b border-white/[0.06] px-4 py-4">
                <label class="mb-1.5 block text-[11px] font-medium text-white/70">Image</label>
                ${element.options?.path ? `<p class="mb-2 truncate text-[10px] text-white/50">${escapeHtml(element.options.original_filename || 'Uploaded image')}</p>` : ''}
                <div class="flex flex-wrap gap-2">
                    <label class="cursor-pointer rounded bg-[#00A3E6] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#0099D6]">
                        ${element.options?.path ? 'Replace' : 'Upload'}
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" data-prop-image-upload>
                    </label>
                    ${element.options?.path ? '<button type="button" class="rounded bg-white/10 px-3 py-1.5 text-xs text-white/70 hover:bg-white/20" data-prop-image-remove>Remove</button>' : ''}
                </div>
                <div class="mt-3">
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-[10px] text-white/50">Opacity</label>
                        <span class="text-[10px] tabular-nums text-white/70" data-prop-image-opacity-label>${imageOpacity(element)}%</span>
                    </div>
                    <input type="range" min="0" max="100" step="1" class="cd-dark-range w-full" data-prop-image-opacity value="${imageOpacity(element)}">
                    <p class="mt-1.5 text-[10px] text-white/40">0% is fully transparent, 100% is fully visible.</p>
                </div>
                <div class="mt-3">
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-[10px] text-white/50">Rotation</label>
                        <span class="text-[10px] tabular-nums text-white/70" data-prop-image-rotate-label>${imageRotate(element)}°</span>
                    </div>
                    <input type="range" min="0" max="359" step="1" class="cd-dark-range w-full" data-prop-image-rotate value="${imageRotate(element)}">
                    <div class="mt-2 flex gap-1">
                        <button type="button" class="flex-1 rounded bg-white/10 px-2 py-1.5 text-[10px] font-medium text-white/70 hover:bg-white/20" data-prop-image-rotate-step="-90">-90°</button>
                        <button type="button" class="flex-1 rounded bg-white/10 px-2 py-1.5 text-[10px] font-medium text-white/70 hover:bg-white/20" data-prop-image-rotate-step="90">+90°</button>
                        <button type="button" class="flex-1 rounded bg-white/10 px-2 py-1.5 text-[10px] font-medium text-white/70 hover:bg-white/20" data-prop-image-rotate-reset>Reset</button>
                    </div>
                    <p class="mt-1.5 text-[10px] text-white/40">Drag the slider or use 90° steps. Rotation is saved with the form.</p>
                </div>
            </div>` : ''}
            <div class="border-b border-white/[0.06] px-4 py-4">
                <label class="mb-1.5 block text-[11px] font-medium text-white/70">Position (px)</label>
                <div class="mb-1 text-[10px] text-white/40">Drag on canvas or enter exact X / Y coordinates</div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-[10px] text-white/50">X</label>
                        <input type="number" min="0" step="1" class="cd-dark-input w-full" data-prop-pos-x value="${fieldPositionX(element)}">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] text-white/50">Y</label>
                        <input type="number" min="0" step="1" class="cd-dark-input w-full" data-prop-pos-y value="${Number(element.settings?.pos_y ?? 0)}">
                    </div>
                </div>
            </div>
            ${isInput ? `
            <div class="border-b border-white/[0.06] px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[13px] text-white">Required</div>
                        <div class="mt-0.5 text-[10px] text-white/40">Prevent submission if empty</div>
                    </div>
                    <button type="button" class="relative inline-flex h-[22px] w-10 shrink-0 items-center rounded-full transition ${element.is_required ? 'bg-[#00A3E6]' : 'bg-white/20'}" data-prop-toggle="is_required">
                        <span class="inline-block h-[18px] w-[18px] transform rounded-full bg-white shadow transition ${element.is_required ? 'translate-x-[20px]' : 'translate-x-[2px]'}"></span>
                    </button>
                </div>
            </div>` : ''}
            ${['short_text', 'long_text', 'email', 'number', 'date'].includes(element.type) ? `
            <div class="border-b border-white/[0.06] px-4 py-4">
                <label class="mb-1.5 block text-[11px] font-medium text-white/70">Placeholder</label>
                <input type="text" class="cd-dark-input w-full" data-prop="placeholder" value="${escapeHtml(element.placeholder || '')}">
            </div>` : ''}
            <div class="border-b border-white/[0.06] px-4 py-4">
                <label class="mb-1.5 block text-[11px] font-medium text-white/70">Help text</label>
                <textarea rows="2" class="cd-dark-input w-full" data-prop="help_text">${escapeHtml(element.help_text || '')}</textarea>
            </div>
            ${hasChoices ? `
            <div class="border-b border-white/[0.06] px-4 py-4">
                <div class="mb-2 flex items-center justify-between">
                    <label class="text-[11px] font-medium text-white/70">Choices</label>
                    <button type="button" class="text-xs text-[#7dd3fc] hover:text-white" data-add-choice>+ Add</button>
                </div>
                ${choicesHtml}
            </div>` : ''}
            <div class="border-b border-white/[0.06] px-4 py-4">
                <label class="mb-1.5 block text-[11px] font-medium text-white/70">Field key</label>
                <input type="text" class="cd-dark-input w-full font-mono" data-prop="field_key" value="${escapeHtml(element.field_key || '')}">
            </div>
        `;

        const labelField = propertiesBody.querySelector('[data-prop="label"]');
        wireTagInsertField(labelField, (nextValue) => {
            element.label = nextValue;
            renderCanvas();
        });
        labelField?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && event.target instanceof HTMLTextAreaElement) {
                event.stopPropagation();
            }
        });

        propertiesBody.querySelectorAll('[data-format-tag]').forEach((button) => {
            button.addEventListener('click', () => {
                const tagName = String(button.dataset.formatTag || '');

                if (!tagName || !labelField) {
                    return;
                }

                const nextValue = wrapSelectionWithFormatTag(labelField, tagName);
                element.label = nextValue;
                renderCanvas();
            });
        });

        const defaultTextField = propertiesBody.querySelector('[data-prop="default_text"]');
        wireTagInsertField(defaultTextField, (nextValue) => {
            ensureSettingsObject(element).default_text = nextValue;
            renderCanvas();
        });

        propertiesBody.querySelector('[data-insert-tag-button]')?.addEventListener('click', () => {
            const tagKey = propertiesBody.querySelector('[data-insert-tag-select]')?.value || '';

            if (tagKey) {
                insertTagIntoSelectedElement(tagKey);
            }
        });

        propertiesBody.querySelector('[data-prop="placeholder"]')?.addEventListener('input', (event) => {
            element.placeholder = event.target.value;
            renderCanvas();
        });

        propertiesBody.querySelector('[data-prop="help_text"]')?.addEventListener('input', (event) => {
            element.help_text = event.target.value;
        });

        propertiesBody.querySelector('[data-prop="field_key"]')?.addEventListener('input', (event) => {
            element.field_key = slugify(event.target.value);
        });

        propertiesBody.querySelector('[data-prop-font-family]')?.addEventListener('change', (event) => {
            const settings = ensureSettingsObject(element);
            const value = String(event.target.value || '');
            settings.font_family = PARAGRAPH_FONT_FAMILIES.some((option) => option.value === value) ? value : '';
            renderCanvas();
        });

        propertiesBody.querySelector('[data-prop-font-size]')?.addEventListener('input', (event) => {
            const settings = ensureSettingsObject(element);
            settings.font_size = paragraphFontSize({ settings: { ...settings, font_size: event.target.value } });
            renderCanvas();
        });

        propertiesBody.querySelector('[data-prop-font-color]')?.addEventListener('input', (event) => {
            const settings = ensureSettingsObject(element);
            settings.font_color = clampParagraphFontColor(event.target.value);
            renderCanvas();
        });

        propertiesBody.querySelector('[data-prop-toggle="is_required"]')?.addEventListener('click', () => {
            element.is_required = !element.is_required;
            renderProperties();
            renderCanvas();
        });

        propertiesBody.querySelectorAll('[data-prop-width]').forEach((button) => {
            button.addEventListener('click', () => {
                const preset = button.dataset.propWidth || 'full';
                const canvasW = getCanvasInnerWidth();
                const settings = ensureSettingsObject(element);
                element.width = preset;
                if (preset === 'full') {
                    settings.box_width = canvasW;
                    settings.pos_x = 0;
                } else if (preset === 'half') {
                    settings.box_width = Math.max(MIN_BOX_WIDTH, Math.floor(canvasW * 0.5));
                } else {
                    settings.box_width = Math.max(MIN_BOX_WIDTH, Math.floor(canvasW / 3));
                }
                renderProperties();
                renderCanvas();
            });
        });

        propertiesBody.querySelectorAll('[data-prop-label-align]').forEach((button) => {
            button.addEventListener('click', () => {
                element.settings = element.settings || {};
                element.settings.label_align = button.dataset.propLabelAlign || 'top';
                renderProperties();
                renderCanvas();
            });
        });

        const syncPosition = (axis, value) => {
            const settings = ensureSettingsObject(element);
            settings[axis] = Math.max(0, Number(value) || 0);
            renderCanvas();
        };

        propertiesBody.querySelector('[data-prop-pos-x]')?.addEventListener('input', (event) => {
            syncPosition('pos_x', event.target.value);
        });

        propertiesBody.querySelector('[data-prop-pos-y]')?.addEventListener('input', (event) => {
            syncPosition('pos_y', event.target.value);
        });

        propertiesBody.querySelector('[data-prop-image-upload]')?.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            if (file && selectedIdx != null) {
                uploadImageForElement(selectedIdx, file);
            }
        });

        propertiesBody.querySelector('[data-prop-image-remove]')?.addEventListener('click', () => {
            element.options = {};
            renderAll();
        });

        const syncImageDimension = (axis, value) => {
            const settings = ensureSettingsObject(element);
            settings[axis] = Math.max(40, Number(value) || 40);
            renderCanvas();
        };

        const syncBoxDimension = (axis, value) => {
            const current = fieldBoxSize(getCanvasInnerWidth(), element);
            if (axis === 'width') {
                applyBoxSize(element, value, current.height);
            } else {
                applyBoxSize(element, current.width, value);
            }
            renderCanvas();
        };

        propertiesBody.querySelector('[data-prop-box-width]')?.addEventListener('input', (event) => {
            syncBoxDimension('width', event.target.value);
        });

        propertiesBody.querySelector('[data-prop-box-height]')?.addEventListener('input', (event) => {
            syncBoxDimension('height', event.target.value);
        });

        propertiesBody.querySelector('[data-prop-image-width]')?.addEventListener('input', (event) => {
            syncImageDimension('image_width', event.target.value);
        });

        propertiesBody.querySelector('[data-prop-image-height]')?.addEventListener('input', (event) => {
            syncImageDimension('image_height', event.target.value);
        });

        const applyImageOpacity = (value) => {
            const settings = ensureSettingsObject(element);
            settings.image_opacity = clampImageOpacity(value);
            const preview = canvas?.querySelector(`[data-canvas-wrap="${selectedIdx}"] [data-image-preview], [data-canvas-wrap="${selectedIdx}"] [data-image-upload]`);
            if (preview) {
                preview.style.opacity = String(settings.image_opacity / 100);
            }
            const label = propertiesBody.querySelector('[data-prop-image-opacity-label]');
            if (label) {
                label.textContent = `${settings.image_opacity}%`;
            }
        };

        propertiesBody.querySelector('[data-prop-image-opacity]')?.addEventListener('input', (event) => {
            applyImageOpacity(event.target.value);
        });

        const applyImageRotate = (value) => {
            const settings = ensureSettingsObject(element);
            settings.image_rotate = clampImageRotate(value);
            const img = canvas?.querySelector(`[data-canvas-wrap="${selectedIdx}"] [data-image-preview] img`);
            if (img) {
                img.style.transform = `rotate(${settings.image_rotate}deg)`;
                img.style.transformOrigin = 'center center';
            }
            const slider = propertiesBody.querySelector('[data-prop-image-rotate]');
            const label = propertiesBody.querySelector('[data-prop-image-rotate-label]');
            if (slider) {
                slider.value = String(settings.image_rotate);
            }
            if (label) {
                label.textContent = `${settings.image_rotate}°`;
            }
        };

        propertiesBody.querySelector('[data-prop-image-rotate]')?.addEventListener('input', (event) => {
            applyImageRotate(event.target.value);
        });

        propertiesBody.querySelectorAll('[data-prop-image-rotate-step]').forEach((button) => {
            button.addEventListener('click', () => {
                applyImageRotate(imageRotate(element) + Number(button.dataset.propImageRotateStep || 0));
            });
        });

        propertiesBody.querySelector('[data-prop-image-rotate-reset]')?.addEventListener('click', () => {
            applyImageRotate(DEFAULT_IMAGE_ROTATE);
        });

        propertiesBody.querySelector('[data-add-choice]')?.addEventListener('click', () => {
            element.options = element.options || {};
            element.options.choices = element.options.choices || [];
            const next = element.options.choices.length + 1;
            element.options.choices.push({ label: `Option ${next}`, value: `option_${next}` });
            renderProperties();
            renderCanvas();
        });

        propertiesBody.querySelectorAll('[data-choice-label]').forEach((input) => {
            input.addEventListener('input', () => {
                const choiceIndex = Number(input.dataset.choiceLabel);
                const choice = element.options.choices[choiceIndex];
                if (choice) {
                    choice.label = input.value;
                    choice.value = slugify(input.value || `option_${choiceIndex + 1}`);
                    renderCanvas();
                }
            });
        });

        propertiesBody.querySelectorAll('[data-remove-choice]').forEach((button) => {
            button.addEventListener('click', () => {
                element.options.choices.splice(Number(button.dataset.removeChoice), 1);
                renderProperties();
                renderCanvas();
            });
        });
    };

    const renderAll = () => {
        renderPalette();
        renderCanvas();
        renderProperties();
    };

    paletteSearch?.addEventListener('input', renderPalette);

    canvasWrap?.addEventListener('click', () => {
        selectedIdx = null;
        renderAll();
    });

    canvas?.addEventListener('dragover', (event) => event.preventDefault());
    canvas?.addEventListener('drop', (event) => {
        event.preventDefault();
        const type = event.dataTransfer.getData('application/x-company-doc-element') || dragPaletteType;
        const tagKey = event.dataTransfer.getData('application/x-company-doc-tag-key') || dragPaletteTagKey || '';
        if (!type) {
            return;
        }

        const inner = getCanvasInner();
        if (inner) {
            const rect = inner.getBoundingClientRect();
            addElement(type, '', event.clientX - rect.left - 24, event.clientY - rect.top - 16, tagKey);
        } else {
            addElement(type, '', null, null, tagKey);
        }

        dragPaletteType = null;
        dragPaletteTagKey = null;
    });

    root.querySelector('[data-close-properties]')?.addEventListener('click', () => {
        selectedIdx = null;
        renderAll();
    });

    /* ── Approvals ── */
    const readAssigneeRow = (row) => {
        const type = row.querySelector('[data-field="assignee_type"]')?.value || 'user';
        return {
            assignee_type: type,
            user_id: type === 'user' ? Number(row.querySelector('[data-field="user_id"]')?.value || 0) || null : null,
            role_id: type === 'role' ? Number(row.querySelector('[data-field="role_id"]')?.value || 0) || null : null,
        };
    };

    const syncAssigneeTypeVisibility = (row) => {
        const type = row.querySelector('[data-field="assignee_type"]')?.value || 'user';
        row.querySelector('[data-field="user_id"]')?.classList.toggle('hidden', type !== 'user');
        row.querySelector('[data-field="role_id"]')?.classList.toggle('hidden', type !== 'role');
    };

    const bindAssigneeRow = (row) => {
        row.querySelector('[data-field="assignee_type"]')?.addEventListener('change', () => syncAssigneeTypeVisibility(row));
        row.querySelector('[data-remove-assignee]')?.addEventListener('click', () => row.remove());
        syncAssigneeTypeVisibility(row);
    };

    const createStepRow = (step = {}) => {
        if (!stepTemplate || !stepsRoot) {
            return null;
        }

        const templateStep = stepTemplate.content?.querySelector('[data-approval-step]')
            || stepTemplate.content?.firstElementChild;
        if (!templateStep) {
            return null;
        }

        const row = templateStep.cloneNode(true);
        const stepNumber = row.querySelector('[data-step-number]');
        if (stepNumber) {
            stepNumber.textContent = String(stepsRoot.querySelectorAll('[data-approval-step]').length + 1);
        }

        row.querySelector('[data-field="name"]').value = step.name || 'Approver';
        row.querySelector('[data-field="mode"]').value = step.mode || 'single';
        row.querySelector('[data-field="instructions"]').value = step.instructions || '';
        row.querySelector('[data-field="optional"]').checked = Boolean(step.optional);

        const assigneeTemplate = stepTemplate.content?.querySelector('[data-assignee-row]');
        const assigneeRows = row.querySelector('[data-assignee-rows]');
        assigneeRows.innerHTML = '';
        const assignees = Array.isArray(step.assignees) && step.assignees.length ? step.assignees : [{ assignee_type: 'user' }];
        assignees.forEach((assignee) => {
            const clone = assigneeTemplate?.cloneNode(true);
            if (!clone) {
                return;
            }
            clone.querySelector('[data-field="assignee_type"]').value = assignee.assignee_type || 'user';
            if (assignee.user_id) {
                clone.querySelector('[data-field="user_id"]').value = String(assignee.user_id);
            }
            if (assignee.role_id) {
                clone.querySelector('[data-field="role_id"]').value = String(assignee.role_id);
            }
            assigneeRows.appendChild(clone);
            bindAssigneeRow(clone);
        });

        row.querySelector('[data-remove-approval-step]')?.addEventListener('click', () => {
            row.remove();
            stepsRoot.querySelectorAll('[data-approval-step]').forEach((stepRow, stepIndex) => {
                const number = stepRow.querySelector('[data-step-number]');
                if (number) {
                    number.textContent = String(stepIndex + 1);
                }
            });
        });

        row.querySelector('[data-add-assignee]')?.addEventListener('click', () => {
            const first = row.querySelector('[data-assignee-row]');
            if (!first) {
                return;
            }
            const clone = first.cloneNode(true);
            clone.querySelectorAll('select').forEach((select) => {
                if (select.dataset.field === 'assignee_type') {
                    select.value = 'user';
                } else {
                    select.value = '';
                }
            });
            assigneeRows.appendChild(clone);
            bindAssigneeRow(clone);
        });

        return row;
    };

    const renderSteps = () => {
        if (!stepsRoot) {
            return;
        }
        stepsRoot.innerHTML = '';
        (steps.length ? steps : []).forEach((step) => {
            const row = createStepRow(step);
            if (row) {
                stepsRoot.appendChild(row);
            }
        });
    };

    const collectSteps = () => [...stepsRoot?.querySelectorAll('[data-approval-step]') || []].map((row) => ({
        name: row.querySelector('[data-field="name"]')?.value || 'Approver',
        mode: row.querySelector('[data-field="mode"]')?.value || 'single',
        optional: row.querySelector('[data-field="optional"]')?.checked || false,
        instructions: row.querySelector('[data-field="instructions"]')?.value || '',
        assignees: [...row.querySelectorAll('[data-assignee-row]')].map(readAssigneeRow).filter((assignee) => assignee.user_id || assignee.role_id),
    }));

    root.querySelectorAll('[data-designer-tab]').forEach((button) => {
        button.addEventListener('click', () => setActiveTab(button.dataset.designerTab || 'build'));
    });

    root.querySelector('[data-add-approval-step]')?.addEventListener('click', () => {
        const row = createStepRow({ name: 'Approver', mode: 'single', assignees: [{ assignee_type: 'user' }] });
        if (row && stepsRoot) {
            stepsRoot.appendChild(row);
        }
    });

    const saveElements = async () => {
        await syncSignaturePreviewSettings();

        const payload = elements.map((element, index) => {
            const settings = ensureSettingsObject(element);

            return {
                type: element.type,
                label: element.label,
                field_key: element.field_key ? slugify(element.field_key) : null,
                placeholder: element.placeholder ?? null,
                help_text: element.help_text ?? null,
                is_required: Boolean(element.is_required),
                width: element.width || 'full',
                sort_order: index,
                options: asObject(element.options),
                validation: element.validation ?? null,
                conditional: element.conditional ?? null,
                settings: {
                    label_align: settings.label_align || 'top',
                    pos_x: Math.round(Number(settings.pos_x ?? 0)),
                    pos_y: Math.round(Number(settings.pos_y ?? 0)),
                    ...(settings.rows ? { rows: settings.rows } : {}),
                    ...(hasCustomBoxWidth(element) ? { box_width: Math.round(Number(settings.box_width)) } : {}),
                    ...(hasCustomBoxHeight(element) ? { box_height: Math.round(Number(settings.box_height)) } : {}),
                    ...(element.type === 'image' ? {
                        image_width: Math.round(Number(settings.image_width ?? DEFAULT_IMAGE_WIDTH)),
                        image_height: Math.round(Number(settings.image_height ?? DEFAULT_IMAGE_HEIGHT)),
                        image_opacity: clampImageOpacity(settings.image_opacity ?? DEFAULT_IMAGE_OPACITY),
                        image_rotate: clampImageRotate(settings.image_rotate ?? DEFAULT_IMAGE_ROTATE),
                    } : {}),
                    ...(element.type === 'merge_tag' && mergeTagKey(element) ? { tag_key: mergeTagKey(element) } : {}),
                    ...(element.settings?.default_text !== undefined ? { default_text: element.settings.default_text } : {}),
                    ...(element.type === 'signature' && settings.signature_preview ? { signature_preview: settings.signature_preview } : {}),
                    ...(element.type === 'paragraph' ? {
                        font_family: paragraphFontFamily(element),
                        font_size: paragraphFontSize(element),
                        font_color: paragraphFontColor(element),
                    } : {}),
                },
            };
        });

        const response = await fetch(saveElementsUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ elements: payload }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            window.alert(data.message || 'Unable to save form elements.');
            return false;
        }
        return true;
    };

    const saveApprovals = async () => {
        const response = await fetch(saveApprovalsUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ steps: collectSteps() }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            window.alert(data.message || 'Unable to save approval steps.');
            return false;
        }
        return true;
    };

    root.querySelector('[data-save-active]')?.addEventListener('click', async () => {
        const ok = activeTab === 'approvals' ? await saveApprovals() : await saveElements();
        if (ok) {
            window.alert('Saved.');
        }
    });

    root.querySelector('[data-save-approvals]')?.addEventListener('click', async () => {
        if (await saveApprovals()) {
            window.alert('Approval steps saved.');
        }
    });

    renderAll();
    renderSteps();
    setActiveTab('build');
};

const ensurePreviewElementPosition = (elements, element, index) => {
    ensureSettingsObject(element);
    if (hasSavedPosition(element)) {
        return;
    }

    let y = CANVAS_INSET;
    for (let i = 0; i < index; i += 1) {
        const prev = elements[i];
        if (hasSavedPosition(prev)) {
            y = Math.max(y, Number(prev.settings.pos_y) + estimateElementHeight(prev) + FIELD_GAP);
        } else {
            y += estimateElementHeight(prev) + FIELD_GAP;
        }
    }

    element.settings.pos_x = isFullWidth(element) ? 0 : CANVAS_INSET;
    element.settings.pos_y = y;
};

const renderReadOnlyPreviewCanvas = async (canvas, elements, assetUrl, previewValues = {}) => {
    if (!canvas) {
        return;
    }

    if (elements.length === 0) {
        canvas.innerHTML = '<p class="rounded-lg border border-dashed border-gray-200 py-10 text-center text-sm text-gray-500">No fields in this template yet. Open Design to add elements.</p>';
        return;
    }

    elements.forEach((element, index) => ensurePreviewElementPosition(elements, element, index));

    const innerWidth = LEGAL_CONTENT_WIDTH_PX;
    let maxBottom = 360;
    elements.forEach((element) => {
        const y = Number(element.settings?.pos_y ?? 0);
        maxBottom = Math.max(maxBottom, y + estimateElementHeight(element) + 48);
    });
    const pageCount = Math.max(1, Math.ceil(Math.max(LEGAL_CONTENT_HEIGHT_PX, maxBottom) / LEGAL_CONTENT_HEIGHT_PX));
    const innerHeight = pageCount * LEGAL_CONTENT_HEIGHT_PX;

    canvas.innerHTML = `<div class="cd-legal-paper"><div class="cd-designer-canvas-inner relative" style="min-height:${innerHeight}px;">${elements.map((element, index) => {
        const box = fieldBoxSize(innerWidth, element);
        const widthCss = elementBoxWidthCss(innerWidth, element);
        const x = fieldPositionX(element);
        const y = Number(element.settings?.pos_y ?? CANVAS_INSET);
        const isImage = element.type === 'image';
        const lockedHeight = isImage || hasCustomBoxHeight(element);
        const heightStyle = lockedHeight ? `height:${box.height}px;` : '';
        const fieldPaddingClass = isImage ? 'p-0' : 'p-2';
        const fieldOverflowClass = isImage ? 'overflow-visible' : (lockedHeight ? 'overflow-auto' : '');
        const boxLeft = isFullWidth(element) && ! isImage ? '0' : `${x}px`;

        return `
            <div
                class="absolute box-border min-w-0"
                style="left:${boxLeft};top:${y}px;width:${widthCss};${heightStyle}max-width:100%;z-index:10"
            >
                <div class="relative w-full max-w-full min-w-0${lockedHeight ? ' h-full' : ''}">
                    <div class="cd-designer-field-body ${fieldPaddingClass} w-full max-w-full ${fieldOverflowClass}${lockedHeight ? ' h-full min-h-0' : ''}">${renderFieldPreview(element, index, previewValues, assetUrl, { readonlyPreview: true })}</div>
                </div>
            </div>
        `;
    }).join('')}</div></div>`;

    canvas.querySelectorAll('input, textarea, select, button').forEach((control) => {
        const type = (control.getAttribute('type') || '').toLowerCase();
        const tag = control.tagName.toLowerCase();
        const isTextLike = tag === 'textarea'
            || (tag === 'input' && ['text', 'email', 'tel', 'number', 'date', 'url', 'search'].includes(type));

        if (isTextLike) {
            control.readOnly = true;
            control.setAttribute('aria-readonly', 'true');
            return;
        }

        control.disabled = true;
        control.setAttribute('aria-disabled', 'true');
    });
};

export const initCompanyDocumentPreview = (root) => {
    if (!root || root.dataset.previewInit === 'true') {
        return;
    }

    root.dataset.previewInit = 'true';

    mergeTagSamples = parseJson(root.dataset.mergeTagSamples, {});
    mergeTagLabels = parseJson(root.dataset.mergeTagLabels, {});

    const canvas = root.querySelector('[data-preview-canvas]');
    const assetUrl = root.dataset.assetUrl || '';
    const previewValues = {};
    const elements = parseJson(root.dataset.initialElements, []).map((element) => ({
        ...element,
        settings: normalizeSettings(element.settings),
        options: asObject(element.options),
    }));

    const serverPreviewValues = parseJson(root.dataset.initialPreviewValues, {});
    Object.entries(serverPreviewValues).forEach(([key, value]) => {
        const elementIndex = elements.findIndex((item, index) => previewKeyFor(item, index) === key);
        const element = elementIndex >= 0 ? elements[elementIndex] : null;

        if (element?.type === 'signature') {
            return;
        }

        previewValues[key] = value;
    });

    elements.forEach((element, index) => {
        if (element.type !== 'signature' || !element.settings?.signature_preview) {
            return;
        }

        previewValues[previewKeyFor(element, index)] = element.settings.signature_preview;
    });

    bindSignaturePreviewPersistence(canvas, previewValues);

    const render = () => renderReadOnlyPreviewCanvas(canvas, elements, assetUrl, previewValues);

    render();

    let resizeTimer = null;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(render, 150);
    });

    const modal = root.closest('.modal-overlay');
    const observer = modal ? new MutationObserver(() => {
        if (!modal.classList.contains('hidden')) {
            window.requestAnimationFrame(render);
        }
    }) : null;

    if (observer && modal) {
        observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
    }
};
