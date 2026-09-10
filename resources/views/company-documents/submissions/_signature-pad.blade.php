<div
    class="signature-pad space-y-2"
    data-signature-pad
    data-required="{{ ($required ?? false) ? 'true' : 'false' }}"
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
                width="420"
                height="120"
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
        name="{{ $inputName ?? 'signature' }}"
        class="hidden"
        data-signature-file-input
        tabindex="-1"
        aria-hidden="true"
    >
    <p class="hidden text-xs text-red-600" data-signature-error></p>
</div>
