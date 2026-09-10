const CANVAS_WIDTH = 420;
const CANVAS_HEIGHT = 120;

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

export function renderSignaturePadHtml({ inputName = '', required = false, previewKey = '' } = {}) {
    const nameAttr = inputName ? ` name="${escapeHtml(inputName)}"` : '';
    const previewKeyAttr = previewKey ? ` data-preview-key="${escapeHtml(previewKey)}"` : '';

    return `
        <div
            class="signature-pad space-y-2"
            data-signature-pad
            data-required="${required ? 'true' : 'false'}"${previewKeyAttr}
        >
            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-xs font-medium">
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-gray-600 data-[active=true]:bg-gray-900 data-[active=true]:text-white"
                    data-signature-tab="draw"
                    data-active="true"
                >
                    Draw
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-gray-600 data-[active=true]:bg-gray-900 data-[active=true]:text-white"
                    data-signature-tab="upload"
                >
                    Upload
                </button>
            </div>

            <div data-signature-draw-panel>
                <div class="overflow-hidden rounded-lg border border-dashed border-gray-300 bg-white">
                    <canvas
                        data-signature-canvas
                        width="${CANVAS_WIDTH}"
                        height="${CANVAS_HEIGHT}"
                        class="block h-[120px] w-full touch-none cursor-crosshair bg-white"
                        aria-label="Draw your signature"
                    ></canvas>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn-secondary text-xs" data-signature-clear>Clear</button>
                </div>
            </div>

            <div class="hidden space-y-2" data-signature-upload-panel>
                <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    class="form-input w-full"
                    data-signature-upload-input
                >
                <div class="hidden rounded-lg border border-gray-200 bg-gray-50 p-2" data-signature-upload-preview>
                    <img alt="Signature preview" class="max-h-24" data-signature-preview-img>
                </div>
            </div>

            <input
                type="file"
                class="hidden"
                data-signature-file-input${nameAttr}
                tabindex="-1"
                aria-hidden="true"
            >
            <p class="hidden text-xs text-red-600" data-signature-error></p>
        </div>
    `;
}

export function renderSignatureReadonlyHtml(state = null) {
    const dataUrl = typeof state?.dataUrl === 'string' ? state.dataUrl.trim() : '';

    if (dataUrl !== '') {
        return `
            <div class="overflow-hidden rounded-lg border border-dashed border-gray-300 bg-white p-2">
                <img src="${escapeHtml(dataUrl)}" alt="Signature" class="block max-h-[120px] w-full object-contain">
            </div>
        `;
    }

    return `
        <div class="flex h-[120px] items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 text-xs text-gray-400">
            No signature
        </div>
    `;
}

const formRegistry = new WeakMap();

function emptyCanvas(ctx, canvas) {
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
}

function canvasHasInk(canvas) {
    if (!canvas) {
        return false;
    }

    const ctx = canvas.getContext('2d');
    const { data } = ctx.getImageData(0, 0, canvas.width, canvas.height);

    for (let i = 0; i < data.length; i += 4) {
        const r = data[i];
        const g = data[i + 1];
        const b = data[i + 2];
        const a = data[i + 3];

        if (a > 0 && (r < 250 || g < 250 || b < 250)) {
            return true;
        }
    }

    return false;
}

function setFileOnInput(input, file) {
    if (!input || !file) {
        return;
    }

    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
}

function readFileAsDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ''));
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(file);
    });
}

async function dataUrlToFile(dataUrl, fileName = 'signature.png') {
    const response = await fetch(dataUrl);
    const blob = await response.blob();

    return new File([blob], fileName, { type: blob.type || 'image/png' });
}

function emitSignaturePadChange(root, state) {
    root.dispatchEvent(new CustomEvent('signature-pad:change', {
        bubbles: true,
        detail: { state },
    }));
}

function registerFormHandler(form) {
    if (!form || form.dataset.signatureFormInit === 'true') {
        return;
    }

    form.dataset.signatureFormInit = 'true';

    form.addEventListener('submit', (event) => {
        if (form.dataset.signatureSubmitting === 'true') {
            return;
        }

        const pads = formRegistry.get(form) || [];
        if (pads.length === 0) {
            return;
        }

        event.preventDefault();

        Promise.all(pads.map((pad) => pad.syncSignature())).then((results) => {
            const failed = pads.find((pad, index) => pad.required && !results[index]);
            if (failed) {
                failed.showError('Please draw or upload your signature.');
                return;
            }

            pads.forEach((pad) => pad.showError(''));
            form.dataset.signatureSubmitting = 'true';
            form.requestSubmit();
        });
    });
}

