@php
    $rateBasisId = (int) old('rate_basis_id', $group?->rate_basis_id ?? 1);
    $isFixedAmount = $rateBasisId === \App\Models\RateBasis::FIXED_AMOUNT_PER_HOUR;
    $rateLabel = $isFixedAmount ? 'Amount Per Hour' : 'Rate';
@endphp

<div class="rate-definition-rates" data-rate-definition-rates data-fixed-basis="{{ \App\Models\RateBasis::FIXED_AMOUNT_PER_HOUR }}">
    <p class="mb-3 text-sm text-gray-600">Please set the rates per Day Type.</p>

    <div class="rate-definition-rates-layout">
        <nav class="rate-definition-day-tabs flex flex-col gap-1" role="tablist">
            @foreach ($dayTypes as $index => $dayType)
                <button
                    type="button"
                    class="rate-definition-day-tab rounded-lg px-3 py-2 text-left text-sm {{ $index === 0 ? 'bg-[#00A3E6]/10 font-medium text-[#00A3E6]' : 'text-gray-600 hover:bg-gray-50' }}"
                    data-rate-day-tab="{{ $dayType->day_type_id }}"
                    role="tab"
                    aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                >
                    {{ $dayType->description }}
                </button>
            @endforeach
        </nav>

        <div class="rate-definition-day-panels min-w-0">
            @foreach ($dayTypes as $index => $dayType)
                @php
                    $panelId = 'rate-day-panel-'.$dayType->day_type_id;
                @endphp
                <div
                    id="{{ $panelId }}"
                    class="rate-definition-day-panel {{ $index === 0 ? 'is-active' : 'hidden' }}"
                    data-rate-day-panel="{{ $dayType->day_type_id }}"
                    role="tabpanel"
                    @unless($index === 0) hidden @endunless
                >
                    <div class="datatable-skolaris-table-wrap overflow-x-auto">
                        <table class="table-skolaris min-w-[720px]">
                            <thead>
                                <tr>
                                    <th class="w-40"></th>
                                    <th class="rate-definition-cb-col min-w-[10rem] {{ $isFixedAmount ? 'hidden' : '' }}">Computation Basis</th>
                                    <th class="min-w-[10rem]">Income Type</th>
                                    <th class="min-w-[8rem]">Is Taxable</th>
                                    <th class="min-w-[7rem] text-right" data-rate-column-label>{{ $rateLabel }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($timeTypes as $timeType)
                                    @php
                                        $key = $dayType->day_type_id.'_'.$timeType->time_type_id;
                                        $existing = $existingRates[$key] ?? null;
                                        $oldPrefix = "rates.{$dayType->day_type_id}.{$timeType->time_type_id}";
                                        $computationBasisId = old($oldPrefix.'.computation_basis_id', $existing?->computation_basis_id);
                                        $incomeTypeId = old($oldPrefix.'.income_type_id', $existing?->income_type_id);
                                        $isTaxable = old($oldPrefix.'.is_taxable', $existing?->is_taxable ?? true);
                                        $rate = old($oldPrefix.'.rate', $existing?->rate);
                                        $taxOptions = \App\Support\RateDefinition::incomeTaxOptions($incomeTypeId ? (int) $incomeTypeId : null);
                                    @endphp
                                    <tr>
                                        <td class="font-medium text-gray-900">{{ $timeType->description }}</td>
                                        <td class="rate-definition-cb-col {{ $isFixedAmount ? 'hidden' : '' }}">
                                            <select name="rates[{{ $dayType->day_type_id }}][{{ $timeType->time_type_id }}][computation_basis_id]" class="form-input text-sm" data-no-searchable-select>
                                                <option value="">None</option>
                                                @foreach ($selectOptions['computation_basis'] ?? [] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $computationBasisId === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select
                                                name="rates[{{ $dayType->day_type_id }}][{{ $timeType->time_type_id }}][income_type_id]"
                                                class="form-input text-sm rate-definition-income-type"
                                                data-no-searchable-select
                                                data-income-tax-target="taxable-{{ $dayType->day_type_id }}-{{ $timeType->time_type_id }}"
                                            >
                                                <option value="">None</option>
                                                @foreach ($selectOptions['income_types'] ?? [] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $incomeTypeId === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select
                                                id="taxable-{{ $dayType->day_type_id }}-{{ $timeType->time_type_id }}"
                                                name="rates[{{ $dayType->day_type_id }}][{{ $timeType->time_type_id }}][is_taxable]"
                                                class="form-input text-sm rate-definition-taxable"
                                                data-no-searchable-select
                                            >
                                                @foreach ($taxOptions as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $isTaxable === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                name="rates[{{ $dayType->day_type_id }}][{{ $timeType->time_type_id }}][rate]"
                                                value="{{ $rate }}"
                                                class="form-input text-right text-sm"
                                                maxlength="13"
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
