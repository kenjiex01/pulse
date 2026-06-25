<?php

namespace App\Support;

use App\Models\EmployeeEmploymentInformation;
use App\Models\PayrollCalendar;
use App\Models\PayType;
use App\Models\SubModule;
use App\Models\User;
use Carbon\Carbon;

class PayrollCalendarModule
{
    public const SUB_MODULE_ROUTE = 'payroll.calendar.index';

    /** @var array<string, int> */
    public const PAY_TYPE_SLUGS = [
        'daily' => PayType::DAILY,
        'weekly' => PayType::WEEKLY,
        'semi-monthly' => PayType::SEMI_MONTHLY,
        'monthly' => PayType::MONTHLY,
    ];

    /** @var array<int, string> */
    public const PAY_TYPE_LABELS = [
        PayType::DAILY => 'Daily',
        PayType::WEEKLY => 'Weekly',
        PayType::SEMI_MONTHLY => 'Semi-Monthly',
        PayType::MONTHLY => 'Monthly',
    ];

    /** @var array<string, string> */
    public const MODULE_TABS = [
        'calendar' => 'Pay Periods',
        'priority' => 'Deduction & Loan Priority',
    ];

    /** @var array<int, string> */
    public const MONTHS = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    public static function payTypeTabs(): array
    {
        return self::PAY_TYPE_LABELS;
    }

    public static function defaultPayTypeSlug(): string
    {
        return 'daily';
    }

    public static function resolvePayTypeSlug(?string $slug): string
    {
        $slug = $slug ?: self::defaultPayTypeSlug();

        if (! array_key_exists($slug, self::PAY_TYPE_SLUGS)) {
            abort(404);
        }

        return $slug;
    }

    public static function payTypeIdFromSlug(string $slug): int
    {
        return self::PAY_TYPE_SLUGS[self::resolvePayTypeSlug($slug)];
    }

    public static function payTypeSlugFromId(int $payTypeId): string
    {
        $slug = array_search($payTypeId, self::PAY_TYPE_SLUGS, true);

        if ($slug === false) {
            abort(404);
        }

        return $slug;
    }

    public static function payTypeLabel(int $payTypeId): string
    {
        return self::PAY_TYPE_LABELS[$payTypeId] ?? 'Unknown';
    }

    public static function monthLabel(int $month): string
    {
        return self::MONTHS[$month] ?? '—';
    }

    public static function shortMonthLabel(int $month): string
    {
        return Carbon::createFromDate(2000, $month, 1)->format('M');
    }

    /**
     * @return array<int, int>
     */
    public static function yearOptions(?int $selectedYear = null): array
    {
        $currentYear = (int) date('Y');
        $minYear = PayrollCalendar::query()->min('pay_year');
        $maxYear = PayrollCalendar::query()->max('pay_year');

        $minYear = is_numeric($minYear) ? (int) $minYear : $currentYear;
        $maxYear = is_numeric($maxYear) ? max((int) $maxYear, $currentYear) : $currentYear;

        if ($minYear > $currentYear) {
            $minYear = $currentYear;
        }

        $maxYear = max($maxYear + 1, $currentYear);

        if ($selectedYear !== null) {
            $minYear = min($minYear, $selectedYear);
            $maxYear = max($maxYear, $selectedYear);
        }

        $years = [];

        for ($year = $minYear; $year <= $maxYear; $year++) {
            $years[] = $year;
        }

        return $years;
    }

    public static function nextPayPeriod(int $payTypeId, int $payYear): int
    {
        $last = PayrollCalendar::query()
            ->where('pay_type_id', $payTypeId)
            ->where('pay_year', $payYear)
            ->max('pay_period');

        return is_numeric($last) ? ((int) $last + 1) : 1;
    }

    public static function routeName(string $action = 'index'): string
    {
        return "payroll.calendar.$action";
    }

    public static function subModule(): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', self::SUB_MODULE_ROUTE)
            ->first();
    }

    public static function authorize(User $user, string $permission = 'view'): void
    {
        $subModule = self::subModule();

        if (! $subModule) {
            if (! $user->isAdmin()) {
                abort(403, 'You do not have permission to access this page.');
            }

            return;
        }

        if ($permission === 'view') {
            if (! $user->hasSubModuleAccess($subModule)) {
                abort(403, 'You do not have permission to access this page.');
            }

            return;
        }

        if (! $user->hasSubModulePermission($subModule, $permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    public static function periodLabel(PayrollCalendar $period): string
    {
        return sprintf(
            '%s pay period "%s" for pay year "%s"',
            strtolower(self::payTypeLabel((int) $period->pay_type_id)),
            $period->formattedPayPeriod(),
            $period->pay_year
        );
    }

    /**
     * @return array<string, string>
     */
    public static function userTypeOptions(): array
    {
        return [
            EmployeeEmploymentInformation::TYPE_FACULTY => 'Faculty',
            EmployeeEmploymentInformation::TYPE_STAFF => 'Staff',
            EmployeeEmploymentInformation::TYPE_ADMIN => 'Admin',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function userTypeKeys(): array
    {
        return array_keys(self::userTypeOptions());
    }

    /**
     * @param  array<int, string>  $userTypes
     */
    public static function isAdminOnlyCategory(array $userTypes): bool
    {
        $types = collect($userTypes)->filter()->unique()->values();

        return $types->count() === 1 && $types->first() === EmployeeEmploymentInformation::TYPE_ADMIN;
    }

    /**
     * @param  array<int, string>  $userTypes
     */
    public static function requiresColleges(array $userTypes): bool
    {
        return ! self::isAdminOnlyCategory($userTypes);
    }
}
