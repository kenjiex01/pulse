<?php

namespace App\Services;

use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentSendLog;
use App\Models\CompanyDocumentSubmission;
use App\Models\Employee;
use App\Models\LuIcctOffensePenalty;
use App\Models\TimekeepingMemoSendLog;
use Carbon\CarbonInterface;

class CompanyDocumentOffenseMemoService
{
    public function formHasOffenseNature(?CompanyDocumentForm $form): bool
    {
        return $form !== null && $form->hasOffenseNatureConfigured();
    }

    public function countPriorSends(CompanyDocumentForm $form, Employee $employee): int
    {
        return $this->countPriorSendsBefore($form, $employee, null);
    }

    public function offenseFrequencyLabelForSendLog(
        ?CompanyDocumentForm $form,
        ?Employee $employee,
        ?CompanyDocumentSubmission $submission,
        ?CarbonInterface $sentAt,
    ): string {
        if ($form === null || ! $this->formHasOffenseNature($form)) {
            return '';
        }

        if ($submission !== null) {
            $submission->loadMissing('values');
            $stored = trim((string) ($submission->values->firstWhere('field_key', 'offense_frequency')?->value_text ?? ''));
            if ($stored !== '') {
                return $stored;
            }
        }

        if ($employee === null || $sentAt === null) {
            return '';
        }

        $ordinal = $this->countPriorSendsBefore($form, $employee, $sentAt) + 1;

        return LuIcctOffensePenalty::frequencyLabel($ordinal);
    }

    private function countPriorSendsBefore(
        CompanyDocumentForm $form,
        Employee $employee,
        ?CarbonInterface $beforeSentAt,
    ): int {
        $formId = (int) $form->company_document_form_id;
        $employeeId = (int) $employee->employee_id;

        $companyDocumentQuery = CompanyDocumentSendLog::query()
            ->where('company_document_form_id', $formId)
            ->where('employee_id', $employeeId);

        $timekeepingQuery = TimekeepingMemoSendLog::query()
            ->where('company_document_form_id', $formId)
            ->where('employee_id', $employeeId);

        if ($beforeSentAt !== null) {
            $companyDocumentQuery->where('sent_at', '<', $beforeSentAt);
            $timekeepingQuery->where('sent_at', '<', $beforeSentAt);
        }

        return $companyDocumentQuery->count() + $timekeepingQuery->count();
    }

    public function nextFrequencyOrdinal(CompanyDocumentForm $form, ?Employee $employee): int
    {
        if (! $this->formHasOffenseNature($form)) {
            return 0;
        }

        if ($employee === null) {
            return 2;
        }

        return $this->countPriorSends($form, $employee) + 1;
    }

    /**
     * @param  array<string, mixed>  $memoContext
     * @return array<string, mixed>
     */
    public function enrichMemoContext(CompanyDocumentForm $form, ?Employee $employee, array $memoContext): array
    {
        if (! $this->formHasOffenseNature($form)) {
            return $memoContext;
        }

        $form->loadMissing('icctOffense');

        $ordinal = $this->nextFrequencyOrdinal($form, $employee);
        $category = $this->resolvePenaltyCategory($form, $memoContext);
        $frequencyLabel = LuIcctOffensePenalty::frequencyLabel($ordinal);
        $disciplinaryAction = $category !== ''
            ? (LuIcctOffensePenalty::penaltyFor($category, $ordinal) ?? '')
            : '';

        return array_merge($memoContext, [
            'offense_frequency_ordinal' => $ordinal,
            'offense_frequency_label' => $frequencyLabel,
            'disciplinary_action' => $disciplinaryAction,
        ]);
    }

    /**
     * @return array{disciplinary_action: string, offense_frequency: string}
     */
    public function previewTagSamples(CompanyDocumentForm $form): array
    {
        $context = $this->enrichMemoContext($form, null, []);

        return [
            'disciplinary_action' => (string) ($context['disciplinary_action'] ?? 'Written Warning'),
            'offense_frequency' => (string) ($context['offense_frequency_label'] ?? 'Second Offense'),
        ];
    }

    /**
     * @param  array<string, mixed>  $memoContext
     */
    private function resolvePenaltyCategory(CompanyDocumentForm $form, array $memoContext): string
    {
        $category = trim((string) ($form->icctOffense?->category ?? ''));

        if ($category === '') {
            $defaults = $form->icctOffenseFieldDefaults();
            $category = trim((string) ($defaults['offense_category'] ?? ''));
        }

        if ($category === '') {
            $category = trim((string) ($memoContext['offense_category'] ?? ''));
        }

        if (str_contains($category, '-')) {
            $category = trim(explode('-', $category)[0]);
        }

        return $category;
    }
}
