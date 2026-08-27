{{-- Full-screen blocker while an update downloads / installs. App is unusable until restart. --}}
@php
    $updater = $desktopUpdater ?? ['enabled' => false];
    $updaterDownloading = is_array($updater['downloading'] ?? null) ? $updater['downloading'] : null;
    $updaterInstalling = is_array($updater['installing'] ?? null) ? $updater['installing'] : null;
    $updaterPending = is_array($updater['pending'] ?? null) ? $updater['pending'] : null;
    $updaterBlocking = ! empty($updater['enabled']) && ($updaterDownloading || $updaterInstalling || (! empty($updater['force_install']) && $updaterPending));
    $initialPercent = 0;
    $initialPhase = 'Downloading update';
    $initialVersion = '';
    if ($updaterInstalling) {
        $initialPercent = 100;
        $initialPhase = 'Installing update — app will reopen…';
        $initialVersion = (string) ($updaterInstalling['version'] ?? '');
    } elseif ($updaterDownloading) {
        $initialPercent = (int) round((float) ($updaterDownloading['percent'] ?? 0));
        $initialPhase = 'Downloading update';
        $initialVersion = (string) ($updaterDownloading['version'] ?? '');
    } elseif ($updaterPending) {
        $initialPercent = 100;
        $initialPhase = 'Installing update — app will reopen…';
        $initialVersion = (string) ($updaterPending['version'] ?? '');
    }
@endphp

@if (! empty($updater['enabled']))
    <div
        id="desktop-updater-overlay"
        class="{{ $updaterBlocking ? 'fixed inset-0 z-[10000] flex' : 'fixed inset-0 z-[10000] hidden' }} items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="desktop-updater-title"
        aria-describedby="desktop-updater-desc"
        data-status-url="{{ route('desktop.updater.status') }}"
        data-check-url="{{ route('desktop.updater.check') }}"
        data-install-url="{{ route('desktop.updater.install') }}"
        data-force-install="{{ ! empty($updater['force_install']) ? '1' : '0' }}"
        data-csrf="{{ csrf_token() }}"
    >
        <div class="w-full max-w-md rounded-2xl border border-white/10 bg-white px-8 py-8 text-center shadow-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#0B318F]">{{ config('app.name') }}</p>
            <h2 id="desktop-updater-title" class="mt-3 text-xl font-semibold text-gray-900">Updating the app</h2>
            <p id="desktop-updater-desc" class="mt-2 text-sm text-slate-600">
                No action needed. The update downloads and installs by itself. The app will reopen when ready.
            </p>

            <p id="desktop-updater-phase" class="mt-6 text-sm font-medium text-slate-800">{{ $initialPhase }}</p>
            <p id="desktop-updater-version" class="mt-1 text-xs text-slate-500">
                @if ($initialVersion !== '')
                    Version {{ $initialVersion }}
                @endif
            </p>

            <p id="desktop-updater-percent" class="mt-6 text-5xl font-semibold tabular-nums tracking-tight text-[#0B318F]">
                {{ $initialPercent }}%
            </p>

            <div class="mt-5 h-3 w-full overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                <div
                    id="desktop-updater-bar"
                    class="h-full rounded-full bg-[#0B318F] transition-[width] duration-300 ease-out"
                    style="width: {{ $initialPercent }}%"
                ></div>
            </div>

            <p class="mt-5 text-xs text-slate-500">Do not close or power off this computer. The app will reopen by itself when the update is done.</p>
        </div>
    </div>

    <script>
        (function () {
            const overlay = document.getElementById('desktop-updater-overlay');
            if (!overlay) return;

            const phaseEl = document.getElementById('desktop-updater-phase');
            const versionEl = document.getElementById('desktop-updater-version');
            const percentEl = document.getElementById('desktop-updater-percent');
            const barEl = document.getElementById('desktop-updater-bar');
            const statusUrl = overlay.dataset.statusUrl;
            const checkUrl = overlay.dataset.checkUrl;
            const installUrl = overlay.dataset.installUrl;
            const csrf = overlay.dataset.csrf || '';

            let installTriggered = false;
            let pollTimer = null;
            let active = overlay.classList.contains('flex') && !overlay.classList.contains('hidden');

            function setPercent(pct) {
                const n = Math.max(0, Math.min(100, Math.round(Number(pct) || 0)));
                percentEl.textContent = n + '%';
                barEl.style.width = n + '%';
                return n;
            }

            function showOverlay(phase, version, percent) {
                active = true;
                overlay.classList.remove('hidden');
                overlay.classList.add('flex');
                document.documentElement.style.overflow = 'hidden';
                phaseEl.textContent = phase;
                versionEl.textContent = version ? ('Version ' + version) : '';
                setPercent(percent);
                schedulePoll(1500);
            }

            function hideOverlay() {
                active = false;
                overlay.classList.add('hidden');
                overlay.classList.remove('flex');
                document.documentElement.style.overflow = '';
                schedulePoll(10000);
            }

            function triggerInstall() {
                if (installTriggered || !installUrl) return;
                installTriggered = true;
                showOverlay('Installing update — app will reopen…', versionEl.textContent.replace(/^Version\s*/, '') || '', 100);

                fetch(installUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                }).catch(function () {
                    installTriggered = false;
                });
            }

            function applyStatus(status) {
                if (!status || !status.enabled) {
                    hideOverlay();
                    return;
                }

                const forceInstall = status.force_install !== false;

                if (status.installing && status.installing.version) {
                    showOverlay('Installing update — app will reopen…', status.installing.version, status.installing.percent != null ? status.installing.percent : 100);
                    return;
                }

                if (status.pending && status.pending.version) {
                    const v = status.pending.version;
                    if (forceInstall) {
                        showOverlay('Installing update — app will reopen…', v, 100);
                        triggerInstall();
                    } else {
                        hideOverlay();
                    }
                    return;
                }

                if (status.downloading) {
                    showOverlay('Downloading update', status.downloading.version || '', status.downloading.percent != null ? status.downloading.percent : 0);
                    return;
                }

                hideOverlay();
            }

            function refreshStatus() {
                fetch(statusUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.json(); })
                    .then(applyStatus)
                    .catch(function () {});
            }

            function triggerCheck() {
                if (!checkUrl) return;
                fetch(checkUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                }).catch(function () {});
            }

            function schedulePoll(ms) {
                if (pollTimer) clearInterval(pollTimer);
                pollTimer = window.setInterval(refreshStatus, ms);
            }

            window.addEventListener('keydown', function (e) {
                if (!active) return;
                e.preventDefault();
                e.stopPropagation();
            }, true);

            triggerCheck();
            refreshStatus();
            schedulePoll(active ? 1500 : 10000);
            if (active) {
                document.documentElement.style.overflow = 'hidden';
            }
            window.setInterval(triggerCheck, 5 * 60 * 1000);
        })();
    </script>
@endif
