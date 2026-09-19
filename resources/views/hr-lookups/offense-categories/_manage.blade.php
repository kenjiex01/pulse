@php
    $primaryKey = $config['primary_key'];
    $frequencyPrimaryKey = $frequencyConfig['primary_key'];
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-4 py-3 sm:px-5">
        <h2 class="text-sm font-semibold text-gray-900">Table of Penalties</h2>
        <p class="mt-0.5 text-xs text-gray-500">Use <strong>Edit</strong> on a category column or frequency row to set penalties. Add new columns or rows with the buttons above.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Frequency</th>
                    @foreach ($categories as $category)
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">
                            <div class="flex flex-wrap items-center gap-2">
                                <span>Category {{ $category->code }}</span>
                                @can('hr-lookup.update', [$lookup, $category])
                                    <button
                                        type="button"
                                        data-modal-open="hr-lookup-edit-{{ $lookup }}-{{ $category->{$primaryKey} }}"
                                        class="inline-flex items-center gap-1 rounded-md border border-[#00A3E6]/30 px-2 py-0.5 text-xs font-medium text-[#00A3E6] hover:bg-[#00A3E6]/5"
                                        title="Edit Category {{ $category->code }}"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Edit
                                    </button>
                                @endcan
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($frequencies as $frequency)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <div class="flex flex-wrap items-center gap-2">
                                <span>{{ $frequency->label }}</span>
                                @can('hr-lookup.update', ['offense-frequencies', $frequency])
                                    <button
                                        type="button"
                                        data-modal-open="hr-lookup-edit-offense-frequencies-{{ $frequency->{$frequencyPrimaryKey} }}"
                                        class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                                        title="Edit {{ $frequency->label }}"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Edit
                                    </button>
                                @endcan
                            </div>
                        </td>
                        @foreach ($categories as $category)
                            <td class="px-4 py-3 text-gray-600">{{ $penaltyMatrix[$category->code][$frequency->frequency_ordinal] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $categories->count() + 1 }}" class="px-4 py-8 text-center text-sm text-gray-500">
                            No offense frequencies yet. Click <strong>Add Frequency</strong> to create the first row.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach ($categories as $category)
    @can('hr-lookup.update', [$lookup, $category])
        @include('partials.modal', [
            'id' => "hr-lookup-edit-$lookup-{$category->{$primaryKey}}",
            'title' => 'Edit Offense Category',
            'description' => 'Update Category '.$category->code.' and frequency penalties',
            'open' => (string) ($openEditId ?? '') === (string) $category->{$primaryKey},
            'body' => view('hr-lookups.offense-categories._form', [
                'lookup' => $lookup,
                'config' => $config,
                'record' => $category,
                'frequencies' => $frequencies,
                'selectOptions' => $selectOptions ?? [],
                'formContext' => "edit-$lookup-{$category->{$primaryKey}}",
            ])->render(),
        ])
    @endcan
@endforeach

@foreach ($frequencies as $frequency)
    @can('hr-lookup.update', ['offense-frequencies', $frequency])
        @include('partials.modal', [
            'id' => "hr-lookup-edit-offense-frequencies-{$frequency->{$frequencyPrimaryKey}}",
            'title' => 'Edit Offense Frequency',
            'description' => 'Update '.$frequency->label,
            'open' => (string) ($openEditFrequencyId ?? '') === (string) $frequency->{$frequencyPrimaryKey},
            'body' => view('hr-lookups.offense-categories._frequency-form', [
                'lookup' => 'offense-frequencies',
                'config' => $frequencyConfig,
                'record' => $frequency,
                'formContext' => "edit-offense-frequencies-{$frequency->{$frequencyPrimaryKey}}",
            ])->render(),
        ])
    @endcan
@endforeach
