<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocumentForm;
use App\Models\TimekeepingMemoSetup;
use App\Services\SysLogService;
use App\Support\TimekeepingMemoSetup as TimekeepingMemoSetupSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimekeepingMemoSetupController extends Controller
{
    public function index(): View
    {
        TimekeepingMemoSetupSupport::authorize(auth()->user(), 'view');

        $setups = TimekeepingMemoSetup::query()
            ->with('form')
            ->orderByRaw("CASE violation_type WHEN 'late' THEN 1 WHEN 'undertime' THEN 2 WHEN 'absent' THEN 3 ELSE 4 END")
            ->get()
            ->keyBy('violation_type');

        $memoForms = CompanyDocumentForm::query()
            ->where('document_type', CompanyDocumentForm::TYPE_MEMO)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['company_document_form_id', 'code', 'name']);

        SysLogService::record(
            action: 'read',
            table: 'tbl_timekeeping_memo_setups',
            description: 'Opened Memo Setup',
        );

        return view('timekeeping.memo-setup.index', [
            'setups' => $setups,
            'memoForms' => $memoForms,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        TimekeepingMemoSetupSupport::authorize($request->user(), 'update');

        $rules = [
            'late_form_id' => ['nullable', 'integer', 'exists:tbl_company_document_forms,company_document_form_id'],
            'undertime_form_id' => ['nullable', 'integer', 'exists:tbl_company_document_forms,company_document_form_id'],
            'absent_form_id' => ['nullable', 'integer', 'exists:tbl_company_document_forms,company_document_form_id'],
        ];

        foreach (TimekeepingMemoSetup::TYPES as $type) {
            $rules[$type.'_email_subject'] = ['required', 'string', 'max:255'];
            $rules[$type.'_email_body'] = ['required', 'string', 'max:10000'];
            $rules[$type.'_email_cc'] = [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    foreach (TimekeepingMemoSetup::parseCcList($value) as $email) {
                        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $fail('Each CC address must be a valid email.');
                        }
                    }
                },
            ];
        }

        $validated = $request->validate($rules);

        foreach (TimekeepingMemoSetup::TYPES as $type) {
            TimekeepingMemoSetup::query()
                ->where('violation_type', $type)
                ->update([
                    'company_document_form_id' => $validated[$type.'_form_id'] ?? null ?: null,
                    'email_subject' => $validated[$type.'_email_subject'],
                    'email_body' => $validated[$type.'_email_body'],
                    'email_cc' => filled($validated[$type.'_email_cc'] ?? null)
                        ? trim((string) $validated[$type.'_email_cc'])
                        : null,
                ]);
        }

        SysLogService::record(
            action: 'update',
            table: 'tbl_timekeeping_memo_setups',
            description: 'Updated Memo Setup templates and email settings for late, undertime, and absent',
        );

        return redirect()
            ->route(TimekeepingMemoSetupSupport::routeName('index'))
            ->with('success', 'Memo Setup saved.');
    }
}
