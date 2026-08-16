@extends('layouts.app')

@section('title', 'BIR Forms Setup — '.config('app.name'))

@section('content')
    @php
        $value = static function (string $key) use ($settings, $defaults) {
            $current = old($key, $settings->{$key});

            if ($current === null || $current === '') {
                return old($key, $defaults[$key] ?? '');
            }

            return $current;
        };
    @endphp

    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'BIR Forms Setup',
        'description' => 'Configure employer and signatory details used on BIR Form 1601-C and BIR Form 2316 reports.',
    ])

    <form method="POST" action="{{ route('payroll.bir-forms.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-gray-900">Employer / Withholding Agent</h3>
            <p class="mt-1 text-sm text-gray-600">Used on the header of BIR 1601-C and Parts II of BIR 2316.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="lg:col-span-2">
                    <label for="company_name" class="form-label">Registered Name</label>
                    <input id="company_name" name="company_name" type="text" value="{{ $value('company_name') }}" class="form-input" maxlength="255">
                    @error('company_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="lg:col-span-2">
                    <label for="company_address" class="form-label">Registered Address</label>
                    <input id="company_address" name="company_address" type="text" value="{{ $value('company_address') }}" class="form-input" maxlength="500">
                    @error('company_address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="company_tin" class="form-label">TIN</label>
                    <input id="company_tin" name="company_tin" type="text" value="{{ $value('company_tin') }}" class="form-input" maxlength="30" placeholder="000000000000">
                    <p class="mt-1 text-xs text-gray-500">9 or 12 digits (hyphens optional).</p>
                    @error('company_tin')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="company_rdo_code" class="form-label">RDO Code</label>
                    <input id="company_rdo_code" name="company_rdo_code" type="text" value="{{ $value('company_rdo_code') }}" class="form-input" maxlength="20" placeholder="046">
                    @error('company_rdo_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="company_zip" class="form-label">ZIP Code</label>
                    <input id="company_zip" name="company_zip" type="text" value="{{ $value('company_zip') }}" class="form-input" maxlength="20" placeholder="1900">
                    @error('company_zip')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="compensation_atc" class="form-label">Compensation ATC (1601-C)</label>
                    <input id="compensation_atc" name="compensation_atc" type="text" value="{{ $value('compensation_atc') }}" class="form-input" maxlength="20" placeholder="WI010">
                    @error('compensation_atc')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-gray-900">Authorized Signatory</h3>
            <p class="mt-1 text-sm text-gray-600">Printed name/title stamped on BIR 1601-C and 2316 certificates.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div>
                    <label for="signatory_name" class="form-label">Signatory Name</label>
                    <input id="signatory_name" name="signatory_name" type="text" value="{{ $value('signatory_name') }}" class="form-input" maxlength="255">
                    @error('signatory_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="signatory_title" class="form-label">Title / Position</label>
                    <input id="signatory_title" name="signatory_title" type="text" value="{{ $value('signatory_title') }}" class="form-input" maxlength="255">
                    @error('signatory_title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-gray-900">BIR 2316 — Minimum Wage Defaults</h3>
            <p class="mt-1 text-sm text-gray-600">Shown on Form 2316 items for Minimum Wage Earners when applicable.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div>
                    <label for="smw_rate_per_day" class="form-label">Statutory Minimum Wage / Day</label>
                    <input id="smw_rate_per_day" name="smw_rate_per_day" type="number" step="0.01" min="0" value="{{ $value('smw_rate_per_day') }}" class="form-input">
                    @error('smw_rate_per_day')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="smw_rate_per_month" class="form-label">Statutory Minimum Wage / Month</label>
                    <input id="smw_rate_per_month" name="smw_rate_per_month" type="number" step="0.01" min="0" value="{{ $value('smw_rate_per_month') }}" class="form-input">
                    @error('smw_rate_per_month')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @can('bir-forms.update')
                <button type="submit" class="btn-primary">Save Setup</button>
            @endcan
            <p class="text-sm text-gray-500">Leave a field blank to fall back to the default from application config / .env.</p>
        </div>
    </form>
@endsection
