<?php

namespace App\Http\Controllers;

use App\Services\DesktopUpdaterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DesktopUpdaterController extends Controller
{
    public function __construct(private DesktopUpdaterService $updater) {}

    public function status(): JsonResponse
    {
        return response()->json($this->updater->status());
    }

    public function check(): JsonResponse
    {
        $this->updater->checkForUpdates();

        return response()->json([
            'ok' => true,
            'status' => $this->updater->status(),
        ]);
    }

    public function install(): RedirectResponse|JsonResponse
    {
        if (! $this->updater->enabled()) {
            if (request()->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Updater is disabled.'], 422);
            }

            return back()->withErrors(['updater' => 'Updater is disabled.']);
        }

        $status = $this->updater->status();
        $pendingVersion = is_array($status['pending'] ?? null)
            ? (string) ($status['pending']['version'] ?? '')
            : '';

        if ($pendingVersion === '' || ! $this->updater->isNewerThanCurrent($pendingVersion)) {
            $this->updater->clear();

            if (request()->expectsJson()) {
                return response()->json(['ok' => true, 'skipped' => true, 'message' => 'Already up to date.']);
            }

            return back()->with('status', 'Already on the latest version.');
        }

        try {
            $this->updater->quitAndInstall();
        } catch (\Throwable $e) {
            if (request()->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
            }

            return back()->withErrors(['updater' => 'Could not install the update.']);
        }

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('status', 'Installing update… the app will restart.');
    }
}
