<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Throwable;

class LibreOfficeDocumentConverter
{
    /**
     * Convert an office document to PDF at $destinationPdfPath.
     * Returns the destination path on success, otherwise null.
     */
    public function convertToPdf(string $sourceAbsolutePath, string $destinationPdfPath): ?string
    {
        $binary = $this->binaryPath();
        if ($binary === null || ! is_file($sourceAbsolutePath)) {
            return null;
        }

        $sourceAbsolutePath = realpath($sourceAbsolutePath) ?: $sourceAbsolutePath;
        $workDir = storage_path('app/private/libreoffice-work/'.uniqid('lo_', true));
        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            return null;
        }

        $profileDir = $workDir.'/profile';
        $extension = strtolower((string) pathinfo($sourceAbsolutePath, PATHINFO_EXTENSION));
        $sourceCopy = $workDir.'/source'.($extension !== '' ? '.'.$extension : '.bin');

        try {
            if (! @copy($sourceAbsolutePath, $sourceCopy)) {
                return null;
            }

            $result = Process::timeout((int) config('document_preview.libreoffice_timeout', 90))
                ->env([
                    'HOME' => $workDir,
                    'TMPDIR' => $workDir,
                ])
                ->run([
                    $binary,
                    '--headless',
                    '--nologo',
                    '--nofirststartwizard',
                    '--norestore',
                    '-env:UserInstallation=file://'.$profileDir,
                    '--convert-to',
                    'pdf',
                    '--outdir',
                    $workDir,
                    $sourceCopy,
                ]);

            if (! $result->successful()) {
                report(new \RuntimeException(
                    'LibreOffice convert failed: '.$result->errorOutput().$result->output()
                ));

                return null;
            }

            $converted = $this->findConvertedPdf($workDir);
            if ($converted === null) {
                return null;
            }

            $outDir = dirname($destinationPdfPath);
            if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
                return null;
            }

            if (! @copy($converted, $destinationPdfPath)) {
                return null;
            }

            return is_file($destinationPdfPath) && filesize($destinationPdfPath) > 0
                ? $destinationPdfPath
                : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    public function isAvailable(): bool
    {
        return $this->binaryPath() !== null;
    }

    public function binaryPath(): ?string
    {
        $configured = trim((string) config('document_preview.libreoffice_path', ''));
        if ($configured !== '' && $this->isUsableBinary($configured)) {
            return $configured;
        }

        foreach ($this->candidateBinaries() as $candidate) {
            if ($this->isUsableBinary($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidateBinaries(): array
    {
        $runtimeRoot = storage_path('app/'.trim((string) config('document_preview.runtime_directory', 'libreoffice-runtime'), '/'));

        $candidates = [
            $runtimeRoot.'/LibreOffice.app/Contents/MacOS/soffice',
            $runtimeRoot.'/Contents/MacOS/soffice',
            $runtimeRoot.'/program/soffice.exe',
            $runtimeRoot.'/LibreOffice/program/soffice.exe',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            '/usr/local/bin/soffice',
            '/opt/homebrew/bin/soffice',
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ];

        try {
            if (PHP_OS_FAMILY !== 'Windows') {
                $which = Process::timeout(5)->run(['/bin/zsh', '-lc', 'which soffice || true']);
                if ($which->successful()) {
                    $path = trim($which->output());
                    if ($path !== '' && $path !== 'soffice not found') {
                        array_unshift($candidates, $path);
                    }
                }
            }
        } catch (Throwable) {
            // ignore
        }

        return $candidates;
    }

    private function isUsableBinary(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return is_file($path);
        }

        return is_file($path) && is_executable($path);
    }

    private function findConvertedPdf(string $workDir): ?string
    {
        $files = glob($workDir.'/*.pdf') ?: [];
        foreach ($files as $file) {
            if (is_file($file) && filesize($file) > 0) {
                return $file;
            }
        }

        return null;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}
