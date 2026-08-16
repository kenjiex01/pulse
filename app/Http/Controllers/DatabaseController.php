<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use App\Services\DesktopCloudBackupService;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DatabaseController extends Controller
{
    public function index(): View
    {
        SysLogService::record(
            action: 'read',
            table: 'database_backup',
            description: 'Opened database backup page',
        );

        return view('database.index', [
            'driver' => config('database.default'),
            'cloudBackup' => app(DesktopCloudBackupService::class)->status(),
        ]);
    }

    public function resetCloudBackupMarker(DesktopCloudBackupService $backupService): RedirectResponse
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403);
        }

        $cleared = $backupService->clearTodayMarker();

        SysLogService::record(
            action: 'delete',
            table: 'database_backup',
            description: $cleared
                ? 'Cleared today\'s cloud backup marker so upload can retry'
                : 'Cloud backup marker reset requested but no today marker was found',
        );

        return redirect()
            ->route('database.index')
            ->with($cleared ? 'success' : 'info', $cleared
                ? 'Today\'s cloud backup marker cleared. The app can upload again after the scheduled time.'
                : 'No today cloud backup marker was found.');
    }

    public function uploadSql(Request $request, DatabaseBackupService $backupService): RedirectResponse
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'sql_file' => ['required', 'file', 'max:'.config('uploads.sql_restore_max_kb', 262144)],
            'confirm_replace' => ['accepted'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $uploadedFile */
        $uploadedFile = $validated['sql_file'];
        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());

        if ($extension !== 'sql') {
            return redirect()
                ->route('database.index')
                ->with('error', 'Please upload a .sql file.');
        }

        $importDir = storage_path('app/backups/imports');
        File::ensureDirectoryExists($importDir);

        $filename = 'restore-'.now()->format('Y-m-d-H-i-s').'-'.uniqid('', true).'.sql';
        $importPath = $importDir.DIRECTORY_SEPARATOR.$filename;

        try {
            $uploadedFile->move($importDir, $filename);

            $result = $backupService->restoreFromSqlFile($importPath);

            $backupService->finalizeRestoredDatabase();

            try {
                SysLogService::record(
                    action: 'update',
                    table: 'database_backup',
                    description: 'Restored database from uploaded SQL file ('.number_format($result['bytes']).' bytes). Safety copy: '.$result['safety_backup'],
                );
            } catch (\Throwable) {
                // Avoid 500 if logging fails immediately after a full DB replace.
            }

            return redirect()->route('database.index', [
                'restore' => 'ok',
                'backup' => $result['safety_backup'],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            try {
                SysLogService::record(
                    action: 'update',
                    table: 'database_backup',
                    description: 'Failed SQL database restore: '.$exception->getMessage(),
                );
            } catch (\Throwable) {
                // Logging may fail if the database was left mid-restore.
            }

            return redirect()->route('database.index', [
                'restore' => 'fail',
                'reason' => Str::limit($exception->getMessage(), 400),
            ]);
        } finally {
            if (isset($importPath) && File::exists($importPath)) {
                File::delete($importPath);
            }
        }
    }
}