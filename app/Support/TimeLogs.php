<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\RawTimekeepingTransaction;
use App\Models\SubModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TimeLogs
{
    public const SUB_MODULE_ROUTE = 'timekeeping.time-logs.index';

    /** @var array<int, string> */
    public const DTR_CAMPUS_CODES = ['SA', 'SU', 'CA'];

    public static function tabs(): array
    {
        return collect(config('time_logs.tabs', []))
            ->mapWithKeys(fn (array $tab, string $key) => [$key => $tab['label'] ?? $key])
            ->all();
    }

    public static function defaultTab(): string
    {
        return array_key_first(config('time_logs.tabs', [])) ?: 'time-in-out';
    }

    public static function resolveTab(?string $tab): string
    {
        $tab = $tab ?: self::defaultTab();

        if (! array_key_exists($tab, config('time_logs.tabs', []))) {
            abort(404);
        }

        return $tab;
    }

    public static function config(string $tab): array
    {
        $config = config("time_logs.tabs.$tab");

        if (! is_array($config)) {
            abort(404);
        }

        return $config;
    }

    public static function requiresCampus(string $tab): bool
    {
        return (bool) (self::config($tab)['requires_campus'] ?? false);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Campus>
     */
    public static function dtrCampuses()
    {
        return Campus::query()
            ->whereIn('campus_code', self::DTR_CAMPUS_CODES)
            ->where('is_active', true)
            ->orderByRaw("FIELD(campus_code, '".implode("','", self::DTR_CAMPUS_CODES)."')")
            ->get();
    }

    public static function transactionTypeId(string $tab): int
    {
        return (int) self::config($tab)['transaction_type_id'];
    }

    public static function routeName(string $action = 'index'): string
    {
        return match ($action) {
            'index' => 'timekeeping.time-logs.index',
            'tab' => 'timekeeping.time-logs.tab',
            'template' => 'timekeeping.time-logs.template',
            'process' => 'timekeeping.time-logs.process',
            'preview' => 'timekeeping.time-logs.preview',
            'commit' => 'timekeeping.time-logs.commit',
            'discard' => 'timekeeping.time-logs.discard',
            'show' => 'timekeeping.time-logs.show',
            'destroy' => 'timekeeping.time-logs.destroy',
            default => "timekeeping.time-logs.$action",
        };
    }

    public static function subModule(): ?SubModule
    {
        return SubModule::query()
            ->where('route_name', self::SUB_MODULE_ROUTE)
            ->where('is_active', true)
            ->first();
    }

    public static function authorize(User $user, string $permission): void
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

        $gatePermission = match ($permission) {
            'add' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            default => $permission,
        };

        if (! $user->can("time-logs.$gatePermission")) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    public static function query(string $tab): Builder
    {
        $config = self::config($tab);

        return RawTimekeepingTransaction::query()
            ->with(['uploadedBy', 'campus'])
            ->withCount('inAndOutRecords as records_count')
            ->where('timekeeping_transaction_type_id', $config['transaction_type_id']);
    }

    public static function columnValue(RawTimekeepingTransaction $record, string $key, ?string $type = null): mixed
    {
        return match ($key) {
            'uploaded_by_name' => $record->uploadedBy?->name,
            'records_count' => $record->records_count,
            'dt_uploaded' => $record->dt_uploaded?->format('M j, Y g:i A'),
            default => data_get($record, $key),
        };
    }
}
