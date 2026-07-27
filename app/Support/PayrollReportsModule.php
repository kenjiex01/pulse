<?php

namespace App\Support;

use App\Models\Report;
use App\Models\ReportClassification;
use App\Models\SubModule;
use App\Models\User;

class PayrollReportsModule
{
    public const SUB_MODULE_ROUTE = 'payroll.reports.index';

    public static function subModule(): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', self::SUB_MODULE_ROUTE)
            ->where('is_active', true)
            ->first();
    }

    public static function authorize(User $user, ?string $permission = null): void
    {
        $subModule = self::subModule();

        if (! $subModule) {
            if (! $user->isAdmin()) {
                abort(403, 'You do not have permission to access this page.');
            }

            return;
        }

        if ($permission === null) {
            if (! $user->hasSubModuleAccess($subModule)) {
                abort(403, 'You do not have permission to access this page.');
            }

            return;
        }

        if (! $user->hasSubModulePermission($subModule, $permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    public static function routeName(string $action = 'index'): string
    {
        return "payroll.reports.{$action}";
    }

    public static function defaultClassificationCode(): string
    {
        return ReportClassification::CODE_PAYROLL;
    }

    /**
     * @return array<string, string>
     */
    public static function activeClassifications(): array
    {
        return ReportClassification::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();
    }

    public static function resolveClassification(?string $code = null): ReportClassification
    {
        $code = $code ?: self::defaultClassificationCode();

        $classification = ReportClassification::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $classification) {
            abort(404);
        }

        return $classification;
    }

    public static function resolveReport(int $reportId, ?ReportClassification $classification = null): Report
    {
        $classification ??= self::resolveClassification();

        $report = Report::query()
            ->with(['group', 'fileTypes'])
            ->where('report_id', $reportId)
            ->where('report_classification_id', $classification->report_classification_id)
            ->where('is_active', true)
            ->first();

        if (! $report) {
            abort(404);
        }

        return $report;
    }

    public static function optionsConfig(string $optionsKey): array
    {
        $config = config("payroll_reports.options.$optionsKey");

        if (! is_array($config)) {
            abort(404);
        }

        return $config;
    }

    public static function generatorClass(string $generatorKey): string
    {
        $class = config("payroll_reports.generators.$generatorKey");

        if (! is_string($class) || ! class_exists($class)) {
            abort(404, 'Report generator is not configured.');
        }

        return $class;
    }
}
