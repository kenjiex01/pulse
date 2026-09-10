<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocumentApproval;
use App\Models\CompanyDocumentApprovalAssignee;
use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyDocumentFileService;
use App\Services\CompanyDocumentMergeTagService;
use App\Services\SysLogService;
use App\Support\CompanyDocumentElementCatalog;
use App\Support\CompanyDocumentInlineFormatting;
use App\Support\CompanyDocumentMergeTagCatalog;
use App\Support\CompanyDocumentTextStyle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanyDocumentDesignerController extends Controller
{
    public function __construct(
        private readonly CompanyDocumentFileService $fileService,
        private readonly CompanyDocumentMergeTagService $mergeTagService,
    ) {}

    public function show(CompanyDocumentForm $companyDocumentForm): View
    {
        $this->authorize('design', $companyDocumentForm);

        $companyDocumentForm->load([
            'elements',
            'approvals.assignees.user:id,name,email',
            'approvals.assignees.role:id,name',
        ]);

        SysLogService::record(
            action: 'read',
            table: 'tbl_company_document_forms',
            recordId: $companyDocumentForm->company_document_form_id,
            description: 'Opened designer for company document template: '.$companyDocumentForm->code,
        );

        return view('company-documents.designer', [
            'form' => $companyDocumentForm,
            'palette' => CompanyDocumentElementCatalog::palette(),
            'mergeTagSamples' => $this->mergeTagService->previewSamples(),
            'mergeTagLabels' => $this->mergeTagService->tagLabels(),
            'approvalModes' => CompanyDocumentApproval::modes(),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->limit(200)->get(['id', 'name', 'email']),
            'initialElements' => $companyDocumentForm->elements->map(fn ($element) => [
                'type' => $element->type,
                'label' => $element->label,
                'field_key' => $element->field_key,
                'placeholder' => $element->placeholder,
                'help_text' => $element->help_text,
                'is_required' => (bool) $element->is_required,
                'width' => $element->width,
                'sort_order' => $element->sort_order,
                'options' => $this->normalizeElementOptions($element->options_json, (string) $element->type) ?? [],
                'validation' => $element->validation_json ?? [],
                'conditional' => $element->conditional_json ?? [],
                'settings' => $this->normalizeElementSettings($element->settings_json),
            ])->values(),
            'initialSteps' => $companyDocumentForm->approvals->map(fn ($approval) => [
                'name' => $approval->name,
                'mode' => $approval->mode,
                'optional' => (bool) $approval->optional,
                'sla_hours' => $approval->sla_hours,
                'instructions' => $approval->instructions,
                'assignees' => $approval->assignees->map(fn ($assignee) => [
                    'assignee_type' => $assignee->assignee_type,
                    'user_id' => $assignee->user_id,
                    'role_id' => $assignee->role_id,
                ])->values(),
            ])->values(),
            'palette' => CompanyDocumentElementCatalog::palette(),
        ]);
    }

    public function saveElements(Request $request, CompanyDocumentForm $companyDocumentForm): JsonResponse
    {
        $this->authorize('design', $companyDocumentForm);

        $validated = $request->validate([
            'elements' => ['required', 'array'],
            'elements.*.type' => ['required', 'string', 'max:40'],
            'elements.*.label' => ['nullable', 'string', 'max:5000'],
            'elements.*.field_key' => ['nullable', 'string', 'max:120'],
            'elements.*.placeholder' => ['nullable', 'string', 'max:255'],
            'elements.*.help_text' => ['nullable', 'string'],
            'elements.*.is_required' => ['nullable', 'boolean'],
            'elements.*.width' => ['nullable', 'string', 'in:full,half,third'],
            'elements.*.sort_order' => ['nullable', 'integer'],
            'elements.*.options' => ['nullable', 'array'],
            'elements.*.validation' => ['nullable', 'array'],
            'elements.*.conditional' => ['nullable', 'array'],
            'elements.*.settings' => ['nullable', 'array'],
        ]);

        $existingKeys = [];

        DB::transaction(function () use ($companyDocumentForm, $validated, &$existingKeys): void {
            CompanyDocumentElement::query()
                ->where('company_document_form_id', $companyDocumentForm->company_document_form_id)
                ->each(fn (CompanyDocumentElement $element) => $element->forceDelete());

            foreach ($validated['elements'] as $index => $elementData) {
                $type = (string) $elementData['type'];
                $key = $elementData['field_key'] ?? null;

                if (! $key && in_array($type, CompanyDocumentElement::inputTypes(), true)) {
                    $key = $this->uniqueFieldKey(
                        Str::slug((string) ($elementData['label'] ?? CompanyDocumentElementCatalog::defaultLabel($type)), '_'),
                        $existingKeys,
                    );
                }

                if ($key) {
                    $existingKeys[] = $key;
                }

                CompanyDocumentElement::query()->create([
                    'company_document_form_id' => $companyDocumentForm->company_document_form_id,
                    'type' => $type,
                    'label' => $this->normalizeElementLabel($elementData['label'] ?? null, $type),
                    'field_key' => $key,
                    'placeholder' => $elementData['placeholder'] ?? null,
                    'help_text' => $elementData['help_text'] ?? null,
                    'is_required' => (bool) ($elementData['is_required'] ?? false),
                    'options_json' => $this->normalizeElementOptions($elementData['options'] ?? null, $type),
                    'validation_json' => $elementData['validation'] ?? null,
                    'conditional_json' => $elementData['conditional'] ?? null,
                    'settings_json' => $this->normalizeElementSettings($elementData['settings'] ?? null, forStorage: true),
                    'width' => $elementData['width'] ?? 'full',
                    'sort_order' => (int) ($elementData['sort_order'] ?? $index),
                ]);
            }

            $companyDocumentForm->increment('version');
        });

        SysLogService::record(
            action: 'update',
            table: 'tbl_company_document_forms',
            recordId: $companyDocumentForm->company_document_form_id,
            description: 'Saved designer elements for template: '.$companyDocumentForm->code,
        );

        return response()->json([
            'ok' => true,
            'message' => 'Form saved.',
            'version' => $companyDocumentForm->fresh()->version,
        ]);
    }

    public function uploadImage(Request $request, CompanyDocumentForm $companyDocumentForm): JsonResponse
    {
        $this->authorize('design', $companyDocumentForm);

        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['image'];

        try {
            $path = $this->fileService->storeDesignerImage($file, (int) $companyDocumentForm->company_document_form_id);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        SysLogService::record(
            action: 'create',
            table: 'tbl_company_document_forms',
            recordId: $companyDocumentForm->company_document_form_id,
            description: 'Uploaded designer image for template: '.$companyDocumentForm->code,
        );

        return response()->json([
            'ok' => true,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
        ]);
    }

    public function showAsset(Request $request, CompanyDocumentForm $companyDocumentForm): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('design', $companyDocumentForm);

        $path = $request->string('path')->trim()->toString();
        abort_if($path === '', 404);

        try {
            $this->fileService->assertDesignerAssetPath((int) $companyDocumentForm->company_document_form_id, $path);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path));
    }

    public function saveApprovals(Request $request, CompanyDocumentForm $companyDocumentForm): JsonResponse
    {
        $this->authorize('design', $companyDocumentForm);

        if (! $companyDocumentForm->supportsApprovalRouting()) {
            return response()->json([
                'ok' => false,
                'message' => 'This document type does not use approval routing.',
            ], 422);
        }

        $validated = $request->validate([
            'steps' => ['required', 'array'],
            'steps.*.name' => ['required', 'string', 'max:150'],
            'steps.*.mode' => ['required', 'string', 'in:single,any_of,all_of'],
            'steps.*.optional' => ['nullable', 'boolean'],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:0'],
            'steps.*.instructions' => ['nullable', 'string'],
            'steps.*.assignees' => ['nullable', 'array'],
            'steps.*.assignees.*.assignee_type' => ['required', 'string', 'in:user,role'],
            'steps.*.assignees.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'steps.*.assignees.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        DB::transaction(function () use ($companyDocumentForm, $validated): void {
            $approvalIds = CompanyDocumentApproval::query()
                ->where('company_document_form_id', $companyDocumentForm->company_document_form_id)
                ->pluck('approval_id');

            CompanyDocumentApprovalAssignee::query()
                ->whereIn('approval_id', $approvalIds)
                ->each(fn (CompanyDocumentApprovalAssignee $assignee) => $assignee->forceDelete());

            CompanyDocumentApproval::query()
                ->where('company_document_form_id', $companyDocumentForm->company_document_form_id)
                ->each(fn (CompanyDocumentApproval $approval) => $approval->forceDelete());

            foreach ($validated['steps'] as $stepIndex => $stepData) {
                $approval = CompanyDocumentApproval::query()->create([
                    'company_document_form_id' => $companyDocumentForm->company_document_form_id,
                    'step_number' => $stepIndex + 1,
                    'name' => $stepData['name'],
                    'mode' => $stepData['mode'],
                    'optional' => (bool) ($stepData['optional'] ?? false),
                    'sla_hours' => $stepData['sla_hours'] ?? null,
                    'instructions' => $stepData['instructions'] ?? null,
                ]);

                foreach ($stepData['assignees'] ?? [] as $assigneeData) {
                    CompanyDocumentApprovalAssignee::query()->create([
                        'approval_id' => $approval->approval_id,
                        'assignee_type' => $assigneeData['assignee_type'],
                        'user_id' => $assigneeData['assignee_type'] === CompanyDocumentApprovalAssignee::TYPE_USER
                            ? ($assigneeData['user_id'] ?? null)
                            : null,
                        'role_id' => $assigneeData['assignee_type'] === CompanyDocumentApprovalAssignee::TYPE_ROLE
                            ? ($assigneeData['role_id'] ?? null)
                            : null,
                    ]);
                }
            }
        });

        SysLogService::record(
            action: 'update',
            table: 'tbl_company_document_forms',
            recordId: $companyDocumentForm->company_document_form_id,
            description: 'Saved approval routing for template: '.$companyDocumentForm->code,
        );

        return response()->json([
            'ok' => true,
            'message' => 'Approval steps saved.',
        ]);
    }

    /**
     * @param  array<int, string>  $existing
     */
    private function uniqueFieldKey(string $base, array $existing): string
    {
        $base = $base !== '' ? $base : 'field';
        $key = $base;
        $index = 2;

        while (in_array($key, $existing, true)) {
            $key = $base.'_'.$index;
            $index++;
        }

        return Str::limit($key, 120, '');
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    private function normalizeElementSettings(?array $settings, bool $forStorage = false): array
    {
        if ($settings === null || $settings === [] || array_is_list($settings)) {
            return $forStorage ? [] : ['label_align' => 'top'];
        }

        $normalized = [
            'label_align' => (string) ($settings['label_align'] ?? 'top'),
        ];

        if (array_key_exists('pos_x', $settings)) {
            $normalized['pos_x'] = (int) $settings['pos_x'];
        }

        if (array_key_exists('pos_y', $settings)) {
            $normalized['pos_y'] = (int) $settings['pos_y'];
        }

        if (isset($settings['rows'])) {
            $normalized['rows'] = (int) $settings['rows'];
        }

        if (isset($settings['box_width'])) {
            $normalized['box_width'] = max(80, (int) $settings['box_width']);
        }

        if (isset($settings['box_height'])) {
            $normalized['box_height'] = max(36, (int) $settings['box_height']);
        }

        if (isset($settings['image_width'])) {
            $normalized['image_width'] = max(40, (int) $settings['image_width']);
        }

        if (isset($settings['image_height'])) {
            $normalized['image_height'] = max(40, (int) $settings['image_height']);
        }

        if (isset($settings['image_opacity'])) {
            $normalized['image_opacity'] = max(0, min(100, (int) $settings['image_opacity']));
        }

        if (isset($settings['image_rotate'])) {
            $degrees = ((int) $settings['image_rotate']) % 360;
            if ($degrees < 0) {
                $degrees += 360;
            }
            $normalized['image_rotate'] = $degrees;
        }

        if (array_key_exists('default_text', $settings)) {
            $normalized['default_text'] = (string) $settings['default_text'];
        }

        if (isset($settings['signature_preview']) && is_array($settings['signature_preview'])) {
            $preview = $settings['signature_preview'];
            $dataUrl = trim((string) ($preview['dataUrl'] ?? ''));

            if ($dataUrl !== '' && str_starts_with($dataUrl, 'data:image/')) {
                $mode = (string) ($preview['mode'] ?? 'draw');
                $normalized['signature_preview'] = [
                    'mode' => in_array($mode, ['draw', 'upload'], true) ? $mode : 'draw',
                    'dataUrl' => $dataUrl,
                    'fileName' => trim((string) ($preview['fileName'] ?? 'signature.png')) ?: 'signature.png',
                ];
            }
        }

        $tagKey = trim((string) ($settings['tag_key'] ?? ''));
        if ($tagKey !== '' && CompanyDocumentMergeTagCatalog::isValid($tagKey)) {
            $normalized['tag_key'] = $tagKey;
        }

        if (array_key_exists('font_family', $settings)
            || array_key_exists('font_size', $settings)
            || array_key_exists('font_color', $settings)) {
            $textStyle = CompanyDocumentTextStyle::normalize($settings);
            $normalized['font_family'] = $textStyle['font_family'];
            $normalized['font_size'] = $textStyle['font_size'];
            $normalized['font_color'] = $textStyle['font_color'];
        }

        return $normalized;
    }

    private function normalizeElementLabel(?string $label, string $type): ?string
    {
        if ($label === null || $label === '') {
            return CompanyDocumentElementCatalog::defaultLabel($type);
        }

        if ($type === CompanyDocumentElement::TYPE_PARAGRAPH) {
            return CompanyDocumentInlineFormatting::sanitize($label);
        }

        return $label;
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return array<string, mixed>|null
     */
    private function normalizeElementOptions(?array $options, string $type): ?array
    {
        if ($options === null || $options === []) {
            return null;
        }

        if (array_is_list($options)) {
            return null;
        }

        if ($type === 'image') {
            $path = trim((string) ($options['path'] ?? ''));
            if ($path === '') {
                return null;
            }

            $filename = trim((string) ($options['original_filename'] ?? ''));

            return [
                'path' => $path,
                'original_filename' => $filename !== '' ? $filename : null,
            ];
        }

        return $options;
    }
}
