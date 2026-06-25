<?php

namespace Database\Seeders;

use App\Models\TimeCaptureFormat;
use App\Support\TimeCaptureFormat as TimeCaptureFormatSupport;
use Illuminate\Database\Seeder;

class TimeCaptureFormatSeeder extends Seeder
{
    public function run(): void
    {
        $payload = array_merge(
            config('time_capturing_settings.biometric_defaults', []),
            [
                'device_name' => 'BIOMETRIC',
                'description' => 'Biometric device — separate row per punch (indicator 1=in, 0=out)',
                'reason_enabled' => false,
                'time_out_enabled' => false,
                'custom_fields' => [],
            ],
        );

        $header = TimeCaptureFormatSupport::headerPayload($payload);
        $format = TimeCaptureFormat::query()->updateOrCreate(
            ['device_name' => $header['device_name']],
            $header,
        );

        TimeCaptureFormatSupport::syncFields($format, $payload);
    }
}
