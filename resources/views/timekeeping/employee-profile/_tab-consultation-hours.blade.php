@php
    use App\Support\TimekeepingEmployeeProfile;
    use Illuminate\Support\Carbon;

    $editable = (bool) ($editable ?? false);
    $formContext = 'consultation-employee-'.$employee->employee_id;
    $useOld = $editable && old('form_context') === $formContext;
    $consultationCheck = session('consultation_check');
    $consultationCheck = $editable
        && is_array($consultationCheck)
        && (int) ($consultationCheck['employee_id'] ?? 0) === (int) $employee->employee_id
        ? $consultationCheck
        : null;
    $slotsByDay = [];

    if ($useOld) {
        foreach (old('consultation_hours', []) as $slot) {
            $dayId = (int) ($slot['day_id'] ?? 0);
            if ($dayId <= 0) {
                continue;
            }
            $slotsByDay[$dayId][] = [
                'time_start' => substr((string) ($slot['time_start'] ?? ''), 0, 5),
                'time_end' => substr((string) ($slot['time_end'] ?? ''), 0, 5),
            ];
        }
    } else {
        foreach ($employee->consultationHours as $slot) {
            $slotsByDay[(int) $slot->day_id][] = [
                'time_start' => substr((string) $slot->time_start, 0, 5),
                'time_end' => substr((string) $slot->time_end, 0, 5),
            ];
        }
    }

    $formatClock = function (?string $time): string {
        if ($time === null || trim($time) === '') {
            return '—';
        }

        return Carbon::parse($time)->format('g:i A');
    };
@endphp

<div class="space-y-4">
    <p class="text-sm text-gray-600">
        Set the faculty consultation schedule for each day. A day can have more than one range, for example Monday 9:00 AM–10:00 AM and 2:00 PM–3:00 PM.
    </p>

    @if ($consultationCheck)
        @if (($consultationCheck['conflicts'] ?? []) !== [])
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" data-consultation-conflicts>
                <p class="font-semibold">
                    Saved, but some consultation hours conflict with this faculty's teaching load{{ ($consultationCheck['source'] ?? '') === 'stored' ? ' (last pulled load — Skolaris could not be reached)' : ' from Skolaris' }}:
                </p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($consultationCheck['conflicts'] as $conflict)
                        <li>{{ $conflict }}</li>
                    @endforeach
                </ul>
            </div>
        @elseif (($consultationCheck['source'] ?? '') === 'none')
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                Teaching load could not be checked: Skolaris could not be reached and no pulled load is on file.
            </div>
        @endif
    @endif

    @if ($editable)
        <form
            method="POST"
            action="{{ route(TimekeepingEmployeeProfile::routeName('consultation.store'), $employee->employee_id) }}"
            class="space-y-4"
            data-consultation-hours-form
        >
            @csrf
            <input type="hidden" name="form_context" value="{{ $formContext }}">
            <input type="hidden" name="setup_employee_id" value="{{ $employee->employee_id }}">
            <input type="hidden" name="active_tab" value="consultation">
            <input type="hidden" name="search" value="{{ request('search') }}">
            <input type="hidden" name="employment_category" value="{{ request('employment_category', 'all') }}">
            <input type="hidden" name="page" value="{{ request('page') }}">
    @endif

    <div class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200">
        @foreach (TimekeepingEmployeeProfile::consultationWeekDays() as $day)
            @php $daySlots = $slotsByDay[(int) $day->day_id] ?? []; @endphp
            <div class="grid gap-3 px-4 py-3 sm:grid-cols-[9rem_minmax(0,1fr)] sm:items-start" data-consultation-day>
                <div class="pt-1">
                    <p class="text-sm font-semibold text-gray-900">{{ $day->day }}</p>
                    @if ($editable)
                        <button type="button" class="mt-1 text-xs font-medium text-[#00A3E6] hover:underline" data-consultation-add>
                            Add hours
                        </button>
                    @endif
                </div>

                <div>
                    <p class="text-sm text-gray-400 {{ $daySlots === [] ? '' : 'hidden' }}" data-consultation-empty>
                        No consultation hours
                    </p>

                    <div class="space-y-2" data-consultation-slots>
                        @foreach ($daySlots as $slot)
                            @if ($editable)
                                <div class="flex flex-wrap items-center gap-2" data-consultation-slot>
                                    <input type="hidden" name="consultation_hours[{{ $day->day_id }}-{{ $loop->index }}][day_id]" value="{{ $day->day_id }}">
                                    <input type="time" name="consultation_hours[{{ $day->day_id }}-{{ $loop->index }}][time_start]" value="{{ $slot['time_start'] }}" class="form-input w-36 text-sm" required>
                                    <span class="text-sm text-gray-500">to</span>
                                    <input type="time" name="consultation_hours[{{ $day->day_id }}-{{ $loop->index }}][time_end]" value="{{ $slot['time_end'] }}" class="form-input w-36 text-sm" required>
                                    <button type="button" class="btn-icon text-red-600 hover:bg-red-50" data-consultation-remove title="Remove">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            @else
                                <span class="mr-2 inline-flex rounded-full bg-[#00A3E6]/10 px-2.5 py-1 text-sm font-medium text-[#0B318F]">
                                    {{ $formatClock($slot['time_start']) }} – {{ $formatClock($slot['time_end']) }}
                                </span>
                            @endif
                        @endforeach
                    </div>

                    @if ($editable)
                        <template>
                            <div class="flex flex-wrap items-center gap-2" data-consultation-slot>
                                <input type="hidden" name="consultation_hours[{{ $day->day_id }}-__INDEX__][day_id]" value="{{ $day->day_id }}">
                                <input type="time" name="consultation_hours[{{ $day->day_id }}-__INDEX__][time_start]" class="form-input w-36 text-sm" required>
                                <span class="text-sm text-gray-500">to</span>
                                <input type="time" name="consultation_hours[{{ $day->day_id }}-__INDEX__][time_end]" class="form-input w-36 text-sm" required>
                                <button type="button" class="btn-icon text-red-600 hover:bg-red-50" data-consultation-remove title="Remove">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($errors->any() && $useOld)
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if ($editable)
            <div class="flex justify-end border-t border-gray-100 pt-4">
                <button type="submit" class="btn-primary">Save Consultation Hours</button>
            </div>
        </form>
    @endif
</div>
