<?php

namespace App\Listeners;

use App\Services\DesktopUpdaterService;
use Native\Laravel\Events\AutoUpdater\DownloadProgress;
use Native\Laravel\Events\AutoUpdater\Error;
use Native\Laravel\Events\AutoUpdater\UpdateAvailable;
use Native\Laravel\Events\AutoUpdater\UpdateDownloaded;
use Native\Laravel\Events\AutoUpdater\UpdateNotAvailable;

class HandleDesktopUpdaterEvents
{
    public function __construct(private DesktopUpdaterService $updater) {}

    public function handleUpdateAvailable(UpdateAvailable $event): void
    {
        $this->updater->markAvailable((string) $event->version);
    }

    public function handleDownloadProgress(DownloadProgress $event): void
    {
        $this->updater->markProgress((float) $event->percent);
    }

    public function handleUpdateDownloaded(UpdateDownloaded $event): void
    {
        $this->updater->markDownloaded(
            (string) $event->version,
            $event->releaseName !== null ? (string) $event->releaseName : null,
        );
    }

    public function handleUpdateNotAvailable(UpdateNotAvailable $event): void
    {
        cache()->forget(DesktopUpdaterService::CACHE_DOWNLOADING);
    }

    public function handleError(Error $event): void
    {
        cache()->forget(DesktopUpdaterService::CACHE_DOWNLOADING);
        report(new \RuntimeException('Desktop updater error: '.($event->message ?? 'unknown')));
    }
}
