@php
    use App\Support\HolidaySettings as HolidaySettingsSupport;
@endphp

<div class="space-y-6">
    <form method="POST" action="{{ route(HolidaySettingsSupport::routeName('update-year'), $year->timekeeping_year_id) }}" class="grid gap-4 sm:grid-cols-[minmax(0,180px)_auto] sm:items-end">
        @csrf
        @method('PUT')
        <input type="hidden" name="edit_year_id" value="{{ $year->timekeeping_year_id }}">
        <div>
            <label class="form-label">Year <span class="text-red-500">*</span></label>
            <input type="number" name="timekeeping_year" min="1900" max="9999" value="{{ old('timekeeping_year', $year->timekeeping_year) }}" class="form-input" required>
            @error('timekeeping_year')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-primary">Save Year</button>
    </form>

    @can('timekeeping-policy.update')
        <div class="rounded-lg border border-dashed border-gray-200 p-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Add Holiday to Year</h3>

            @if (count($availableOptions) > 0)
                <form method="POST" action="{{ route(HolidaySettingsSupport::routeName('store-year-entry'), $year->timekeeping_year_id) }}">
                    @csrf
                    <input type="hidden" name="edit_year_id" value="{{ $year->timekeeping_year_id }}">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[280px] flex-1">
                            <label class="form-label">Holiday <span class="text-red-500">*</span></label>
                            <select name="timekeeping_holiday_id" class="form-input" required>
                                <option value="">— Select holiday —</option>
                                @foreach ($availableOptions as $id => $label)
                                    <option value="{{ $id }}" @selected((int) old('timekeeping_holiday_id') === (int) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('timekeeping_holiday_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn-primary">Add Holiday</button>
                    </div>
                </form>
            @elseif (count($holidayOptions ?? []) === 0)
                <p class="text-sm text-gray-500">
                    No master holidays yet.
                    <a href="{{ HolidaySettingsSupport::moduleIndexRoute('holidays', ['create' => 1]) }}" class="font-medium text-[#0089c2] hover:underline">Create a holiday</a>
                    first, then add it here.
                </p>
            @else
                <p class="text-sm text-gray-500">
                    All master holidays are already assigned to this year.
                    <a href="{{ HolidaySettingsSupport::moduleIndexRoute('holidays', ['create' => 1]) }}" class="font-medium text-[#0089c2] hover:underline">Create another holiday</a>
                    to add more.
                </p>
            @endif
        </div>
    @endcan

    <div>
        <h3 class="mb-3 text-sm font-semibold text-gray-900">Holiday Schedule</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="table-skolaris min-w-[720px]">
            <thead>
                <tr>
                    <th>Holiday Code</th>
                    <th>Legal / Special</th>
                    <th>Date</th>
                    <th>Recurring</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($year->holidayYears as $entry)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $entry->timekeeping_holiday_code }}</td>
                        <td class="text-gray-600">{{ HolidaySettingsSupport::legalLabel($entry->is_legal) }}</td>
                        <td class="text-gray-600">{{ $entry->dt_datestamp?->format('Y-m-d') }}</td>
                        <td class="text-gray-600">{{ $entry->recurring ? 'Yes' : 'No' }}</td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                @can('timekeeping-policy.update')
                                    <button type="button" data-modal-open="holiday-year-entry-edit-{{ $entry->timekeeping_holiday_year_id }}" class="btn-icon" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endcan
                                @can('timekeeping-policy.delete')
                                    <form method="POST" action="{{ route(HolidaySettingsSupport::routeName('destroy-year-entry'), [$year->timekeeping_year_id, $entry->timekeeping_holiday_year_id]) }}" onsubmit="return confirm('Remove this holiday from the year?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="edit_year_id" value="{{ $year->timekeeping_year_id }}">
                                        <button type="submit" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" title="Delete">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-sm text-gray-500">No holidays assigned to this year yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

@foreach ($year->holidayYears as $entry)
    @can('timekeeping-policy.update')
        @include('partials.modal', [
            'id' => 'holiday-year-entry-edit-'.$entry->timekeeping_holiday_year_id,
            'title' => 'Edit Year Holiday',
            'description' => 'Update holiday schedule for '.$year->timekeeping_year,
            'body' => view('timekeeping.holiday-settings.year._entry-form', [
                'year' => $year,
                'entry' => $entry,
            ])->render(),
        ])
    @endcan
@endforeach
