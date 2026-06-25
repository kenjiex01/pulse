<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use App\Services\SysLogService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function __invoke(DatabaseBackupService $backupService): BinaryFileResponse|Response
    {
        $user = auth()->user();

        if (! $user?->isSuperAdmin()) {
            abort(403);
        }

        try {
            $backup = $backupService->create();
            $backupService->pruneOldCopies();
        } catch (\Throwable $exception) {
            report($exception);

            return response('Database backup failed: '.$exception->getMessage(), 500);
        }

        SysLogService::record(
            action: 'read',
            table: 'database_backup',
            description: 'Downloaded database backup ('.$backup['driver'].', '.number_format($backup['size']).' bytes)',
        );

        return $backupService->downloadResponse($backup);
    }
}
