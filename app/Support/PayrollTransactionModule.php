<?php

namespace App\Support;

use App\Models\RawPayrollTransaction;
use App\Models\SubModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PayrollTransactionModule
{
    public const SUB_MODULE_ROUTE = 'payroll.transaction.index';

    /** @var array<string, string> */
    public const MODULE_TABS = [
        'batches' => 'Payroll Batches',
        'upload-transactions' => 'Upload Adjustments',
        'unpost-batches' => 'Unpost Batches',
    ];

    public const DEFAULT_TAB = 'batches';

    public const DEFAULT_UPLOAD_TYPE = 'incomes';

    /** @var array<string, string> */
    public const BATCH_DETAIL_TABS = [
        'incomes' => 'Incomes',
        'deductions' => 'Deductions',
    ];

    public const DEFAULT_BATCH_DETAIL_TAB = 'incomes';

    public static function uploadTypes(): array
    {
        return collect(config('payroll_transaction.upload_types', []))
            ->mapWithKeys(fn ($config, $key) => [$key => is_array($config) ? ($config['label'] ?? $key) : $config])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function uploadConfig(string $uploadType): array
    {
        $config = config("payroll_transaction.upload_types.$uploadType");

        if (! is_array($config)) {
            abort(404);
        }

        return $config;
    }

    public static function uploadQuery(string $uploadType): Builder
    {
        $config = self::uploadConfig($uploadType);
        $relation = $config['detail_relation'];

        return RawPayrollTransaction::query()
            ->with(['payrollCalendar.payType', 'uploadedBy'])
            ->withCount("{$relation} as records_count")
            ->where('payroll_transaction_type_id', $config['transaction_type_id']);
    }

    public static function columnValue(RawPayrollTransaction $record, string $key, ?string $type = null): mixed
    {
        return match ($key) {
            'uploaded_by_name' => $record->uploadedBy?->name,
            'records_count' => $record->records_count,
            'pay_type' => $record->payrollCalendar?->payType?->pay_type,
            'pay_period' => $record->payrollCalendar?->formattedPayPeriod(),
            'pay_year' => $record->payrollCalendar?->pay_year,
            'dt_uploaded' => $record->dt_uploaded?->format('M j, Y g:i A'),
            default => data_get($record, $key),
        };
    }

    public static function resolveTab(?string $tab): string
    {
        $tab = $tab ?: self::DEFAULT_TAB;

        if (! array_key_exists($tab, self::MODULE_TABS)) {
            abort(404);
        }

        return $tab;
    }

    public static function resolveUploadType(?string $uploadType): string
    {
        $uploadType = $uploadType ?: self::DEFAULT_UPLOAD_TYPE;

        if (! array_key_exists($uploadType, self::uploadTypes())) {
            abort(404);
        }

        return $uploadType;
    }

    public static function resolveBatchDetailTab(?string $tab): string
    {
        $tab = $tab ?: self::DEFAULT_BATCH_DETAIL_TAB;

        if (! array_key_exists($tab, self::BATCH_DETAIL_TABS)) {
            return self::DEFAULT_BATCH_DETAIL_TAB;
        }

        return $tab;
    }

    public static function routeName(string $action = 'index'): string
    {
        return match ($action) {
            'upload.template' => 'payroll.transaction.upload.template',
            'upload.process' => 'payroll.transaction.upload.process',
            'upload.commit' => 'payroll.transaction.upload.commit',
            'upload.discard' => 'payroll.transaction.upload.discard',
            'upload.destroy' => 'payroll.transaction.upload.destroy',
            default => "payroll.transaction.$action",
        };
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
}
