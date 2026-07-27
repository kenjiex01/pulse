<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\RawTimekeepingTransaction;
use App\Models\SubModule;
use App\Models\TeachingLoadPullBatch;
use App\Models\User;
use App\Services\SkolarisApiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TimeLogs
{
    public const SUB_MODULE_ROUTE = 'timekeeping.time-logs.index';

    public const TEACHING_LOADS_TAB = 'teaching-loads';

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

    public static function isSkolarisPullTab(string $tab): bool
    {
        return (self::config($tab)['type'] ?? null) === 'skolaris_pull';
    }

    public static function requiresCampus(string $tab): bool
    {
        return (bool) (self::config($tab)['requires_campus'] ?? false);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Campus>
     */
    public static function dtrCampuses()
    {
        $order = array_flip(self::DTR_CAMPUS_CODES);

        return Campus::query()
            ->whereIn('campus_code', self::DTR_CAMPUS_CODES)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Campus $campus) => $order[$campus->campus_code] ?? PHP_INT_MAX)
            ->values();
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
            'pull.start' => 'timekeeping.time-logs.pull.start',
            'pull.step' => 'timekeeping.time-logs.pull.step',
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

    /**
     * @return array<int, string>
     */
    public static function skolarisEmployeeNumbers(): array
    {
        return app(SkolarisApiService::class)->listEmployeeNumbers();
    }

    public static function eligiblePullEmployeesQuery(?string $search = null): Builder
    {
        // Local faculty picker only — do not call Skolaris on page load (that can time out).
        // Per-employee pull still verifies data via dailyLoads().
        return Employee::query()
            ->facultyEligible()
            ->where('is_active', true)
            ->whereNotNull('employee_number')
            ->where('employee_number', '!=', '')
            ->search($search)
            ->with(['teachingLoadSyncStatus.lastPulledBy'])
            ->leftJoin('teaching_load_sync_status', function ($join) {
                $join->on('tbl_employees.employee_id', '=', 'teaching_load_sync_status.employee_id')
                    ->whereNull('teaching_load_sync_status.deleted_at');
            })
            ->select('tbl_employees.*')
            ->orderByRaw('teaching_load_sync_status.last_pulled_at IS NULL')
            ->orderByDesc('teaching_load_sync_status.last_pulled_at')
            ->orderBy('tbl_employees.employee_number');
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, int>
     */
    public static function eligibleEmployeeIds(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return self::eligiblePullEmployeesQuery()
            ->whereIn('tbl_employees.employee_id', $employeeIds)
            ->pluck('tbl_employees.employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function query(string $tab): Builder
    {
        if (self::isSkolarisPullTab($tab)) {
            return TeachingLoadPullBatch::query()
                ->with('pulledBy')
                ->withCount('sessions as records_count')
                ->withCount(['sessions as employee_count' => fn ($query) => $query->select(DB::raw('count(distinct employee_id)'))])
                ->orderByDesc('pulled_at')
                ->orderByDesc('teaching_load_pull_batch_id');
        }

        $config = self::config($tab);

        return RawTimekeepingTransaction::query()
            ->with(['uploadedBy', 'campus'])
            ->withCount('inAndOutRecords as records_count')
            ->where('timekeeping_transaction_type_id', $config['transaction_type_id']);
    }

    public static function columnValue(mixed $record, string $key, ?string $type = null): mixed
    {
        if ($record instanceof TeachingLoadPullBatch) {
            return match ($key) {
                'batch_no' => 'Batch #'.$record->formattedBatchNo(),
                'pulled_at' => $record->pulled_at?->format('M j, Y g:i A') ?? '—',
                'pulled_by_name' => $record->pulledBy?->name ?? '—',
                'employee_count' => $record->employee_count ?? 0,
                'records_count' => $record->records_count ?? 0,
                'date_range' => $record->dateRangeLabel(),
                default => data_get($record, $key),
            };
        }

        return match ($key) {
            'uploaded_by_name' => $record->uploadedBy?->name,
            'records_count' => $record->records_count,
            'dt_uploaded' => $record->dt_uploaded?->format('M j, Y g:i A'),
            default => data_get($record, $key),
        };
    }
}
