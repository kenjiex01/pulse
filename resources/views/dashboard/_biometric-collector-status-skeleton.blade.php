<div
    class="mt-8 animate-pulse rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
    data-dashboard-biometric-skeleton
    aria-busy="true"
    role="status"
>
    <span class="sr-only">Loading Biometric Collector status</span>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1 space-y-2">
            <div class="h-6 w-64 max-w-full rounded bg-gray-200"></div>
            <div class="h-4 w-full max-w-xl rounded bg-gray-100"></div>
            <div class="h-4 w-48 max-w-full rounded bg-gray-100"></div>
        </div>
        <div class="h-10 w-44 shrink-0 rounded-lg bg-gray-200"></div>
    </div>

    <div class="mb-4 flex flex-wrap gap-3">
        <div class="h-8 w-40 rounded-full bg-gray-100"></div>
        <div class="h-8 w-44 rounded-full bg-gray-100"></div>
        <div class="h-8 w-52 rounded-full bg-gray-100"></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-3">
            <div class="h-4 w-32 rounded bg-gray-200"></div>
            <div class="overflow-hidden rounded-lg border border-gray-100">
                <div class="space-y-0 divide-y divide-gray-100 bg-gray-50 p-3">
                    @foreach (range(1, 4) as $row)
                        <div class="flex gap-3 py-3">
                            <div class="h-4 flex-1 rounded bg-gray-200"></div>
                            <div class="h-4 w-20 rounded bg-gray-100"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <div class="h-4 w-36 rounded bg-gray-200"></div>
            <div class="overflow-hidden rounded-lg border border-gray-100">
                <div class="space-y-0 divide-y divide-gray-100 bg-gray-50 p-3">
                    @foreach (range(1, 4) as $row)
                        <div class="flex gap-3 py-3">
                            <div class="h-4 flex-1 rounded bg-gray-200"></div>
                            <div class="h-4 w-24 rounded bg-gray-100"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
