@php
    $schemes = [
        ['from' => 'from-blue-500', 'to' => 'to-blue-600', 'bg' => 'bg-blue-50', 'icon' => 'text-blue-600'],
        ['from' => 'from-indigo-500', 'to' => 'to-indigo-600', 'bg' => 'bg-indigo-50', 'icon' => 'text-indigo-600'],
        ['from' => 'from-cyan-500', 'to' => 'to-cyan-600', 'bg' => 'bg-cyan-50', 'icon' => 'text-cyan-600'],
        ['from' => 'from-sky-500', 'to' => 'to-sky-600', 'bg' => 'bg-sky-50', 'icon' => 'text-sky-600'],
    ];
    $scheme = $schemes[$index % count($schemes)];
@endphp

<div class="group stat-card">
    <div class="absolute -right-12 -top-12 h-24 w-24 rounded-full bg-gradient-to-br {{ $scheme['from'] }} {{ $scheme['to'] }} opacity-10 transition-transform duration-500 group-hover:scale-150"></div>
    <div class="relative z-10 p-4 sm:p-5">
        <div class="mb-2 flex items-center justify-between gap-3">
            <div class="rounded-lg p-2 {{ $scheme['bg'] }} transition-colors group-hover:brightness-95">
                {!! $icon !!}
            </div>
            <div class="min-w-0 flex-1 text-right">
                <span class="block text-xl font-bold leading-tight text-gray-900 sm:text-2xl">{{ $value }}</span>
            </div>
        </div>
        <div class="text-[11px] font-medium leading-tight text-gray-700 sm:text-xs">{{ $title }}</div>
        @isset($subtitle)
            <div class="text-[10px] leading-tight text-gray-600 sm:text-[11px]">{{ $subtitle }}</div>
        @endisset
        <div class="mt-2 h-1 w-full rounded-full bg-gray-100 sm:mt-3">
            <div class="h-1 rounded-full bg-gradient-to-r {{ $scheme['from'] }} {{ $scheme['to'] }}" style="width: {{ $progress ?? 100 }}%"></div>
        </div>
    </div>
</div>
