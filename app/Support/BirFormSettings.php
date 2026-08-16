<?php

namespace App\Support;

use App\Models\BirFormSetting;

/**
 * Resolves BIR 1601-C / 2316 employer & signatory details.
 * DB overrides (Payroll → BIR Forms Setup) win over config/env defaults.
 */
class BirFormSettings
{
    /**
     * @return array{
     *     company_name: string,
     *     company_address: string,
     *     company_tin: string,
     *     company_rdo_code: string,
     *     company_zip: string,
     *     signatory_name: string,
     *     signatory_title: string,
     *     compensation_atc: string,
     *     smw_rate_per_day: float,
     *     smw_rate_per_month: float
     * }
     */
    public static function all(): array
    {
        $defaults = [
            'company_name' => (string) config('bir_forms.company_name', config('app.name')),
            'company_address' => (string) config('bir_forms.company_address', ''),
            'company_tin' => (string) config('bir_forms.company_tin', ''),
            'company_rdo_code' => (string) config('bir_forms.company_rdo_code', ''),
            'company_zip' => (string) config('bir_forms.company_zip', ''),
            'signatory_name' => (string) config('bir_forms.signatory_name', ''),
            'signatory_title' => (string) config('bir_forms.signatory_title', ''),
            'compensation_atc' => (string) config('bir_forms.compensation_atc', 'WI010'),
            'smw_rate_per_day' => (float) config('bir_forms.smw_rate_per_day', 0),
            'smw_rate_per_month' => (float) config('bir_forms.smw_rate_per_month', 0),
        ];

        try {
            $row = BirFormSetting::query()->first();
        } catch (\Throwable) {
            return $defaults;
        }

        if ($row === null) {
            return $defaults;
        }

        foreach (array_keys($defaults) as $key) {
            $value = $row->{$key};

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $defaults[$key] = in_array($key, ['smw_rate_per_day', 'smw_rate_per_month'], true)
                ? (float) $value
                : (string) $value;
        }

        return $defaults;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return $all[$key] ?? $default;
    }
}
