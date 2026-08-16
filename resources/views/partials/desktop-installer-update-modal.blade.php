@php
    /** @var array{current_version: string, latest_version: string, platform: string, filename: string, download_url: string}|null $desktopInstallerUpdate */
    $desktopInstallerUpdate = $desktopInstallerUpdate ?? null;
@endphp

@if (! empty($desktopInstallerUpdate))
    <div
        id="desktop-installer-update-modal"
        class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/70 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="desktop-installer-update-title"
        data-desktop-installer-update
        data-desktop-installer-force
    >
        <div class="w-full max-w-md rounded-xl border border-amber-200 bg-white p-6 shadow-xl">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <h2 id="desktop-installer-update-title" class="text-lg font-semibold text-gray-900">Update required</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        You must install the latest Pulse desktop app before continuing. Installed
                        <span class="font-medium text-gray-900">v{{ $desktopInstallerUpdate['current_version'] }}</span>
                        → required
                        <span class="font-medium text-gray-900">v{{ $desktopInstallerUpdate['latest_version'] }}</span>.
                    </p>
                    <p class="mt-2 text-xs text-gray-500">
                        Download <code class="rounded bg-gray-100 px-1">{{ $desktopInstallerUpdate['filename'] }}</code>,
                        quit Pulse, then run the installer.
                    </p>
                    <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700">
                        Your database on this computer is kept. After you open the new version, Pulse applies pending
                        migrations and module updates automatically — employee/payroll data is not wiped.
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <a
                    href="{{ $desktopInstallerUpdate['download_url'] }}"
                    class="btn-primary inline-flex w-full items-center justify-center"
                    data-desktop-installer-download
                    data-desktop-installer-filename="{{ $desktopInstallerUpdate['filename'] }}"
                    data-no-loader
                >
                    Download update
                </a>
                <p class="mt-2 text-center text-xs text-gray-500" data-desktop-installer-download-status hidden></p>
            </div>
        </div>
    </div>
@endif
