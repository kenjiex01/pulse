<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;

class LibreOfficeRuntimeInstaller
{
    public function __construct(
        private readonly LibreOfficeDocumentConverter $converter,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('document_preview.runtime_enabled', true);
    }

    public function isInstalled(): bool
    {
        $binary = $this->installedBinaryPath();

        return $binary !== null && $this->isUsableBinary($binary);
    }

    public function isAvailable(): bool
    {
        return $this->converter->isAvailable();
    }

    /**
     * @return array{
     *     enabled: bool,
     *     available: bool,
     *     installed_runtime: bool,
     *     platform: string,
     *     version: string,
     *     download_url: string|null,
     *     binary_path: string|null,
     *     approximate_size_mb: int
     * }
     */
    public function status(): array
    {
        $platform = $this->platformKey();

        return [
            'enabled' => $this->isEnabled(),
            'available' => $this->isAvailable(),
            'installed_runtime' => $this->isInstalled(),
            'platform' => $platform,
            'version' => (string) config('document_preview.runtime_version', '26.2.5'),
            'download_url' => $this->downloadUrl($platform),
            'binary_path' => $this->converter->binaryPath(),
            'approximate_size_mb' => $platform === 'win-x64' ? 360 : 290,
        ];
    }

    /**
     * Download + extract LibreOffice into app storage. Returns binary path.
     */
    public function install(): string
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('LibreOffice runtime download is disabled.');
        }

        if ($this->isAvailable()) {
            $binary = $this->converter->binaryPath();
            if ($binary !== null) {
                return $binary;
            }
        }

        $platform = $this->platformKey();
        $url = $this->downloadUrl($platform);
        if ($url === null) {
            throw new \RuntimeException('No LibreOffice download is configured for this platform.');
        }

        @set_time_limit(0);

        $runtimeRoot = $this->runtimeRoot();
        $downloadDir = storage_path('app/private/libreoffice-downloads');
        $this->ensureDirectory($runtimeRoot);
        $this->ensureDirectory($downloadDir);

        $archivePath = $downloadDir.'/'.basename(parse_url($url, PHP_URL_PATH) ?: 'libreoffice-package');

        $this->download($url, $archivePath);

        if ($platform === 'win-x64') {
            $this->extractWindowsMsi($archivePath, $runtimeRoot);
        } else {
            $this->extractMacDmg($archivePath, $runtimeRoot);
        }

        @unlink($archivePath);

        $binary = $this->installedBinaryPath();
        if ($binary === null || ! $this->isUsableBinary($binary)) {
            throw new \RuntimeException('LibreOffice runtime installed but soffice binary was not found.');
        }

        @chmod($binary, 0755);

        return $binary;
    }

    public function installedBinaryPath(): ?string
    {
        $root = $this->runtimeRoot();

        $candidates = [
            $root.'/LibreOffice.app/Contents/MacOS/soffice',
            $root.'/Contents/MacOS/soffice',
            $root.'/program/soffice.exe',
            $root.'/LibreOffice/program/soffice.exe',
        ];

        foreach ($candidates as $candidate) {
            if ($this->isUsableBinary($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function runtimeRoot(): string
    {
        $relative = trim((string) config('document_preview.runtime_directory', 'libreoffice-runtime'), '/');

        return storage_path('app/'.$relative);
    }

    public function platformKey(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'win-x64';
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $machine = strtolower(php_uname('m'));

            if (str_contains($machine, 'arm') || str_contains($machine, 'aarch')) {
                return 'mac-arm64';
            }

            return 'mac-x64';
        }

        return 'linux-x64';
    }

    public function downloadUrl(string $platform): ?string
    {
        $template = (string) (config('document_preview.runtime_downloads.'.$platform) ?? '');
        if ($template === '') {
            return null;
        }

        $version = (string) config('document_preview.runtime_version', '26.2.5');

        return str_replace('{version}', $version, $template);
    }

    private function download(string $url, string $destination): void
    {
        if (is_file($destination) && filesize($destination) > 10_000_000) {
            return;
        }

        $response = Http::timeout((int) config('document_preview.libreoffice_timeout', 90) * 10)
            ->withOptions(['sink' => $destination])
            ->get($url);

        if (! $response->successful() || ! is_file($destination) || filesize($destination) < 1_000_000) {
            @unlink($destination);
            throw new \RuntimeException('Failed to download LibreOffice runtime (HTTP '.$response->status().').');
        }
    }

    private function extractMacDmg(string $dmgPath, string $runtimeRoot): void
    {
        $mountPoint = storage_path('app/private/libreoffice-mount/'.uniqid('lo_', true));
        $this->ensureDirectory($mountPoint);

        try {
            $attach = Process::timeout(120)->run([
                'hdiutil', 'attach', '-nobrowse', '-readonly', '-quiet', $dmgPath, '-mountpoint', $mountPoint,
            ]);
            if (! $attach->successful()) {
                throw new \RuntimeException('Unable to mount LibreOffice DMG: '.$attach->errorOutput());
            }

            $appPath = $this->findMacApp($mountPoint);
            if ($appPath === null) {
                throw new \RuntimeException('LibreOffice.app not found inside DMG.');
            }

            $this->deletePath($runtimeRoot.'/LibreOffice.app');

            $copy = Process::timeout(300)->run(['cp', '-R', $appPath, $runtimeRoot.'/LibreOffice.app']);
            if (! $copy->successful()) {
                throw new \RuntimeException('Unable to copy LibreOffice.app: '.$copy->errorOutput());
            }

            Process::timeout(120)->run([
                'xattr', '-dr', 'com.apple.quarantine', $runtimeRoot.'/LibreOffice.app',
            ]);
        } finally {
            Process::timeout(60)->run(['hdiutil', 'detach', $mountPoint, '-quiet']);
            @rmdir($mountPoint);
        }
    }

    private function extractWindowsMsi(string $msiPath, string $runtimeRoot): void
    {
        $this->deletePath($runtimeRoot);
        $this->ensureDirectory($runtimeRoot);

        $result = Process::timeout(600)->run([
            'msiexec',
            '/a',
            $msiPath,
            '/qn',
            'TARGETDIR='.$runtimeRoot,
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('Unable to extract LibreOffice MSI: '.$result->errorOutput().$result->output());
        }
    }

    private function findMacApp(string $mountPoint): ?string
    {
        $direct = $mountPoint.'/LibreOffice.app';
        if (is_dir($direct)) {
            return $direct;
        }

        $matches = glob($mountPoint.'/*.app') ?: [];

        return $matches[0] ?? null;
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new \RuntimeException('Unable to create directory: '.$path);
        }
    }

    private function deletePath(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
        } catch (Throwable) {
            // ignore cleanup races
        }

        @rmdir($path);
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
}
