<?php

namespace App\Services;

use App\Models\CompanyDocumentForm;
use App\Models\CompanyDocumentNteCase;
use Carbon\Carbon;
use RuntimeException;

class CompanyDocumentNteSyncService
{
    public function __construct(
        private readonly SkolarisApiService $skolaris,
    ) {}

    public function isConfiguredForForm(CompanyDocumentForm $form): bool
    {
        return $form->expectsWebNteResponse()
            && filled(config('skolaris.pulse_api_key'))
            && trim((string) $form->code) !== '';
    }

    /**
     * @return array{matched: int, message: string}
     */
    public function syncFromSkolaris(CompanyDocumentForm $form): array
    {
        if (! filled(config('skolaris.pulse_api_key'))) {
            throw new RuntimeException('Skolaris Pulse API key is not configured.');
        }

        if (! $form->expectsWebNteResponse()) {
            throw new RuntimeException('This template does not track web NTE responses.');
        }

        $templateCode = trim((string) $form->code);
        if ($templateCode === '') {
            throw new RuntimeException('This template has no code for Skolaris Company Documents matching.');
        }

        try {
            $payload = $this->skolaris->pullCompanyDocumentNteResponsesSync([
                'template_code' => $templateCode,
                'per_page' => 100,
            ]);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), '404')) {
                return [
                    'matched' => 0,
                    'message' => 'Skolaris Company Documents NTE response sync is not available yet. Matching will use template code "'.$templateCode.'" when the web endpoint is live.',
                ];
            }

            throw $exception;
        }

        $matched = $this->matchResponsesToOpenCases($form, $payload['data'] ?? []);

        return [
            'matched' => $matched,
            'message' => $matched > 0
                ? 'Marked '.$matched.' NTE case(s) as received from Skolaris (template '.$templateCode.').'
                : 'No new Skolaris NTE responses matched open cases for template '.$templateCode.'.',
        ];
    }

    public function openCaseCount(CompanyDocumentForm $form): int
    {
        return CompanyDocumentNteCase::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->whereIn('status', [
                CompanyDocumentNteCase::STATUS_PENDING,
                CompanyDocumentNteCase::STATUS_OVERDUE,
            ])
            ->count();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function matchResponsesToOpenCases(CompanyDocumentForm $form, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $openCases = CompanyDocumentNteCase::query()
            ->with('employee:employee_id,employee_number')
            ->where('company_document_form_id', $form->company_document_form_id)
            ->whereIn('status', [
                CompanyDocumentNteCase::STATUS_PENDING,
                CompanyDocumentNteCase::STATUS_OVERDUE,
            ])
            ->get();

        $matched = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $employeeNumber = trim((string) ($row['employee_number'] ?? ''));
            if ($employeeNumber === '') {
                continue;
            }

            $submittedAt = $this->parseTimestamp($row['submitted_at'] ?? $row['responded_at'] ?? null);
            if ($submittedAt === null) {
                continue;
            }

            $case = $openCases->first(function (CompanyDocumentNteCase $case) use ($employeeNumber, $submittedAt): bool {
                if ($case->employee?->employee_number !== $employeeNumber) {
                    return false;
                }

                $sentAt = $case->sent_at;
                $dueAt = $case->due_at;

                if ($sentAt === null || $dueAt === null) {
                    return false;
                }

                return $submittedAt->between($sentAt, $dueAt->copy()->endOfDay());
            });

            if ($case === null) {
                continue;
            }

            $case->update([
                'status' => CompanyDocumentNteCase::STATUS_RECEIVED,
                'responded_at' => $submittedAt,
                'skolaris_request_id' => isset($row['response_id']) ? (int) $row['response_id'] : null,
                'response_snapshot_json' => [
                    'source' => 'skolaris_company_documents',
                    'template_code' => $row['template_code'] ?? $form->code,
                    'payload' => $row,
                ],
            ]);

            $matched++;
        }

        return $matched;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