function initSignaturePad(root) {
    if (!root || root.dataset.signatureInit === 'true') {
        return;
    }

    root.dataset.signatureInit = 'true';

    const canvas = root.querySelector('[data-signature-canvas]');
    const fileInput = root.querySelector('[data-signature-file-input]');
    const uploadInput = root.querySelector('[data-signature-upload-input]');
    const drawPanel = root.querySelector('[data-signature-draw-panel]');
    const uploadPanel = root.querySelector('[data-signature-upload-panel]');
    const previewWrap = root.querySelector('[data-signature-upload-preview]');
    const previewImg = root.querySelector('[data-signature-preview-img]');
    const errorEl = root.querySelector('[data-signature-error]');
    const tabButtons = root.querySelectorAll('[data-signature-tab]');
    const required = root.dataset.required === 'true';
    const form = root.closest('form');

    let mode = 'draw';
    let drawing = false;
    let lastPoint = null;

    const captureState = async () => {
        if (mode === 'upload') {
            const file = uploadInput?.files?.[0] || fileInput?.files?.[0];
            if (!file) {
                return null;
            }

            return {
                mode: 'upload',
                dataUrl: await readFileAsDataUrl(file),
                fileName: file.name || 'signature.png',
            };
        }

        if (!canvasHasInk(canvas)) {
            return null;
        }

        return {
            mode: 'draw',
            dataUrl: canvas.toDataURL('image/png'),
            fileName: 'signature.png',
        };
    };

    const restoreState = async (state) => {
        if (!state?.dataUrl) {
            return;
        }

        setMode(state.mode === 'upload' ? 'upload' : 'draw');

        if (state.mode === 'upload') {
            const file = await dataUrlToFile(state.dataUrl, state.fileName || 'signature.png');
            setFileOnInput(fileInput, file);
            if (previewImg) {
                previewImg.src = state.dataUrl;
            }
            previewWrap?.classList.remove('hidden');
            return;
        }

        if (!canvas) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const img = new Image();
        await new Promise((resolve, reject) => {
            img.onload = () => resolve();
            img.onerror = reject;
            img.src = state.dataUrl;
        });
        emptyCanvas(ctx, canvas);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        const file = await dataUrlToFile(state.dataUrl, state.fileName || 'signature.png');
        setFileOnInput(fileInput, file);
    };

    const notifyChange = async () => {
        emitSignaturePadChange(root, await captureState());
    };

    const showError = (message) => {
        if (!errorEl) {
            return;
        }

        if (message) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        } else {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }
    };

    const setMode = (nextMode) => {
        mode = nextMode;
        tabButtons.forEach((button) => {
            button.dataset.active = button.dataset.signatureTab === mode ? 'true' : 'false';
        });
        drawPanel?.classList.toggle('hidden', mode !== 'draw');
        uploadPanel?.classList.toggle('hidden', mode !== 'upload');
        showError('');
    };

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setMode(button.dataset.signatureTab === 'upload' ? 'upload' : 'draw');
        });
    });

    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = 2.2;
        ctx.strokeStyle = '#111827';
        emptyCanvas(ctx, canvas);

        const getPoint = (event) => {
            const rect = canvas.getBoundingClientRect();
            const clientX = event.touches?.[0]?.clientX ?? event.clientX;
            const clientY = event.touches?.[0]?.clientY ?? event.clientY;
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;

            return {
                x: (clientX - rect.left) * scaleX,
                y: (clientY - rect.top) * scaleY,
            };
        };

        const startDraw = (event) => {
            event.preventDefault();
            drawing = true;
            lastPoint = getPoint(event);
        };

        const draw = (event) => {
            if (!drawing) {
                return;
            }

            event.preventDefault();
            const point = getPoint(event);
            if (!lastPoint) {
                return;
            }

            ctx.beginPath();
            ctx.moveTo(lastPoint.x, lastPoint.y);
            ctx.lineTo(point.x, point.y);
            ctx.stroke();
            lastPoint = point;
            showError('');
        };

        const endDraw = () => {
            if (!drawing) {
                return;
            }

            drawing = false;
            lastPoint = null;
            void notifyChange();
        };

        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', endDraw);
        canvas.addEventListener('mouseleave', endDraw);
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', endDraw);

        root.querySelector('[data-signature-clear]')?.addEventListener('click', () => {
            emptyCanvas(ctx, canvas);
            if (fileInput) {
                fileInput.value = '';
            }
            if (uploadInput) {
                uploadInput.value = '';
            }
            previewWrap?.classList.add('hidden');
            showError('');
            void notifyChange();
        });
    }

    uploadInput?.addEventListener('change', () => {
        const file = uploadInput.files?.[0];
        if (!file) {
            previewWrap?.classList.add('hidden');
            if (fileInput) {
                fileInput.value = '';
            }
            void notifyChange();
            return;
        }

        setFileOnInput(fileInput, file);
        if (previewImg) {
            previewImg.src = URL.createObjectURL(file);
        }
        previewWrap?.classList.remove('hidden');
        showError('');
        void notifyChange();
    });

    const syncSignature = () => new Promise((resolve) => {
        if (mode === 'upload') {
            const file = uploadInput?.files?.[0];
            if (file) {
                setFileOnInput(fileInput, file);
            }
            resolve(Boolean(fileInput?.files?.length));
            return;
        }

        if (fileInput?.files?.length) {
            resolve(true);
            return;
        }

        if (!canvasHasInk(canvas)) {
            resolve(false);
            return;
        }

        canvas.toBlob((blob) => {
            if (!blob) {
                resolve(false);
                return;
            }

            setFileOnInput(fileInput, new File([blob], 'signature.png', { type: 'image/png' }));
            resolve(true);
        }, 'image/png');
    });

    root.signaturePadController = { captureState, restoreState, syncSignature };

    const padApi = { syncSignature, showError, required };

    if (form) {
        const pads = formRegistry.get(form) || [];
        pads.push(padApi);
        formRegistry.set(form, pads);
        registerFormHandler(form);
    }
}

export function initSignaturePads(root = document) {
    root.querySelectorAll('[data-signature-pad]').forEach(initSignaturePad);
}

export async function captureSignaturePadState(root) {
    if (!root?.signaturePadController) {
        return null;
    }

    return root.signaturePadController.captureState();
}

export async function restoreSignaturePadState(root, state) {
    if (!root?.signaturePadController) {
        return;
    }

    await root.signaturePadController.restoreState(state);
}
