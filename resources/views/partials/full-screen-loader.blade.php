<div
    id="pulse-full-screen-loader"
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-white/70 backdrop-blur-md"
    aria-hidden="false"
    aria-live="polite"
    role="status"
>
    <div class="text-center">
        <div class="mb-6">
            <div class="relative inline-block">
                <div class="absolute inset-0 animate-ping rounded-full border-4 border-blue-200 opacity-50" style="animation-duration: 2s;"></div>
                <div class="relative flex h-20 w-20 items-center justify-center rounded-full border-4 border-blue-100">
                    <div class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-t-blue-600"></div>
                    <img
                        src="{{ asset('img/skolarislogo.png') }}"
                        alt="Skolaris Logo"
                        class="h-12 w-12 rounded object-cover"
                    >
                </div>
            </div>
        </div>

        <h2 class="mb-3 text-xl font-bold tracking-wide text-gray-800">SKOLARIS</h2>

        <div class="mx-auto mb-3 h-1.5 w-48 overflow-hidden rounded-full bg-gray-200">
            <div class="animate-loading-bar h-full rounded-full bg-[#00A3E6]"></div>
        </div>

        <p id="pulse-loader-text" class="text-sm font-medium text-gray-500">{{ $loaderText ?? 'Loading...' }}</p>

        <div class="mt-4 flex justify-center space-x-1.5">
            <div class="h-2 w-2 animate-bounce rounded-full bg-[#00A3E6]" style="animation-delay: 0ms;"></div>
            <div class="h-2 w-2 animate-bounce rounded-full bg-[#00A3E6]" style="animation-delay: 150ms;"></div>
            <div class="h-2 w-2 animate-bounce rounded-full bg-[#00A3E6]" style="animation-delay: 300ms;"></div>
        </div>
    </div>
</div>
