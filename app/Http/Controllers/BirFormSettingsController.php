<?php

namespace App\Http\Controllers;

use App\Models\BirFormSetting;
use App\Services\SysLogService;
use App\Support\GovernmentIdNumbers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BirFormSettingsController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('bir-forms.viewAny'), 403);

        $settings = BirFormSetting::settings();

        SysLogService::record(
            action: 'read',
            table: 'tbl_bir_form_settings',
            description: 'Opened BIR Forms Setup',
        );

        return view('payroll.bir-forms.index', [
            'settings' => $settings,
            'defaults' => [
                'company_name' => (string) config('bir_forms.company_name', config('app.name')),
                'company_address' => (string) config('bir_forms.company_address', ''),
                'company_tin' => (string) config('bir_forms.company_tin', ''),
                'company_rdo_code' => (string) config('bir_forms.company_rdo_code', ''),
                'company_zip' => (string) config('bir_forms.company_zip', ''),
                'signatory_name' => (string) config('bir_forms.signatory_name', ''),
                'signatory_title' => (string) config('bir_forms.signatory_title', ''),
                'compensation_atc' => (string) config('bir_forms.compensation_atc', 'WI010'),
                'smw_rate_per_day' => (float) config('bir_forms.smw_rate_per_day', 600),
                'smw_rate_per_month' => (float) config('bir_forms.smw_rate_per_month', 15650),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bir-forms.update'), 403);

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_tin' => ['nullable', 'string', 'max:30'],
            'company_rdo_code' => ['nullable', 'string', 'max:20'],
            'company_zip' => ['nullable', 'string', 'max:20'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'compensation_atc' => ['nullable', 'string', 'max:20'],
            'smw_rate_per_day' => ['nullable', 'numeric', 'min:0'],
            'smw_rate_per_month' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tin = GovernmentIdNumbers::normalize($validated['company_tin'] ?? null);
        if ($tin !== null && ! GovernmentIdNumbers::isValid($tin, GovernmentIdNumbers::TYPE_TIN)) {
            return back()
                ->withInput()
                ->withErrors(['company_tin' => 'TIN must be 9 or 12 digits.']);
        }

        $settings = BirFormSetting::settings();
        $settings->fill([
            'company_name' => $this->nullableString($validated['company_name'] ?? null),
            'company_address' => $this->nullableString($validated['company_address'] ?? null),
            'company_tin' => $tin,
            'company_rdo_code' => $this->nullableString($validated['company_rdo_code'] ?? null),
            'company_zip' => $this->nullableString(preg_replace('/\D/', '', (string) ($validated['company_zip'] ?? '')) ?: null),
            'signatory_name' => $this->nullableString($validated['signatory_name'] ?? null),
            'signatory_title' => $this->nullableString($validated['signatory_title'] ?? null),
            'compensation_atc' => $this->nullableString($validated['compensation_atc'] ?? null),
            'smw_rate_per_day' => array_key_exists('smw_rate_per_day', $validated) && $validated['smw_rate_per_day'] !== null && $validated['smw_rate_per_day'] !== ''
                ? (float) $validated['smw_rate_per_day']
                : null,
            'smw_rate_per_month' => array_key_exists('smw_rate_per_month', $validated) && $validated['smw_rate_per_month'] !== null && $validated['smw_rate_per_month'] !== ''
                ? (float) $validated['smw_rate_per_month']
                : null,
        ]);
        $settings->save();

        SysLogService::record(
            action: 'update',
            table: 'tbl_bir_form_settings',
            recordId: (int) $settings->bir_form_setting_id,
            description: 'Updated BIR Forms Setup (employer / signatory / 2316 defaults)',
        );

        return redirect()
            ->route('payroll.bir-forms.index')
            ->with('success', 'BIR Forms Setup saved. These details will be used on BIR 1601-C and 2316 reports.');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
