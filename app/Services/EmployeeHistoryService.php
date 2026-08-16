<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SysLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeHistoryService
{
    /**
     * @return array<string, string>
     */
    public function fieldLabels(): array
    {
        return config('employee_history.field_labels', []);
    }

    public function logsForEmployee(Employee $employee, int $perPage = 15): LengthAwarePaginator
    {
        return SysLog::query()
            ->where('table_name', 'tbl_employees')
            ->where('record_id', $employee->employee_id)
            ->whereIn('action', ['create', 'update', 'delete'])
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<int, array{field: string, label: string, old: mixed, new: mixed}>
     */
    public function changesForLog(SysLog $log): array
    {
        $oldValues = is_array($log->old_values) ? $log->old_values : [];
        $newValues = is_array($log->new_values) ? $log->new_values : [];
        $labels = $this->fieldLabels();
        $changes = [];

        if ($log->action === 'create') {
            foreach ($newValues as $field => $value) {
                $changes[] = [
                    'field' => (string) $field,
                    'label' => $labels[$field] ?? $this->humanizeField((string) $field),
                    'old' => null,
                    'new' => $value,
                ];
            }

            return $changes;
        }

        if ($log->action === 'delete') {
            foreach ($oldValues as $field => $value) {
                $changes[] = [
                    'field' => (string) $field,
                    'label' => $labels[$field] ?? $this->humanizeField((string) $field),
                    'old' => $value,
                    'new' => null,
                ];
            }

            return $changes;
        }

        $fields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($fields as $field) {
            $old = $oldValues[$field] ?? null;
            $new = $newValues[$field] ?? null;

            if ($this->valuesEqual($old, $new)) {
                continue;
            }

            $changes[] = [
                'field' => (string) $field,
                'label' => $labels[$field] ?? $this->humanizeField((string) $field),
                'old' => $old,
                'new' => $new,
            ];
        }

        return $changes;
    }

    public function formatDisplayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : '—';
        }

        return (string) $value;
    }

    public function actionLabel(string $action): string
    {
        return match ($action) {
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            default => ucfirst($action),
        };
    }

    private function humanizeField(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return json_encode($left) === json_encode($right);
        }

        return $left == $right;
    }
}
