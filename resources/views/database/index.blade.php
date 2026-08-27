@extends('layouts.app')

@section('title', 'Database — '.config('app.name'))

@section('content')
    <div class="space-y-6">
        @include('partials.flash')

        @if (request('restore') === 'ok')
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                Database restored from SQL file.
                @if (request('backup'))
                    A safety backup was saved as <strong>{{ request('backup') }}</strong>.
                @endif
                Refresh the app if anything looks stale.
            </div>
        @elseif (request('restore') === 'fail')
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                SQL restore failed: {{ request('reason') }}
            </div>
        @endif

        @include('partials.page-header', [
            'title' => 'Database',
            'description' => 'Download a SQL backup or restore the database from an uploaded .sql file.',
        ])

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                <div class="inline-flex rounded-2xl bg-gradient-to-br from-[#0f766e] to-[#14b8a6] p-4 text-white">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7c-2 0-3 1-3 3z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v4m6-4v4M9 17v4m6-4v4"/>
                    </svg>
                </div>

                <div class="min-w-0 flex-1 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold text-[#0B318F]">Download SQL Backup</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Exports your business data as a <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">.sql</code> file.
                            Menu modules and role navigation are <strong>not</strong> included — the app version you install supplies those.
                            Use this before reinstalling the desktop app or when moving data to another machine.
                        </p>
                    </div>

                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-xl border border-gray-100 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Driver</dt>
                            <dd class="mt-1 font-medium text-[#0B318F]">{{ strtoupper($driver) }}</dd>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Format</dt>
                            <dd class="mt-1 font-medium text-[#0B318F]">SQL dump</dd>
                        </div>
                    </dl>

                    <a href="{{ route('database.download') }}" class="btn-primary inline-flex items-center gap-2" data-no-loader>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download Backup
                    </a>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-[#0B318F]">Daily Cloud Backup</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Automatically gzips the full database and uploads to S3 once per day after the scheduled time
                        (browser and desktop). Files go under year then month folders
                        (<code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">payroll-backups/YYYY/MM/</code>).
                        The filename includes this computer's name so backups from different machines stay distinct.
                        On desktop, NativePHP also runs the scheduler every minute while the app is open.
                    </p>
                </div>

                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-100 bg-slate-50 px-4 py-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Status</dt>
                        <dd class="mt-1 font-medium text-[#0B318F]">
                            @if (! $cloudBackup['enabled'])
                                Disabled
                            @elseif (! $cloudBackup['configured'])
                                <span class="text-amber-700">Missing S3 credentials</span>
                            @elseif ($cloudBackup['uploaded_today'])
                                <span class="text-green-700">Uploaded today</span>
                            @elseif ($cloudBackup['ready_to_run'])
                                <span class="text-amber-700">Ready — will upload on this or the next page load</span>
                            @else
                                <span class="text-amber-700">Pending after {{ $cloudBackup['schedule_label'] }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-slate-50 px-4 py-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Schedule</dt>
                        <dd class="mt-1 font-medium text-[#0B318F]">{{ $cloudBackup['schedule_label'] }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-slate-50 px-4 py-3 sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Last Cloud Upload</dt>
                        <dd class="mt-1 font-medium text-[#0B318F]">
                            @if ($cloudBackup['last_uploaded_at'])
                                {{ \Carbon\Carbon::parse($cloudBackup['last_uploaded_at'])->timezone(config('backup.cloud.timezone', 'Asia/Manila'))->format('M j, Y g:i A') }}
                                @if ($cloudBackup['last_s3_key'])
                                    <span class="mt-1 block text-xs font-normal text-gray-500">{{ $cloudBackup['last_s3_key'] }}</span>
                                @endif
                            @else
                                No cloud upload recorded yet
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($cloudBackup['enabled'] && ! $cloudBackup['configured'])
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Cloud backup credentials were not packaged correctly. Reinstall with a build that keeps
                        <code class="rounded bg-white px-1">DB_BACKUP_S3_SECRET</code> in the desktop <code class="rounded bg-white px-1">.env</code>.
                    </p>
                @endif

                @if ($cloudBackup['enabled'] && $cloudBackup['configured'] && $cloudBackup['uploaded_today'])
                    <form method="POST" action="{{ route('database.cloud-backup.reset-marker') }}" class="pt-2">
                        @csrf
                        <button type="submit" class="btn-secondary text-sm">
                            Clear today's backup marker
                        </button>
                        <p class="mt-2 text-xs text-gray-500">
                            Use when today's marker is set but no file reached S3 — allows the scheduled upload to retry.
                        </p>
                    </form>
                @endif
            </div>
        </div>

        <div
            id="database-sql-upload-panel"
            class="rounded-2xl border border-amber-300 bg-amber-50/40 p-6 shadow-sm sm:p-8"
            data-database-sql-upload
        >
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-amber-900">Restore SQL Backup</h2>
                    <p class="mt-1 text-sm text-amber-900/80">
                        Replaces the current <strong>{{ strtoupper($driver) }}</strong> database with the uploaded <code class="rounded bg-white px-1.5 py-0.5 text-xs">.sql</code> file.
                        @if (in_array($driver, ['mysql', 'mariadb'], true))
                            MySQL / phpMyAdmin dumps import directly. <strong>People360 desktop</strong> backups (SQLite, often starting with <code class="rounded bg-white px-1 text-xs">PRAGMA</code>) are converted automatically for browser dev.
                        @else
                            MySQL / phpMyAdmin dumps are automatically converted to the desktop SQLite format.
                        @endif
                        A safety copy of the current database is saved under <code class="rounded bg-white px-1.5 py-0.5 text-xs">storage/app/backups</code> before import (auto-restored if the upload is invalid).
                        Uploaded files from older app versions have stale menu data removed automatically; modules are refreshed after restore.
                        Max file size {{ (int) floor(config('uploads.sql_restore_max_kb', 262144) / 1024) }} MB.
                    </p>
                </div>

                <form method="POST" action="{{ route('database.upload-sql') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <label for="database-sql-file" class="form-label">SQL file</label>
                        <input
                            id="database-sql-file"
                            name="sql_file"
                            type="file"
                            accept=".sql,application/sql,text/plain"
                            class="form-input"
                            required
                        >
                        @error('sql_file')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-start gap-2 text-sm text-amber-900">
                        <input
                            type="checkbox"
                            name="confirm_replace"
                            value="1"
                            class="mt-1 rounded border-amber-400 text-amber-700 focus:ring-amber-500"
                            @checked(old('confirm_replace'))
                            required
                        >
                        <span>I understand this will replace all current database data.</span>
                    </label>
                    @error('confirm_replace')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="btn-primary bg-amber-700 hover:bg-amber-800">
                        Upload and Restore
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
