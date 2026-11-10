<?php

namespace App\Services;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Support\CompanyDocumentInlineFormatting;
use App\Support\CompanyDocumentMemoTable;
use App\Support\CompanyDocumentOffensePairTable;
use App\Support\CompanyDocumentMemoDocxExporter;
use App\Support\CompanyDocumentMemoDompdfPreparer;
use App\Support\CompanyDocumentMemoFonts;
use App\Support\CompanyDocumentMemoPdfLayout;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class CompanyDocumentMemoRenderService
{
    public function __construct(
        private readonly CompanyDocumentMemoValueResolver $valueResolver,
        private readonly CompanyDocumentMergeTagService $mergeTagService,
        private readonly CompanyDocumentOffenseMemoService $offenseMemoService,
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

        $memoContext = $this->offenseMemoService->enrichMemoContext(
            $form,
            null,
            $this->sampleMemoContext(),
        );
        $elements = [];
        $previewValues = $form->icctOffenseFieldDefaults();

        foreach ($form->elements->sortBy('sort_order') as $index => $element) {
            $elements[] = $this->mapElement($element, null, $memoContext, $index, $previewValues);
        }

        return [
            'form' => $form,
            'elements' => $elements,
            'preview_values' => $previewValues,
            'memo_fields' => $this->fixedLayoutFields($form, null, $memoContext),
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

        $memoContext = $this->offenseMemoService->enrichMemoContext($form, $employee, $memoContext);

        $elements = [];
        $previewValues = [];

        foreach ($form->elements->sortBy('sort_order') as $index => $element) {
            $elements[] = $this->mapElement($element, $employee, $memoContext, $index, $previewValues);
        }

        return [
            'form' => $form,
            'elements' => $elements,
            'preview_values' => $previewValues,
            'memo_fields' => $this->fixedLayoutFields($form, $employee, $memoContext),
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
        $fixed = $this->fixedLayout($preview);
        if ($fixed !== null) {
            return View::make($fixed['view'], [
                'fields' => $fixed['fields'],
                'browserPreview' => $browserPreview,
            ])->render();
        }

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
            $fixed = $this->fixedLayout($materialized['preview']);
            if ($fixed !== null) {
                $html = View::make($fixed['view'], [
                    'fields' => $fixed['fields'],
                    'browserPreview' => false,
                    'fontFiles' => CompanyDocumentMemoFonts::copyInto($materialized['chroot']),
                ])->render();
                $paper = $fixed['paper'];
            } else {
                $elements = collect($materialized['preview']['elements'])->sortBy('sort_order')->values()->all();
                $layout = CompanyDocumentMemoPdfLayout::buildForPdf($elements);

                $html = View::make('pdf.company-document-memo-document', [
                    'form' => $materialized['preview']['form'],
                    'layout' => $layout,
                    'previewValues' => $materialized['preview']['preview_values'],
                    'browserPreview' => false,
                ])->render();
                $paper = 'legal';
            }

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
            $dompdf->setPaper($paper, 'portrait');
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
    /**
     * @param  array<string, mixed>  $preview
     */
    /**
     * @param  array<string, mixed>  $preview
     * @return array{view: string, paper: string|array<int, float>, fields: array<string, mixed>}|null
     */
    private function fixedLayout(array $preview): ?array
    {
        $fields = $preview['memo_fields'] ?? null;
        if (! is_array($fields) || ! isset($fields['layout_view'])) {
            return null;
        }

        return [
            'view' => (string) $fields['layout_view'],
            'paper' => $fields['layout_paper'] ?? 'letter',
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $memoContext
     * @return array<string, mixed>|null
     */
    private function fixedLayoutFields(CompanyDocumentForm $form, ?Employee $employee, array $memoContext): ?array
    {
        $letters = [
            'hr_notice_to_explain' => ['Via LBC', true, [0.0, 0.0, 612.0, 936.0], 'Vice- President, Administration', 'full'],
            'hr_letter_nte_followup_2' => ['Via Registered Mail', false, 'letter', 'Vice- President, Administration', 'full'],
            'hr_letter_nte_followup_3' => ['Via Registered Mail', false, 'letter', 'Vice- President, Administration', 'full'],
            'hr_letter_admin_investigation' => ['Via Registered Mail', false, 'letter', 'Vice- President, Administration', 'full'],
            'hr_letter_dismissal_notice' => ['Via Registered Mail', false, 'letter', 'Vice-President, Administration', 'short'],
        ];

        if (isset($letters[$form->code])) {
            [$delivery, $deliveryFirst, $paper, $signTitle, $ccStyle] = $letters[$form->code];
            $address = trim((string) ($employee?->address_line ?? ''));

            return [
                'layout_view' => 'pdf.company-document-hr-letter',
                'layout_paper' => $paper,
                'variant' => $form->code,
                'tall' => is_array($paper),
                'delivery' => $delivery,
                'delivery_first' => $deliveryFirst,
                'logo' => $this->memoLogoDataUri(),
                'control_no' => $this->mergeTagService->resolve('control_no', $employee, $memoContext),
                'memo_date' => $this->mergeTagService->resolve('memo_date', $employee, $memoContext),
                'to_name' => $this->mergeTagService->resolve('employee_full_name', $employee, $memoContext),
                'address' => $address !== '' ? $address : 'Residential Address',
                'sign_name' => 'Au G. Esma',
                'sign_title' => $signTitle,
                'cc_style' => $ccStyle,
            ];
        }

        $memos = [
            'hr_memo_absences_tardiness' => ['pdf.company-document-absences-tardiness-memo', 'letter', null],
            'hr_memo_company_property' => ['pdf.company-document-company-property-memo', 'letter', 'Release of Company Property'],
            'hr_internal_memo' => ['pdf.company-document-attendance-memo', [0.0, 0.0, 612.0, 936.0], 'Attendances'],
            'hr_memo_philhealth_compliance' => ['pdf.company-document-philhealth-memo', 'letter', 'Philhealth Number'],
        ];

        if (! isset($memos[$form->code])) {
            return null;
        }

        [$view, $paper, $subject] = $memos[$form->code];
        $from = $this->splitNameAndTitle($this->mergeTagService->resolve('memo_from', $employee, $memoContext));
        $ccLines = [];
        foreach (preg_split("/\r\n|\n|\r/", $this->mergeTagService->resolve('memo_cc', $employee, $memoContext)) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $ccLines[] = $this->splitNameAndTitle($line);
        }

        return [
            'layout_view' => $view,
            'layout_paper' => $paper,
            'logo' => $this->memoLogoDataUri(),
            'control_no' => $this->mergeTagService->resolve('control_no', $employee, $memoContext),
            'from_name' => $from[0],
            'from_title' => $from[1],
            'to_name' => $this->mergeTagService->resolve('employee_full_name', $employee, $memoContext),
            'to_title' => $this->mergeTagService->resolve('employee_designation', $employee, $memoContext),
            'memo_date' => $this->mergeTagService->resolve('memo_date', $employee, $memoContext),
            'period' => $this->mergeTagService->resolve('attendance_period', $employee, $memoContext),
            'hire_date' => $this->mergeTagService->resolve('hire_date', $employee, $memoContext),
            'subject' => $subject,
            'cc_lines' => $ccLines,
        ];
    }

    private function memoLogoDataUri(): string
    {
        $logoPath = resource_path('seeders/assets/icct-colleges-logo.jpg');
        if (! is_file($logoPath)) {
            return '';
        }

        return 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoPath));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitNameAndTitle(string $value): array
    {
        $value = trim($value);
        foreach ([' — ', ' – ', ' - '] as $separator) {
            if (str_contains($value, $separator)) {
                [$name, $title] = explode($separator, $value, 2);

                return [trim($name), trim($title)];
            }
        }

        return [$value, ''];
    }

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
        $options = $element->options_json ?? [];

        if ($element->isMergeTag()) {
            $tagKey = (string) (($settings['tag_key'] ?? '') ?: '');
            $label = $tagKey !== ''
                ? $this->mergeTagService->resolve($tagKey, $employee, $memoContext)
                : '';
            $type = CompanyDocumentElement::TYPE_PARAGRAPH;
        } elseif ($type === CompanyDocumentElement::TYPE_PARAGRAPH) {
            $label = CompanyDocumentOffensePairTable::render(
                $label,
                fn (string $text): string => CompanyDocumentInlineFormatting::renderSegment(
                    $this->mergeTagService->resolveInlineTags($text, $employee, $memoContext),
                ),
                fn (string $tagKey): string => e($this->mergeTagService->resolve($tagKey, $employee, $memoContext)),
            );
        } elseif ($type === CompanyDocumentElement::TYPE_TABLE) {
            $resolvedRows = [];
            foreach (CompanyDocumentMemoTable::normalizeRows($options['rows'] ?? null) as $row) {
                $resolvedRows[] = array_map(
                    fn (string $cell): string => CompanyDocumentInlineFormatting::renderSegment(
                        $this->mergeTagService->resolveInlineTags($cell, $employee, $memoContext),
                    ),
                    $row,
                );
            }
            $options = ['rows' => $resolvedRows];
        } elseif ($type === CompanyDocumentElement::TYPE_HEADING) {
            $label = $this->mergeTagService->resolveInlineTags($label, $employee, $memoContext);
            $label = CompanyDocumentInlineFormatting::renderSegment($label);
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
            'options' => $options,
            'settings' => $settings,
        ];
    }
}
