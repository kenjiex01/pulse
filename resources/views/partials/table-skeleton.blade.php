@php
    $columns = (int) ($columns ?? 6);
    $rows = (int) ($rows ?? 8);
    $minWidth = $minWidth ?? null;
@endphp

<div
    class="table-skeleton animate-pulse"
    data-table-skeleton
    role="status"
    aria-busy="true"
>
    <span class="sr-only">{{ $srOnly ?? 'Loading table' }}</span>

    <div @class(['datatable-skolaris-table-wrap' => $wrap ?? true])>
        <div class="overflow-x-auto">
            <table @class(['min-w-full divide-y divide-gray-200', 'table-skolaris w-full' => ! ($plain ?? false)]) @if($minWidth) style="min-width: {{ $minWidth }}" @endif>
                @if ($showHeader ?? true)
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach (range(1, $columns) as $column)
                                <th class="px-3 py-3 sm:px-4">
                                    <div @class([
                                        'h-3 rounded bg-gray-200',
                                        'w-3/4' => $column === 1,
                                        'w-full' => $column !== 1,
                                    ])></div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach (range(1, $rows) as $row)
                        <tr data-table-skeleton-row aria-hidden="true">
                            @foreach (range(1, $columns) as $column)
                                <td class="px-3 py-3 sm:px-4">
                                    <div @class([
                                        'h-4 rounded',
                                        'bg-gray-200 w-3/4' => $column === 1,
                                        'bg-gray-100 w-full' => $column !== 1 && $column < $columns,
                                        'bg-gray-100 w-2/5' => $column === $columns,
                                    ])></div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
