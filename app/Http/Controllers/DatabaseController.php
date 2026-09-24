<?php

namespace App\Http\Controllers;

use App\Database\People360LanConnection;
use App\Services\DatabaseBackupService;
use App\Services\DesktopCloudBackupService;
use App\Services\People360LanBeacon;
use App\Services\People360LanClient;
use App\Services\SysLogService;
use App\Support\People360LanProtocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class DatabaseController extends Controller
{
    public function index(): View
    {
        SysLogService::record(
            action: 'read',
            table: 'database_backup',
            description: 'Opened database backup page',
        );

        $peers = [];
        $lanError = null;

        try {
            app(People360LanBeacon::class)->ensureRunning();
            $peers = app(People360LanClient::class)->discover();
        } catch (\Throwable $exception) {
            $lanError = $exception->getMessage();
        }

        return view('database.index', [
            'driver' => config('database.default'),
            'cloudBackup' => app(DesktopCloudBackupService::class)->status(),
            'lanPeers' => $peers,
            'lanError' => $lanError,
            'lanConnection' => session('people360_lan_database'),
        ]);
    }

    public function connectLan(Request $request, People360LanClient $client): RedirectResponse
    {
        if (is_array(session('people360_lan_database'))) {
            return redirect()
                ->route('database.index')
                ->with('error', 'Disconnect from the current computer before connecting to another.');
        }

        $validated = $request->validate([
            'machine_id' => ['required', 'regex:/^[a-f0-9]{32}$/'],
            'hostname' => ['required', 'string', 'max:255'],
            'address' => ['required', 'ip'],
            'http_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'version' => ['nullable', 'string', 'max:40'],
        ]);

        if (! People360LanProtocol::isPrivateIpv4($validated['address'])) {
            return redirect()
                ->route('database.index')
                ->with('error', 'That computer is not on this network.');
        }

        try {
            $client->ping($validated['address'], (int) $validated['http_port']);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('database.index')
                ->with('error', $exception->getMessage());
        }

        session([
            'people360_lan_database' => [
                'machine_id' => $validated['machine_id'],
                'hostname' => $validated['hostname'],
                'address' => $validated['address'],
                'http_port' => (int) $validated['http_port'],
                'version' => $validated['version'] ?? '',
            ],
        ]);

        SysLogService::record(
            action: 'read',
            table: 'database_backup',
            description: 'Connected to People360 database on '.$validated['hostname'].' ('.$validated['address'].')',
        );

        return redirect()
            ->route('database.index')
            ->with('success', 'Connected to '.$validated['hostname'].'. People360 is now using that computer\'s database.');
    }

    public function disconnectLan(People360LanClient $client): RedirectResponse
    {
        $remote = session('people360_lan_database');

        if (! is_array($remote)) {
            return redirect()->route('database.index');
        }

        $client->release((string) $remote['address'], (int) $remote['http_port']);
        session()->forget('people360_lan_database');
        People360LanConnection::useLocal();

        SysLogService::record(
            action: 'update',
            table: 'database_backup',
            description: 'Disconnected from People360 database on '.($remote['hostname'] ?? 'another computer'),
        );

        return redirect()
            ->route('database.index')
            ->with('success', 'Disconnected from '.($remote['hostname'] ?? 'that computer').'. This computer is using its own database again.');
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