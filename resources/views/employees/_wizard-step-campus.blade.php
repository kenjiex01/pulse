<form method="POST" action="{{ route('employees.wizard.campus') }}" class="space-y-6" data-campus-wizard-form>
    @csrf

    <h2 class="text-2xl font-bold text-gray-900">Select Your Campus</h2>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($campuses as $campus)
            @include('employees._campus-card', [
                'campus' => $campus,
                'selectedCampusId' => old('campus_id', $selectedCampus?->campus_id),
            ])
        @endforeach
    </div>

    @error('campus_id')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    <p class="text-sm text-gray-500">Click a campus card to continue to the next step.</p>
</form>
