@php
    $steps = [
        ['name' => 'Campus', 'description' => $step === 0 ? 'Select your campus' : ($selectedCampus->campus_name ?? 'Select your campus')],
        ['name' => 'Details', 'description' => 'Position & contact info'],
        ['name' => 'Review', 'description' => 'Review & submit'],
    ];
@endphp

<div class="lg:sticky lg:top-8">
    <div class="mb-6 lg:mb-10">
        <h1 class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl lg:text-3xl">Add Employee</h1>
        <p class="mt-1 text-sm text-gray-600 sm:text-base">Same form as employee registration — creates the account immediately</p>
    </div>

    <ol class="space-y-4">
        @foreach ($steps as $index => $stepItem)
            @php
                $isActive = $step === $index;
                $isComplete = $step > $index;
            @endphp
            <li class="flex gap-3">
                <div class="flex flex-col items-center">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold {{ $isActive ? 'bg-[#00A3E6] text-white' : ($isComplete ? 'bg-[#10B981] text-white' : 'bg-gray-200 text-gray-600') }}">
                        @if ($isComplete)
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>
                    @if ($index < count($steps) - 1)
                        <div class="mt-1 h-8 w-0.5 {{ $isComplete ? 'bg-[#10B981]' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
                <div class="min-w-0 pb-4">
                    <p class="text-sm font-semibold {{ $isActive ? 'text-[#0089c2]' : 'text-gray-900' }}">{{ $stepItem['name'] }}</p>
                    <p class="text-xs text-gray-500">{{ $stepItem['description'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>

    <div class="mt-6 border-t border-gray-200 pt-6">
        <form method="POST" action="{{ route('employees.wizard.cancel') }}">
            @csrf
            <button type="submit" class="btn-secondary w-full">Cancel</button>
        </form>
    </div>
</div>
