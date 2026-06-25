@php
    use App\Support\HolidaySettings as HolidaySettingsSupport;
@endphp

<form
    method="POST"
    action="{{ route(HolidaySettingsSupport::routeName('store-year')) }}"
    class="space-y-4"
>
    @csrf
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    <div>
        <label class="form-label">Year <span class="text-red-500">*</span></label>
        <input type="number" name="timekeeping_year" min="1900" max="9999" value="{{ old('timekeeping_year') }}" class="form-input max-w-xs" required>
        @error('timekeeping_year')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-gray-500">Recurring master holidays are copied automatically when a new year is created.</p>
    </div>

    @include('partials.modal-form-actions', [
        'submitLabel' => 'Create Year',
    ])
</form>
