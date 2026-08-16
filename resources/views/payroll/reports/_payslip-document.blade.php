@php
    $forPdf = $forPdf ?? false;
    $payslips = $preview['meta']['payslips'] ?? [];
    $companyName = $preview['meta']['company_name'] ?? config('app.name');
@endphp

@if (! empty($preview['meta']['batch_label']) && ! $forPdf)
    <p @class(['mb-4 text-sm text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:10px;margin:0 0 10px;color:#555;' : '' }}">
        Batch: {{ $preview['meta']['batch_label'] }}
    </p>
@endif

<div @class(['space-y-8' => ! $forPdf])>
    @forelse ($payslips as $payslip)
        @php
            $isStaff = ($payslip['layout_type'] ?? 'faculty') === 'staff';
            $lineCount = max(count($payslip['earnings'] ?? []), count($payslip['deductions'] ?? []));
        @endphp
        <div
            @class([
                'rounded-xl border border-gray-300 bg-white p-4 sm:p-6 print:break-after-page',
                'relative overflow-hidden' => ($payslip['is_confidential'] ?? false) && ! $forPdf,
            ])
            style="{{ $forPdf ? (($loop->last ? '' : 'page-break-after:always;').'margin-bottom:0;border:1px solid #999;padding:12px;') : '' }}"
        >
            @if ($payslip['is_confidential'] ?? false)
                <div @class(['pointer-events-none absolute inset-0 flex items-center justify-center opacity-10' => ! $forPdf]) style="{{ $forPdf ? 'display:none;' : '' }}">
                    <span class="rotate-[-25deg] text-5xl font-bold uppercase tracking-widest text-red-700">Confidential</span>
                </div>
            @endif

            <div @class(['grid grid-cols-1 gap-6 lg:grid-cols-12' => ! $forPdf]) style="{{ $forPdf ? 'display:table;width:100%;' : '' }}">
                <div @class(['lg:col-span-4 border-r border-gray-200 pr-4' => ! $forPdf]) style="{{ $forPdf ? 'display:table-cell;width:30%;vertical-align:top;padding-right:12px;border-right:1px solid #ccc;' : '' }}">
                    <p @class(['text-xs leading-relaxed text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'font-size:9px;line-height:1.4;color:#333;' : '' }}">
                        I acknowledge to have received from {{ $companyName }} the amount stated below and have no further claims for services rendered.
                    </p>
                    <dl @class(['mt-4 space-y-2 text-sm' => ! $forPdf]) style="{{ $forPdf ? 'margin-top:10px;font-size:10px;' : '' }}">
                        <div><dt class="font-medium text-gray-600">Pay Period</dt><dd>{{ $payslip['pay_period'] ?? '' }}</dd></div>
                        <div><dt class="font-medium text-gray-600">Pay Date</dt><dd>{{ $payslip['pay_date'] ?? '' }}</dd></div>
                        <div><dt class="font-medium text-gray-600">Employee</dt><dd>{{ $payslip['employee_name'] ?? '' }}</dd></div>
                        <div><dt class="font-medium text-gray-600">Net Pay</dt><dd class="font-semibold">{{ number_format($payslip['net_pay'] ?? 0, 2) }}</dd></div>
                    </dl>
                    <div @class(['mt-10 border-t border-gray-400 pt-2 text-center text-xs text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'margin-top:24px;border-top:1px solid #666;padding-top:6px;text-align:center;font-size:9px;' : '' }}">
                        Signature
                    </div>
                </div>

                <div @class(['lg:col-span-8' => ! $forPdf]) style="{{ $forPdf ? 'display:table-cell;vertical-align:top;padding-left:12px;' : '' }}">
                    <div @class(['text-center' => ! $forPdf]) style="{{ $forPdf ? 'text-align:center;' : '' }}">
                        <h3 @class(['text-base font-bold text-gray-900' => ! $forPdf]) style="{{ $forPdf ? 'font-size:13px;font-weight:700;margin:0;' : '' }}">{{ $companyName }}</h3>
                        <p @class(['text-sm font-semibold underline' => ! $forPdf]) style="{{ $forPdf ? 'font-size:12px;font-weight:700;text-decoration:underline;' : '' }}">PAYSLIP</p>
                        <p @class(['text-sm text-gray-700' => ! $forPdf]) style="{{ $forPdf ? 'font-size:10px;margin-top:4px;' : '' }}">
                            {{ ($payslip['faculty_label'] ?? '') !== '' ? $payslip['faculty_label'].': ' : '' }}{{ $payslip['employee_name'] ?? '' }}
                        </p>
                        <p @class(['text-xs text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:9px;' : '' }}">Pay Period: {{ $payslip['pay_period'] ?? '' }}</p>
                        @if ($isStaff && ($payslip['days_present'] ?? null) !== null)
                            <p @class(['text-xs text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:9px;' : '' }}">Days Present: {{ number_format($payslip['days_present'], 4) }}</p>
                        @elseif (! $isStaff)
                            @if (($payslip['total_hours'] ?? null) !== null)
                                <p @class(['text-xs text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:9px;' : '' }}">Total Hours Present: {{ number_format($payslip['total_hours'], 4) }}</p>
                            @endif
                            @if (($payslip['loading_schedule'] ?? null) !== null)
                                <p @class(['text-xs text-gray-600' => ! $forPdf]) style="{{ $forPdf ? 'font-size:9px;' : '' }}">Loading Schedule: {{ number_format($payslip['loading_schedule'], 2) }}</p>
                            @endif
                        @endif
                    </div>

                    <table @class(['mt-4 w-full border-collapse text-xs' => ! $forPdf]) style="{{ $forPdf ? 'width:100%;margin-top:10px;border-collapse:collapse;font-size:9px;' : '' }}">
                        <thead>
                            <tr @class(['bg-slate-50' => ! $forPdf])>
                                <th @class(['border px-2 py-1.5 text-left font-semibold' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">Earnings</th>
                                @if ($isStaff)
                                    <th @class(['border px-2 py-1.5 text-right font-semibold' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;text-align:right;' : '' }}">Days</th>
                                @endif
                                <th @class(['border px-2 py-1.5 text-right font-semibold' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;text-align:right;' : '' }}">Amount</th>
                                <th @class(['border px-2 py-1.5 text-left font-semibold' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;' : '' }}">Deductions</th>
                                @if ($isStaff)
                                    <th @class(['border px-2 py-1.5 text-right font-semibold' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;text-align:right;' : '' }}">Mins</th>
                                @endif
                                <th @class(['border px-2 py-1.5 text-right font-semibold' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #999;padding:4px;background:#f3f4f6;text-align:right;' : '' }}">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($index = 0; $index < $lineCount; $index++)
                                @php
                                    $earning = $payslip['earnings'][$index] ?? null;
                                    $deduction = $payslip['deductions'][$index] ?? null;
                                @endphp
                                <tr>
                                    <td @class(['border px-2 py-1 text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">{{ $earning['label'] ?? '' }}</td>
                                    @if ($isStaff)
                                        <td @class(['border px-2 py-1 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ isset($earning['days']) ? number_format($earning['days'], 4) : '' }}</td>
                                    @endif
                                    <td @class(['border px-2 py-1 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ isset($earning['amount']) ? number_format($earning['amount'], 2) : '' }}</td>
                                    <td @class(['border px-2 py-1 text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">{{ $deduction['label'] ?? '' }}</td>
                                    @if ($isStaff)
                                        <td @class(['border px-2 py-1 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ isset($deduction['mins']) ? number_format($deduction['mins'], 2) : '' }}</td>
                                    @endif
                                    <td @class(['border px-2 py-1 text-right text-gray-800' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ isset($deduction['amount']) ? number_format($deduction['amount'], 2) : '' }}</td>
                                </tr>
                            @endfor
                        </tbody>
                        <tfoot>
                            <tr @class(['font-semibold bg-slate-50' => ! $forPdf])>
                                <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">{{ $isStaff ? 'New rate' : 'Daily Rate' }}</td>
                                @if ($isStaff)
                                    <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                                @endif
                                <td @class(['border px-2 py-1.5 text-right' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">
                                    {{ $isStaff
                                        ? (isset($payslip['new_rate']) ? number_format($payslip['new_rate'], 2) : '')
                                        : (isset($payslip['daily_rate']) ? number_format($payslip['daily_rate'], 2) : '') }}
                                </td>
                                <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                                @if ($isStaff)
                                    <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                                @endif
                                <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                            </tr>
                            <tr @class(['font-semibold bg-slate-50' => ! $forPdf])>
                                <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">Total Earnings</td>
                                @if ($isStaff)
                                    <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                                @endif
                                <td @class(['border px-2 py-1.5 text-right' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($payslip['total_earnings'] ?? 0, 2) }}</td>
                                <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">Total Deductions</td>
                                @if ($isStaff)
                                    <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                                @endif
                                <td @class(['border px-2 py-1.5 text-right' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($payslip['total_deductions'] ?? 0, 2) }}</td>
                            </tr>
                            <tr @class(['font-semibold' => ! $forPdf])>
                                @if ($isStaff)
                                    <td colspan="3" @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                                @else
                                    <td colspan="2" @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                                @endif
                                <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}">Net Pay</td>
                                @if ($isStaff)
                                    <td @class(['border px-2 py-1.5' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;' : '' }}"></td>
                                @endif
                                <td @class(['border px-2 py-1.5 text-right' => ! $forPdf]) style="{{ $forPdf ? 'border:1px solid #ccc;padding:4px;text-align:right;' : '' }}">{{ number_format($payslip['net_pay'] ?? 0, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <p @class(['text-sm text-gray-500' => ! $forPdf]) style="{{ $forPdf ? 'font-size:10px;color:#555;' : '' }}">
            No payslip data found for the selected batch and employees.
        </p>
    @endforelse
</div>
