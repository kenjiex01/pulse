<?php

namespace App\Http\Controllers;

use App\Services\DesktopInstallerUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DesktopInstallerUpdateController extends Controller
{
    public function download(Request $request, DesktopInstallerUpdateService $updater): JsonResponse|RedirectResponse
    {
        abort_unless($updater->isEnabled(), 404);

        $url = $updater->temporaryDownloadUrl();
        $check = $updater->checkIfNeeded();
        $filename = is_array($check) ? (string) ($check['filename'] ?? 'People360-setup') : 'People360-setup';

        if ($url === null) {
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No newer installer is available for download right now.',
                ], 404);
            }

            return redirect()
                ->to(auth()->check() ? route('dashboard') : route('login'))
                ->with('error', 'No newer installer is available for download right now.');
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'url' => $url,
                'filename' => $filename,
            ]);
        }

        return redirect()->away($url);
    }
}
