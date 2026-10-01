<?php

declare(strict_types=1);

use App\Support\EncryptedEnv;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = EncryptedEnv::reveal((string) config('skolaris.pulse_api_key'));
$baseUrl = (string) config('skolaris.pulse_api_base_url');

if ($apiKey === '' || $baseUrl === '') {
    fwrite(STDERR, "Skolaris Pulse API not configured.\n");
    exit(1);
}

$weekStarts = array_slice($argv, 1);
if ($weekStarts === []) {
    $weekStarts = [
        '2026-07-14',
        '2026-07-21',
        '2026-09-01',
        '2026-09-08',
        '2026-09-15',
        '2026-09-22',
        '2026-09-29',
    ];
}

$client = Http::baseUrl($baseUrl)
    ->acceptJson()
    ->timeout(180)
    ->withHeaders(['X-API-Key' => $apiKey]);

$markedDates = [];

foreach ($weekStarts as $weekStart) {
    $response = $client->get('/attendance-checker/dashboard', ['week_start' => $weekStart]);

    if ($response->failed()) {
        echo "Week {$weekStart}: failed HTTP {$response->status()}\n";

        continue;
    }

    $data = $response->json('data') ?? [];
    echo "\nWeek {$weekStart} → ".($data['week_end'] ?? '?')."\n";

    foreach ($data['daily'] ?? [] as $day) {
        $date = (string) ($day['date'] ?? '');
        $scheduled = (int) ($day['scheduled_count'] ?? 0);
        $checked = (int) ($day['checked_count'] ?? 0);

        if ($scheduled === 0) {
            continue;
        }

        $status = is_array($day['status'] ?? null) ? $day['status'] : [];
        $line = sprintf(
            '  %s  schedules=%d  checker_marked=%d  pending=%d  P=%d A=%d L=%d U=%d E=%d M=%d',
            $date,
            $scheduled,
            $checked,
            (int) ($status['pending'] ?? 0),
            (int) ($status['present'] ?? 0),
            (int) ($status['absent'] ?? 0),
            (int) ($status['late'] ?? 0),
            (int) ($status['undertime'] ?? 0),
            (int) ($status['excuse'] ?? 0),
            (int) ($status['meeting'] ?? 0),
        );
        echo $line."\n";

        if ($checked > 0) {
            $markedDates[$date] = $checked;
        }
    }
}

echo "\n=== Dates with at least one checker mark ===\n";
if ($markedDates === []) {
    echo "(none in scanned weeks)\n";
} else {
    ksort($markedDates);
    foreach ($markedDates as $date => $count) {
        echo "{$date}: {$count} marked load(s)\n";
    }
}
