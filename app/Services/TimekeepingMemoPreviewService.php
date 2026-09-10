<?php

namespace App\Services;

use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Models\TimekeepingMemoSetup;
use RuntimeException;

class TimekeepingMemoPreviewService
{
    public function __construct(
        private readonly CompanyDocumentMemoRenderService $renderService,
        private readonly TimekeepingMemoAttendanceService $attendanceService,
        private readonly TimekeepingMemoSendService $sendService,
    ) {}

    /**
     * @param  list<string>|null  $workDates
     * @return array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, string>,
     *     selected_dates: list<string>,
     *     memo_context: array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}
     * }
     */
    public function buildForEmployee(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        string $violationType,
        ?array $workDates,
    ): array {
        return $this->buildPreviewPayloadForEmployee($employee, $dateFrom, $dateTo, $violationType, $workDates);
    }

    /**
     * @param  list<string>|null  $workDates
     */
    public function renderPdfForEmployee(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        string $violationType,
        ?array $workDates,
    ): string {
        return $this->renderService->renderPdf($this->previewPayloadForPdf($employee, $dateFrom, $dateTo, $violationType, $workDates));
    }

    public function renderHtmlForEmployee(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        string $violationType,
        ?array $workDates,
    ): string {
        return $this->renderService->renderDocumentHtml($this->previewPayloadForPdf($employee, $dateFrom, $dateTo, $violationType, $workDates), true);
    }

    /**
     * @param  list<string>|null  $workDates
     * @return array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }
     */
    private function previewPayloadForPdf(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        string $violationType,
        ?array $workDates,
    ): array {
        $preview = $this->buildPreviewPayloadForEmployee($employee, $dateFrom, $dateTo, $violationType, $workDates);

        return [
            'form' => $preview['form'],
            'elements' => $preview['elements'],
            'preview_values' => $preview['preview_values'],
        ];
    }

    /**
     * @param  list<string>|null  $workDates
     * @return array{
     *     form: CompanyDocumentForm,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, string>,
     *     selected_dates: list<string>,
     *     memo_context: array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}
     * }
     */
    public function buildPreviewPayloadForEmployee(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        string $violationType,
        ?array $workDates,
    ): array {
        $violationType = $this->attendanceService->normalizeViolationType($violationType);
        $form = $this->resolveForm($violationType);
        $form->load('elements');

        $allDays = $this->attendanceService->violationDaysForEmployee($employee, $dateFrom, $dateTo, $violationType);
        $availableDates = collect($allDays)->pluck('work_date')->all();
        $selectedDates = $this->sendService->resolveSelectedDatesForPreview($workDates, $availableDates);

        if ($selectedDates === []) {
            throw new RuntimeException('No violation dates selected to preview.');
        }

        $memoContext = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'violation_type' => $violationType,
            'violation_count' => count($selectedDates),
            'selected_dates' => $selectedDates,
        ];

        $mapped = $this->renderService->buildPreviewData($form, $employee, $memoContext);

        return [
            'form' => $form,
            'elements' => $mapped['elements'],
            'preview_values' => $mapped['preview_values'],
            'selected_dates' => $selectedDates,
            'memo_context' => $memoContext,
        ];
    }

    private function resolveForm(string $violationType): CompanyDocumentForm
    {
        $setup = TimekeepingMemoSetup::query()
            ->where('violation_type', $violationType)
            ->with('form')
            ->first();

        if ($setup === null || $setup->company_document_form_id === null) {
            throw new RuntimeException('No memo template configured for '.TimekeepingMemoSetup::labelForType($violationType).'. Open Memo Setup first.');
        }

        /** @var CompanyDocumentForm|null $form */
        $form = $setup->form;
        if ($form === null || ! $form->is_active) {
            throw new RuntimeException('The selected memo template is missing or inactive.');
        }

        return $form;
    }
}
