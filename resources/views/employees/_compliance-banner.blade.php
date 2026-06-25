@php
    $status = $status ?? 'pending';
    $classes = match ($status) {
        'compliant' => 'border-green-200 bg-green-50 text-green-700',
        'withheld' => 'border-red-200 bg-red-50 text-red-700',
        'overdue' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-amber-200 bg-amber-50 text-amber-800',
    };
@endphp

<div class="mb-4 rounded-md border p-3 text-sm {{ $classes }}" data-employee-compliance-banner>
    <p class="font-semibold" data-compliance-status-label>Compliance Status: {{ strtoupper($status) }}</p>
</div>
