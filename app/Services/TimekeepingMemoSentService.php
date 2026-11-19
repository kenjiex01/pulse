<?php

namespace App\Services;

use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSendLog;
use App\Models\CompanyDocumentSubmission;
use App\Models\Employee;
use App\Models\TimekeepingMemoSendLog;
use App\Models\TimekeepingMemoSetup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class TimekeepingMemoSentService
{
    /**
     * @return Collection<int, array{
     *     submission_id: int|null,
     *     sent_at: ?Carbon,
     *     control_no: string,
     *     form_id: int|null,
     *     form_name: string,
     *     form_code: string,
     *     employee_name: string,
     *     employee_number: string,
     *     violation_type: string,
     *     violation_label: string,
     *     sender_name: string,
     *     source_label: string,
     *     search: string
     * }>
     */
    public function rows(): Collection
    {
        $memoLogs = TimekeepingMemoSendLog::query()
            ->with([
                'employee' => fn ($query) => $query->withTrashed(),
                'form' => fn ($query) => $query->withTrashed(),
                'sender',
            ])
            ->get();

        $rows = $memoLogs
            ->groupBy(fn (TimekeepingMemoSendLog $log) => $log->submission_id
                ? 'submission-'.$log->submission_id
                : 'memo-'.$log->getKey())
            ->map(fn (Collection $group) => $this->rowFromMemoGroup($group));

        $usedSubmissions = $memoLogs->pluck('submission_id')->filter()->unique()->all();

        $documentLogs = CompanyDocumentSendLog::query()
            ->with([
                'employee' => fn ($query) => $query->withTrashed(),
                'form' => fn ($query) => $query->withTrashed(),
                'sender',
            ])
            ->whereHas('form', fn ($query) => $query->withTrashed()->where('document_type', CompanyDocumentForm::TYPE_MEMO))
            ->when($usedSubmissions !== [], function ($query) use ($usedSubmissions): void {
                $query->where(function ($inner) use ($usedSubmissions): void {
                    $inner->whereNull('submission_id')
                        ->orWhereNotIn('submission_id', $usedSubmissions);
                });
            })
            ->get();

        foreach ($documentLogs as $log) {
            $rows->push($this->rowFromDocument($log));
        }

        return $rows
            ->sortByDesc(fn (array $row) => $row['sent_at']?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * @return array{
     *     submission: CompanyDocumentSubmission,
     *     employee: Employee,
     *     form: CompanyDocumentForm,
     *     control_no: string,
     *     violation_label: string,
     *     sent_at: ?Carbon,
     *     memo_context: array<string, mixed>
     * }
     */
    public function previewContext(CompanyDocumentSubmission $submission): array
    {
        $memoLogs = TimekeepingMemoSendLog::query()
            ->where('submission_id', $submission->submission_id)
            ->orderBy('work_date')
            ->get();
        $documentLog = CompanyDocumentSendLog::query()
            ->where('submission_id', $submission->submission_id)
            ->orderByDesc('sent_at')
            ->first();

        $employeeId = $memoLogs->first()?->employee_id ?? $documentLog?->employee_id;
        $employee = $employeeId === null
            ? null
            : Employee::query()->withTrashed()->find($employeeId);
        $form = CompanyDocumentForm::query()->withTrashed()->find($submission->company_document_form_id);

        if ($employee === null || $form === null) {
            throw new RuntimeException('This sent memo can no longer be previewed.');
        }

        $sentAt = $memoLogs->first()?->sent_at ?? $documentLog?->sent_at;
        $controlNo = trim((string) ($memoLogs->first()?->control_no ?? $documentLog?->control_no ?? ''));
        $violationType = (string) ($memoLogs->first()?->violation_type ?? '');
        $dates = $memoLogs
            ->pluck('work_date')
            ->map(fn ($date) => $date instanceof Carbon ? $date->toDateString() : (string) $date)
            ->filter()
            ->values()
            ->all();
        $periodStart = $dates[0] ?? ($sentAt?->toDateString() ?? now()->toDateString());
        $periodEnd = $dates === [] ? $periodStart : (string) end($dates);

        return [
            'submission' => $submission,
            'employee' => $employee,
            'form' => $form,
            'control_no' => $controlNo,
            'violation_label' => $violationType !== '' ? TimekeepingMemoSetup::labelForType($violationType) : 'Memo',
            'sent_at' => $sentAt,
            'memo_context' => [
                'date_from' => $periodStart,
                'date_to' => $periodEnd,
                'violation_type' => $violationType !== '' ? $violationType : 'absent',
                'violation_count' => count($dates),
                'selected_dates' => $dates,
                'control_no' => $controlNo,
                'memo_date' => $sentAt?->timezone('Asia/Manila')->format('F j, Y') ?? now('Asia/Manila')->format('F j, Y'),
            ],
        ];
    }

    /**
     * @param  Collection<int, TimekeepingMemoSendLog>  $group
     * @return array<string, mixed>
     */
    private function rowFromMemoGroup(Collection $group): array
    {
        $latest = $group->sortByDesc('sent_at')->first();
        $violationType = (string) ($latest?->violation_type ?? '');

        return $this->row(
            submissionId: $latest?->submission_id,
            sentAt: $latest?->sent_at,
            controlNo: (string) ($latest?->control_no ?? ''),
            form: $latest?->form,
            employee: $latest?->employee,
            violationType: $violationType,
            senderName: (string) ($latest?->sender?->name ?? ''),
            sourceLabel: 'Memo',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFromDocument(CompanyDocumentSendLog $log): array
    {
        return $this->row(
            submissionId: $log->submission_id,
            sentAt: $log->sent_at,
            controlNo: (string) ($log->control_no ?? ''),
            form: $log->form,
            employee: $log->employee,
            violationType: '',
            senderName: (string) ($log->sender?->name ?? ''),
            sourceLabel: 'Company Documents',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        ?int $submissionId,
        ?Carbon $sentAt,
        string $controlNo,
        ?CompanyDocumentForm $form,
        ?Employee $employee,
        string $violationType,
        string $senderName,
        string $sourceLabel,
    ): array {
        $formName = trim((string) ($form?->name ?? ''));
        $employeeName = trim((string) ($employee?->full_name ?? ''));
        $employeeNumber = trim((string) ($employee?->employee_number ?? ''));
        $violationLabel = $violationType !== '' ? TimekeepingMemoSetup::labelForType($violationType) : '';
        $sentLabel = $sentAt?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '';

        $search = strtolower(implode(' ', array_filter([
            $formName,
            (string) ($form?->code ?? ''),
            $employeeName,
            $employeeNumber,
            $controlNo,
            $violationLabel,
            $violationType,
            $senderName,
            $sourceLabel,
            $sentLabel,
            $sentAt?->toDateString() ?? '',
        ])));

        return [
            'submission_id' => $submissionId,
            'sent_at' => $sentAt,
            'sent_label' => $sentLabel,
            'control_no' => $controlNo,
            'form_id' => $form?->company_document_form_id,
            'form_name' => $formName !== '' ? $formName : 'Memo',
            'form_code' => (string) ($form?->code ?? ''),
            'employee_name' => $employeeName !== '' ? $employeeName : 'Employee',
            'employee_number' => $employeeNumber,
            'violation_type' => $violationType,
            'violation_label' => $violationLabel,
            'sender_name' => $senderName,
            'source_label' => $sourceLabel,
            'search' => $search,
        ];
    }
}
