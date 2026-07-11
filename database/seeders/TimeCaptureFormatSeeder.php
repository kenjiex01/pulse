<?php

namespace Database\Seeders;

use App\Models\TimeCaptureFormat;
use App\Support\TimeCaptureFormat as TimeCaptureFormatSupport;
use Illuminate\Database\Seeder;

class TimeCaptureFormatSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            array_merge(
                config('time_capturing_settings.biometric_defaults', []),
                [
                    'device_name' => 'BIOMETRIC',
                    'description' => 'Biometric device — separate row per punch (indicator 1=in, 0=out)',
                    'reason_enabled' => false,
                    'time_out_enabled' => false,
                    'custom_fields' => [],
                ],
            ),
            [
                'device_name' => 'STAFF',
                'description' => 'Staff manual upload — employee number, date, time in and time out per row',
                'employee_id_type' => 'employee_number',
                'employee_id_column' => 1,
                'date_type' => 'actual_date',
                'date_column' => 2,
                'time_in_type' => 'time_in',
                'time_in_column' => 3,
                'same_column_indicator' => false,
                'time_out_enabled' => true,
                'time_out_column' => 4,
                'reason_enabled' => false,
                'custom_fields' => [],
            ],
        ];

        foreach ($formats as $payload) {
            $header = TimeCaptureFormatSupport::headerPayload($payload);
            $format = TimeCaptureFormat::withTrashed()->updateOrCreate(
                ['device_name' => $header['device_name']],
                $header,
            );

            if ($format->trashed()) {
                $format->restore();
            }

            TimeCaptureFormatSupport::syncFields($format, $payload);
        }
    }
}
