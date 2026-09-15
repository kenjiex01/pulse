<?php

namespace App\Services;

use App\Models\PulseFacultyLoadItem;
use App\Models\PulseFacultyLoadUpload;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UploadedFacultyLoadService
{
    public function __construct(
        private readonly SkolarisApiService $skolaris,
    ) {}

    public function paginate(string $search = '', ?string $parseStatus = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = PulseFacultyLoadUpload::query()
            ->withCount('items')
            ->with('puller:id,name,email')
            ->orderByDesc('pulled_at')
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('faculty_name', 'like', '%'.$search.'%')
                    ->orWhere('employee_number', 'like', '%'.$search.'%')
                    ->orWhere('campus_name', 'like', '%'.$search.'%')
                    ->orWhere('term_label', 'like', '%'.$search.'%')
                    ->orWhere('original_filename', 'like', '%'.$search.'%')
                    ->orWhere('skolaris_uploader_name', 'like', '%'.$search.'%')
                    ->orWhereHas('puller', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($parseStatus !== null && $parseStatus !== '' && $parseStatus !== 'all') {
            $query->where('parse_status', $parseStatus);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array{created: int, updated: int, skipped: int, total: int}
     */
    public function pullFromSkolaris(
        User $user,
        ?string $parseStatus = null,
        bool $refreshExisting = false,
    ): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $page = 1;
        $lastPage = 1;

        do {
            $payload = $this->skolaris->listUploadedFacultyLoads(
                page: $page,
                perPage: 100,
                parseStatus: $parseStatus !== 'all' ? $parseStatus : null,
            );

            foreach ($payload['data'] as $summary) {
                if (! is_array($summary)) {
                    continue;
                }

                $skolarisUploadId = (int) ($summary['upload_id'] ?? 0);

                if ($skolarisUploadId <= 0) {
                    continue;
                }

                $existing = PulseFacultyLoadUpload::query()
                    ->where('skolaris_upload_id', $skolarisUploadId)
                    ->first();

                $skolarisUpdatedAt = $this->parseSkolarisTimestamp($summary['updated_at'] ?? $summary['created_at'] ?? null);

                if ($existing && ! $refreshExisting) {
                    if ($existing->skolaris_updated_at && $skolarisUpdatedAt
                        && $existing->skolaris_updated_at->gte($skolarisUpdatedAt)) {
                        $skipped++;

                        continue;
                    }
                }

                $detail = $this->skolaris->getUploadedFacultyLoad($skolarisUploadId);
                $isUpdate = $existing !== null;

                DB::transaction(function () use ($user, $detail, $existing, $skolarisUploadId, $skolarisUpdatedAt) {
                    $upload = $existing ?? new PulseFacultyLoadUpload([
                        'skolaris_upload_id' => $skolarisUploadId,
                    ]);

                    $upload->fill([
                        'faculty_name' => $detail['faculty_name'] ?? null,
                        'employee_number' => $detail['employee_number'] ?? null,
                        'faculty_email' => $detail['faculty_email'] ?? null,
                        'load_type' => $detail['load_type'] ?? null,
                        'department' => $detail['department'] ?? null,
                        'campus_name' => $detail['campus_name'] ?? null,
                        'term_label' => $detail['term_label'] ?? null,
                        'period_start' => $detail['period_start'] ?? null,
                        'period_end' => $detail['period_end'] ?? null,
                        'period_range' => $detail['period_range'] ?? null,
                        'employment_type' => $detail['employment_type'] ?? null,
                        'appointment_basis' => $detail['appointment_basis'] ?? null,
                        'total_hours_week' => $detail['total_hours_week'] ?? null,
                        'total_units' => $detail['total_units'] ?? null,
                        'total_hours' => $detail['total_hours'] ?? null,
                        'original_filename' => (string) ($detail['original_filename'] ?? 'faculty-loading.pdf'),
                        'mime_type' => $detail['mime_type'] ?? 'application/pdf',
                        'file_size' => $detail['file_size'] ?? null,
                        'page_count' => $detail['page_count'] ?? null,
                        'parse_status' => $detail['parse_status'] ?? 'pending',
                        'parse_message' => $detail['parse_message'] ?? null,
                        'skolaris_updated_at' => $skolarisUpdatedAt,
                        'skolaris_uploader_name' => $detail['uploaded_by']['full_name'] ?? null,
                        'skolaris_uploader_email' => $detail['uploaded_by']['email'] ?? null,
                        'pulled_at' => now(),
                        'pulled_by_id' => $user->id,
                    ]);

                    if (! $upload->stored_path) {
                        $upload->stored_path = 'pulse/faculty-loads/skolaris/'.$skolarisUploadId.'.pdf';
                    }

                    $upload->save();

                    PulseFacultyLoadItem::query()
                        ->where('upload_id', $upload->upload_id)
                        ->delete();

                    foreach ($detail['items'] ?? [] as $item) {
                        if (! is_array($item)) {
                            continue;
                        }

                        PulseFacultyLoadItem::create([
                            'upload_id' => $upload->upload_id,
                            'row_number' => $item['row_number'] ?? null,
                            'row_type' => $item['row_type'] ?? 'subject',
                            'subject_code' => $item['subject_code'] ?? null,
                            'title' => $item['title'] ?? null,
                            'class_schedule' => $item['class_schedule'] ?? null,
                            'day' => $item['day'] ?? null,
                            'units' => $item['units'] ?? null,
                            'hours' => $item['hours'] ?? null,
                            'hours_paid' => $item['hours_paid'] ?? null,
                            'room' => $item['room'] ?? null,
                            'section' => $item['section'] ?? null,
                            'synchronous_schedule' => $item['synchronous_schedule'] ?? null,
                            'stud_count' => $item['stud_count'] ?? null,
                            'period_start' => $item['period_start'] ?? null,
                            'period_end' => $item['period_end'] ?? null,
                            'period_range' => $item['period_range'] ?? null,
                            'schedule_note' => $item['schedule_note'] ?? null,
                            'sort_order' => $item['sort_order'] ?? 0,
                        ]);
                    }

                    try {
                        $binary = $this->skolaris->downloadUploadedFacultyLoadBinary($skolarisUploadId);
                        Storage::disk(PulseFacultyLoadUpload::diskName())->put($upload->stored_path, $binary);
                    } catch (RuntimeException) {
                        // Metadata/items still useful when PDF download fails.
                    }
                });

                if ($isUpdate) {
                    $updated++;
                } else {
                    $created++;
                }
            }

            $meta = $payload['meta'] ?? [];
            $lastPage = max(1, (int) ($meta['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage && $page <= 100);

        $total = PulseFacultyLoadUpload::query()->count();

        SysLogService::record(
            action: 'create',
            table: 'pulse_faculty_load_uploads',
            description: 'Pulled uploaded faculty loads from Skolaris ('.$created.' new, '.$updated.' updated, '.$skipped.' skipped)',
            userId: $user->id,
            newValues: [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
            ],
        );

        return compact('created', 'updated', 'skipped', 'total');
    }

    public function delete(PulseFacultyLoadUpload $upload): void
    {
        $disk = PulseFacultyLoadUpload::diskName();

        if ($upload->stored_path) {
            Storage::disk($disk)->delete($upload->stored_path);
        }

        $oldValues = $upload->only(['faculty_name', 'original_filename', 'parse_status', 'skolaris_upload_id']);

        $upload->delete();

        SysLogService::record(
            action: 'delete',
            table: 'pulse_faculty_load_uploads',
            recordId: $upload->upload_id,
            oldValues: $oldValues,
            description: 'Removed locally cached uploaded faculty load #'.$upload->upload_id.' (Skolaris source retained)',
        );
    }

    private function parseSkolarisTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
