@php
    $columns = (int) ($columns ?? 6);
    $rows = (int) ($rows ?? 6);
@endphp

@foreach (range(1, $rows) as $row)
    <tr data-table-skeleton-row aria-hidden="true">
        @foreach (range(1, $columns) as $column)
            <td class="px-3 py-3 sm:px-4">
                <div @class([
                    'h-4 animate-pulse rounded',
                    'bg-gray-200 w-3/4' => $column === 1,
                    'bg-gray-100 w-full' => $column !== 1 && $column < $columns,
                    'bg-gray-100 w-2/5' => $column === $columns,
                ])></div>
            </td>
        @endforeach
    </tr>
@endforeach
