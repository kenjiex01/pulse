<?php

namespace App\Services;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Support\CompanyDocumentInlineFormatting;
use App\Support\CompanyDocumentMemoDocxExporter;
use App\Support\CompanyDocumentMemoDompdfPreparer;
use App\Support\CompanyDocumentMemoPdfLayout;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class CompanyDocumentMemoRenderService
{
    public function __construct(
        private readonly CompanyDocumentMemoValueResolver $valueResolver,
        private readonly CompanyDocumentMergeTagService $mergeTagService,
    ) {}

    /**
     * @param  array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}  $memoContext
     * @return array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }
     */
    /**
     * @return array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }
     */
    public function buildSamplePreviewData(CompanyDocumentForm $form): array
    {
        $form->loadMissing(['elements', 'icctOffense']);

        $memoContext = $this->sampleMemoContext();
        $elements = [];
        $previewValues = $form->icctOffenseFieldDefaults();

        foreach ($form->elements->sortBy('sort_order') as $index => $element) {
            $elements[] = $this->mapElement($element, null, $memoContext, $index, $previewValues);
        }

        return [
            'form' => $form,
            'elements' => $elements,
            'preview_values' => $previewValues,
        ];
    }

    public function renderSamplePdf(CompanyDocumentForm $form): string
    {
        return $this->renderPdf($this->buildSamplePreviewData($form));
    }

    public function buildPreviewData(
        CompanyDocumentForm $form,
        Employee $employee,
        array $memoContext,
    ): array {
        $form->loadMissing('elements');

        $elements = [];
        $previewValues = [];

        foreach ($form->elements->sortBy('sort_order') as $index => $element) {
            $elements[] = $this->mapElement($element, $employee, $memoContext, $index, $previewValues);
        }

        return [
            'form' => $form,
            'elements' => $elements,
            'preview_values' => $previewValues,
        ];
    }

    /**
     * @param  array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }  $preview
     */
    public function renderHtml(array $preview): string
    {
        return View::make('emails.partials.company-document-memo', [
            'form' => $preview['form'],
            'elements' => collect($preview['elements'])->sortBy('sort_order')->values()->all(),
            'previewValues' => $preview['preview_values'],
        ])->render();
    }

    /**
     * @param  array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }  $preview
     */
    public function renderCanvas(array $preview): string
    {
        $elements = collect($preview['elements'])->sortBy('sort_order')->values()->all();
        $layout = CompanyDocumentMemoPdfLayout::build($elements);

        return View::make('shared.company-document-memo-canvas', [
            'layout' => $layout,
            'previewValues' => $preview['preview_values'],
            'formSettings' => $preview['form']->settings_json,
        ])->render();
    }

    /**
     * Browser preview and PDF share this HTML (640px form column on legal paper).
     *
     * @param  array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }  $preview
     */
    public function renderDocumentHtml(array $preview, bool $browserPreview = false): string
    {
        $elements = collect($preview['elements'])->sortBy('sort_order')->values()->all();
        $layout = CompanyDocumentMemoPdfLayout::buildForPdf($elements);

        return View::make('pdf.company-document-memo-document', [
            'form' => $preview['form'],
            'layout' => $layout,
            'previewValues' => $preview['preview_values'],
            'browserPreview' => $browserPreview,
        ])->render();
    }

    /**
     * @param  array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }  $preview
     */
    public function renderPdf(array $preview): string
    {
        $preparer = new CompanyDocumentMemoDompdfPreparer();
        $materialized = $preparer->materializePreviewImages($preview);

        try {
            $elements = collect($materialized['preview']['elements'])->sortBy('sort_order')->values()->all();
            $layout = CompanyDocumentMemoPdfLayout::buildForPdf($elements);

            $html = View::make('pdf.company-document-memo-document', [
                'form' => $materialized['preview']['form'],
                'layout' => $layout,
                'previewValues' => $materialized['preview']['preview_values'],
                'browserPreview' => false,
            ])->render();

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('dpi', 96);
            $options->set('isFontSubsettingEnabled', true);
            $options->setChroot([$materialized['chroot']]);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->setBasePath($materialized['chroot'].\DIRECTORY_SEPARATOR);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('legal', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        } finally {
            $preparer->cleanup($materialized['cleanup']);
        }
    }

    /**
     * @param  array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }  $preview
     */
    public function renderDocx(array $preview): string
    {
        $preparer = new CompanyDocumentMemoDompdfPreparer();
        $materialized = $preparer->materializePreviewImages($preview);

        try {
            return (new CompanyDocumentMemoDocxExporter())->export(
                $materialized['preview'],
                $materialized['chroot'],
            );
        } finally {
            $preparer->cleanup($materialized['cleanup']);
        }
    }

    /**
     * @param  array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}  $memoContext
     * @param  array<string, mixed>  $previewValues
     * @return array<string, mixed>
     */
    /**
     * @return array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}
     */
    private function sampleMemoContext(): array
    {
        $today = now();

        return [
            'date_from' => $today->copy()->startOfMonth()->toDateString(),
            'date_to' => $today->copy()->endOfMonth()->toDateString(),
            'violation_type' => 'absent',
            'violation_count' => 3,
            'selected_dates' => [
                $today->copy()->subDays(10)->toDateString(),
                $today->copy()->subDays(7)->toDateString(),
                $today->copy()->subDays(3)->toDateString(),
            ],
        ];
    }

    private function mapElement(
        CompanyDocumentElement $element,
        ?Employee $employee,
        array $memoContext,
        int $index,
        array &$previewValues,
    ): array {
        $settings = $element->settings_json ?? ['label_align' => 'top'];
        $type = $element->type;
        $label = (string) ($element->label ?? '');

        if ($element->isMergeTag()) {
            $tagKey = (string) (($settings['tag_key'] ?? '') ?: '');
            $label = $tagKey !== ''
                ? $this->mergeTagService->resolve($tagKey, $employee, $memoContext)
                : '';
            $type = CompanyDocumentElement::TYPE_PARAGRAPH;
        } elseif (in_array($type, [CompanyDocumentElement::TYPE_PARAGRAPH, CompanyDocumentElement::TYPE_HEADING], true)) {
            $label = $this->mergeTagService->resolveInlineTags($label, $employee, $memoContext);
            $label = CompanyDocumentInlineFormatting::sanitize($label);
        }

        if ($element->isInput() && $type !== CompanyDocumentElement::TYPE_SIGNATURE) {
            $key = (string) ($element->field_key ?: 'preview_'.$index);
            $value = $this->valueResolver->resolveInputValue($element, $employee, $memoContext);
            if ($value !== null && $value !== '') {
                $previewValues[$key] = $value;
            }
        }

        if ($type === CompanyDocumentElement::TYPE_SIGNATURE) {
            $key = (string) ($element->field_key ?: 'preview_'.$index);
            $dataUrl = $this->valueResolver->signatureDataUrl($element);
            if ($dataUrl !== '') {
                $previewValues[$key] = [
                    'mode' => (string) (($settings['signature_preview']['mode'] ?? '') ?: 'draw'),
                    'dataUrl' => $dataUrl,
                    'fileName' => (string) (($settings['signature_preview']['fileName'] ?? '') ?: 'signature.png'),
                ];
            }
        }

        if ($type === 'image') {
            $path = trim((string) (($element->options_json ?? [])['path'] ?? ''));
            if ($path !== '' && Storage::disk('local')->exists($path)) {
                $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';
                $binary = Storage::disk('local')->get($path);
                $settings['image_data_url'] = 'data:'.$mime.';base64,'.base64_encode($binary);
            }
        }

        return [
            'type' => $type,
            'label' => $label,
            'field_key' => $element->field_key,
            'placeholder' => $element->placeholder,
            'help_text' => $element->help_text,
            'is_required' => (bool) $element->is_required,
            'width' => $element->width,
            'sort_order' => $element->sort_order,
            'options' => $element->options_json ?? [],
            'settings' => $settings,
        ];
    }
}
