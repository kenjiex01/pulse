<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class BiometricS3PullSettings
{
    private const PATH = 'settings/biometric-s3-auto-pull.json';

    public function isAutoPullEnabled(): bool
    {
        return (bool) ($this->read()['enabled'] ?? false);
    }

    public function setAutoPullEnabled(bool $enabled): void
    {
        $data = $this->read();
        $data['enabled'] = $enabled;
        $this->write($data);
    }

    public function lastRunAt(): ?string
    {
        $value = $this->read()['last_run_at'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function markRunFinished(): void
    {
        $data = $this->read();
        $data['last_run_at'] = now()->toIso8601String();
        $this->write($data);
    }

    public function shouldRunNow(): bool
    {
        if (! $this->isAutoPullEnabled()) {
            return false;
        }

        $lastRun = $this->lastRunAt();
        if ($lastRun === null) {
            return true;
        }

        $interval = max(1, (int) config('biometric_logs.auto_pull.interval_minutes', 5));

        return now()->diffInMinutes(\Carbon\Carbon::parse($lastRun), true) >= $interval;
    }

    /**
     * @return array{enabled?: bool, last_run_at?: string|null}
     */
    private function read(): array
    {
        $path = storage_path('app/'.self::PATH);
        if (! is_file($path)) {
            return ['enabled' => false, 'last_run_at' => null];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : ['enabled' => false, 'last_run_at' => null];
    }

    /**
     * @param  array{enabled?: bool, last_run_at?: string|null}  $data
     */
    private function write(array $data): void
    {
        $path = storage_path('app/'.self::PATH);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'enabled' => (bool) ($data['enabled'] ?? false),
            'last_run_at' => $data['last_run_at'] ?? null,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
