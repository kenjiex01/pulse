<?php

namespace App\Services;

use App\Models\Campus;
use App\Support\HrLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrLookupUploadService
{
    /**
     * @return list<string>
     */
    public function headers(string $lookup): array
    {
        return array_map(
            fn (array $field): string => (string) $field['label'],
            HrLookup::fields($lookup),
        );
    }

    public function download(string $lookup): StreamedResponse
    {
        $filename = str_replace('-', '_', $lookup).'_upload_template.csv';
        $headers = $this->headers($lookup);

        return response()->streamDownload(function () use ($headers): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>, labels: list<string>}
     */
    public function import(string $lookup, UploadedFile $file): array
    {
        $rows = $this->rows($file);
        $headerMap = $this->headerMap($lookup, $rows[0] ?? []);
        $created = 0;
        $updated = 0;
        $errors = [];
        $labels = [];

        if ($headerMap === []) {
            return [
                'created' => 0,
                'updated' => 0,
                'errors' => ['The template header row does not match this module. Download a fresh template and try again.'],
                'labels' => [],
            ];
        }

        $dataRows = array_slice($rows, 1);
        $imported = false;

        foreach ($dataRows as $offset => $row) {
            $rowNumber = $offset + 2;
            $raw = $this->rowByField($lookup, $headerMap, $row);

            if ($this->rowIsEmpty($raw)) {
                continue;
            }

            $imported = true;
            $resolved = $this->resolveRow($lookup, $raw, $rowNumber);

            if ($resolved['error'] !== null) {
                $errors[] = $resolved['error'];

                continue;
            }

            $existing = $this->existingRecord($lookup, $resolved['payload']);
            $validator = Validator::make(
                $resolved['payload'],
                HrLookup::validationRules($lookup, $existing),
            );

            if ($validator->fails()) {
                $errors[] = 'Row '.$rowNumber.': '.$validator->errors()->first();

                continue;
            }

            $payload = HrLookup::validatedPayload($lookup, $validator->validated());

            try {
                $record = $this->save($lookup, $existing, $payload);
            } catch (ValidationException $exception) {
                $errors[] = 'Row '.$rowNumber.': '.collect($exception->errors())->flatten()->first();

                continue;
            }

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }

            $labels[] = HrLookup::recordLabel($record, $lookup);
        }

        if (! $imported) {
            $errors[] = 'The template has no data rows.';
        }

        return compact('created', 'updated', 'errors', 'labels');
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return array<string, int>
     */
    private function headerMap(string $lookup, array $headerRow): array
    {
        $wanted = [];

        foreach (HrLookup::fields($lookup) as $field) {
            $wanted[$this->normalizeHeader((string) $field['label'])] = $field['name'];
        }

        $map = [];

        foreach ($headerRow as $index => $header) {
            $key = $this->normalizeHeader((string) $header);
            if (isset($wanted[$key])) {
                $map[$wanted[$key]] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  list<string|null>  $row
     * @return array<string, string>
     */
    private function rowByField(string $lookup, array $headerMap, array $row): array
    {
        $raw = [];

        foreach (HrLookup::fields($lookup) as $field) {
            $index = $headerMap[$field['name']] ?? null;
            $raw[$field['name']] = $index === null ? '' : trim((string) ($row[$index] ?? ''));
        }

        return $raw;
    }

    /**
     * @param  array<string, string>  $raw
     */
    private function rowIsEmpty(array $raw): bool
    {
        foreach ($raw as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $raw
     * @return array{payload: array<string, mixed>, error: ?string}
     */
    private function resolveRow(string $lookup, array $raw, int $rowNumber): array
    {
        $payload = [];

        foreach (HrLookup::fields($lookup) as $field) {
            $name = $field['name'];
            $value = $raw[$name] ?? '';
            $type = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                $payload[$name] = $value === ''
                    ? (bool) ($field['default'] ?? true)
                    : in_array(strtolower($value), ['1', 'yes', 'y', 'true', 'active'], true);

                continue;
            }

            if ($type === 'select' && isset($field['options'])) {
                if ($value === '') {
                    $payload[$name] = '';

                    continue;
                }

                $matched = null;
                foreach ($field['options'] as $optionValue => $label) {
                    if (strcasecmp($value, (string) $optionValue) === 0 || strcasecmp($value, (string) $label) === 0) {
                        $matched = $optionValue;
                        break;
                    }
                }

                if ($matched === null) {
                    return [
                        'payload' => [],
                        'error' => 'Row '.$rowNumber.': '.$field['label'].' "'.$value.'" is not a valid choice.',
                    ];
                }

                $payload[$name] = $matched;

                continue;
            }

            if ($type === 'select' && ($field['source'] ?? null) === 'campuses') {
                if ($value === '') {
                    $payload[$name] = '';

                    continue;
                }

                $campus = Campus::query()
                    ->where(function ($query) use ($value): void {
                        $query->where('campus_code', $value)->orWhere('campus_name', $value);
                    })
                    ->first();

                if (! $campus) {
                    return [
                        'payload' => [],
                        'error' => 'Row '.$rowNumber.': '.$field['label'].' "'.$value.'" was not found. Use a campus code or campus name.',
                    ];
                }

                $payload[$name] = $campus->campus_id;

                continue;
            }

            if ($value === '' && in_array('nullable', $field['rules'] ?? [], true)) {
                $payload[$name] = null;

                continue;
            }

            $payload[$name] = $value;
        }

        return ['payload' => $payload, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function existingRecord(string $lookup, array $payload): ?Model
    {
        $config = HrLookup::config($lookup);

        foreach (HrLookup::fields($lookup) as $field) {
            if (empty($field['unique'])) {
                continue;
            }

            $value = $payload[$field['name']] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $found = $config['model']::query()->where($field['name'], $value)->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function save(string $lookup, ?Model $existing, array $payload): Model
    {
        $config = HrLookup::config($lookup);

        if ($existing) {
            $old = $existing->toArray();
            $existing->update($payload);
            $record = $existing->fresh() ?? $existing;

            SysLogService::record(
                action: 'update',
                table: $config['log_table'],
                recordId: $record->getKey(),
                oldValues: $old,
                newValues: $record->toArray(),
                description: 'Updated '.$config['name'].' record from upload: '.HrLookup::recordLabel($record, $lookup),
            );

            return $record;
        }

        $record = $config['model']::query()->create($payload);

        SysLogService::record(
            action: 'create',
            table: $config['log_table'],
            recordId: $record->getKey(),
            newValues: $record->fresh()?->toArray() ?? $record->toArray(),
            description: 'Created '.$config['name'].' record from upload: '.HrLookup::recordLabel($record, $lookup),
        );

        return $record;
    }

    /**
     * @return list<list<string|null>>
     */
    private function rows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();

            return array_map(
                fn (array $row): array => array_map(fn ($cell) => $cell === null ? '' : trim((string) $cell), $row),
                $sheet,
            );
        }

        $handle = fopen($file->getRealPath(), 'r');
        $rows = [];

        if ($handle === false) {
            return [];
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? (string) $row[0];
            }
            $rows[] = array_map(fn ($cell) => trim((string) $cell), $row);
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return strtolower(trim($header));
    }
}
