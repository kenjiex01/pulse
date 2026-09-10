<?php

namespace App\Services;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSubmission;
use App\Models\CompanyDocumentSubmissionValue;
use App\Models\Employee;
use App\Models\TimekeepingMemoSendLog;
use App\Models\TimekeepingMemoSetup;
use App\Models\User;
use App\Support\MemoPdfFilename;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TimekeepingMemoSendService
{
    public function __construct(
        private readonly CompanyDocumentFormBuilderService $builderService,
        private readonly CompanyDocumentFileService $fileService,
        private readonly CompanyDocumentMemoRenderService $memoRenderService,
        private readonly CompanyDocumentMemoValueResolver $valueResolver,
        private readonly TimekeepingMemoAttendanceService $attendanceService,
        private readonly TimekeepingMemoEmailService $emailService,
    ) {}

    /**
     * @param  list<string>|null  $workDates  Empty = all violation days in range
     * @return array{submission_id: int, sent_dates: list<string>}
     */
    public function sendForEmployee(
        Employee $employee,
        string $dateFrom,
        string $dateTo,
        string $violationType,
        ?array $workDates,
        User $sender,
    ): array {
        @set_time_limit(120);

        $violationType = $this->attendanceService->normalizeViolationType($violationType);
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

        $form->load('elements');
        $allDays = $this->attendanceService->violationDaysForEmployee($employee, $dateFrom, $dateTo, $violationType);
        $availableDates = collect($allDays)->pluck('work_date')->all();

        $selectedDates = $this->resolveSelectedDates($workDates, $availableDates);
        if ($selectedDates === []) {
            throw new RuntimeException('No violation dates selected to send.');
        }

        $memoContext = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'violation_type' => $violationType,
            'violation_count' => count($selectedDates),
            'selected_dates' => $selectedDates,
        ];

        $preview = $this->memoRenderService->buildPreviewData($form, $employee, $memoContext);
        $memoPdf = $this->memoRenderService->renderPdf($preview);
        $pdfFilename = MemoPdfFilename::for($form, $employee, $violationType);

        $result = DB::transaction(function () use ($employee, $form, $setup, $violationType, $selectedDates, $sender, $memoContext) {
            $submission = CompanyDocumentSubmission::query()->create([
                'company_document_form_id' => $form->company_document_form_id,
                'form_version' => $form->version,
                'form_snapshot_json' => $this->builderService->snapshotForm($form),
                'submitted_by_user_id' => $sender->id,
                'status' => CompanyDocumentSubmission::STATUS_COMPLETED,
                'submitted_at' => now(),
                'completed_at' => now(),
            ]);

            foreach ($form->elements as $element) {
                if (! $element->isInput()) {
                    continue;
                }

                $key = (string) $element->field_key;

                if ($element->type === CompanyDocumentElement::TYPE_SIGNATURE) {
                    $row = [
                        'submission_id' => $submission->submission_id,
                        'element_id' => $element->element_id,
                        'field_key' => $key,
                    ];

                    $dataUrl = $this->valueResolver->signatureDataUrl($element);
                    if ($dataUrl !== '') {
                        $row = array_merge($row, $this->fileService->storeSignatureFromDataUrl($dataUrl, $submission->submission_id));
                    }

                    CompanyDocumentSubmissionValue::query()->create($row);

                    continue;
                }

                $value = $this->valueResolver->resolveInputValue($element, $employee, $memoContext);

                CompanyDocumentSubmissionValue::query()->create([
                    'submission_id' => $submission->submission_id,
                    'element_id' => $element->element_id,
                    'field_key' => $key,
                    'value_text' => $value,
                ]);
            }

            $sentAt = now();
            foreach ($selectedDates as $workDate) {
                TimekeepingMemoSendLog::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->employee_id,
                        'violation_type' => $violationType,
                        'work_date' => $workDate,
                    ],
                    [
                        'company_document_form_id' => $form->company_document_form_id,
                        'submission_id' => $submission->submission_id,
                        'sent_by_user_id' => $sender->id,
                        'sent_at' => $sentAt,
                    ],
                );
            }

            return [
                'submission_id' => (int) $submission->submission_id,
                'sent_dates' => $selectedDates,
            ];
        });

        $this->emailService->sendForEmployee($employee, $setup, $memoContext, $form, $memoPdf, $pdfFilename);

        return $result;
    }

    /**
     * @param  list<string>|null  $workDates
     * @param  list<string>  $availableDates
     * @return list<string>
     */
    public function resolveSelectedDatesForPreview(?array $workDates, array $availableDates): array
    {
        return $this->resolveSelectedDates($workDates, $availableDates);
    }

    /**
     * @param  array{date_from: string, date_to: string, violation_type: string, violation_count: int, selected_dates: list<string>}  $memoContext
     */
    public function resolveInputValueForEmployee(CompanyDocumentElement $element, Employee $employee, array $memoContext): ?string
    {
        return $this->valueResolver->resolveInputValue($element, $employee, $memoContext);
    }

    /**
     * @param  list<string>|null  $workDates
     * @param  list<string>  $availableDates
     * @return list<string>
     */
    private function resolveSelectedDates(?array $workDates, array $availableDates): array
    {
        if ($workDates === null || $workDates === []) {
            return array_values($availableDates);
        }

        $allowed = array_flip($availableDates);

        return array_values(array_filter(
            $workDates,
            fn (string $date) => isset($allowed[$date]),
        ));
    }
}
