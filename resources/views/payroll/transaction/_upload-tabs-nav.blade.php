@php
    use App\Support\PayrollTransactionModule;
@endphp

<nav class="mb-4 flex flex-wrap gap-1 border-b border-gray-200 pb-2" role="tablist">
    @foreach ($uploadTypes as $typeKey => $typeLabel)
        <a
            href="{{ route(PayrollTransactionModule::routeName('tab'), ['tab' => 'upload-transactions', 'upload' => $typeKey]) }}"
            role="tab"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors {{ ($uploadType ?? PayrollTransactionModule::DEFAULT_UPLOAD_TYPE) === $typeKey ? 'bg-[#0B318F] text-white' : 'text-gray-600 hover:bg-gray-100' }}"
            aria-selected="{{ ($uploadType ?? PayrollTransactionModule::DEFAULT_UPLOAD_TYPE) === $typeKey ? 'true' : 'false' }}"
        >
            {{ $typeLabel }}
        </a>
    @endforeach
</nav>
