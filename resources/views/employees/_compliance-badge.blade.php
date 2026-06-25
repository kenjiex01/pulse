@php
    $status = $status ?? 'pending';
    $classes = match ($status) {
        'compliant' => 'badge-success',
        'withheld' => 'bg-red-50 text-red-700 border-red-200',
        'overdue' => 'bg-orange-50 text-orange-700 border-orange-200',
        default => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    };
@endphp

<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $classes }}">
    {{ ucfirst($status) }}
</span>
