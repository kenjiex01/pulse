const pulseTableSkeletonFallback = (columns = 6, rows = 8, showHeader = true) => {
    const header = showHeader
        ? `<thead class="bg-gray-50"><tr>${Array.from({ length: columns }, (_, index) => {
            const widthClass = index === 0 ? 'w-3/4' : 'w-full';

            return `<th class="px-3 py-3 sm:px-4"><div class="h-3 rounded bg-gray-200 ${widthClass}"></div></th>`;
        }).join('')}</tr></thead>`
        : '';

    const body = Array.from({ length: rows }, () => {
        const cells = Array.from({ length: columns }, (_, index) => {
            let widthClass = 'w-full';

            if (index === 0) {
                widthClass = 'w-3/4';
            } else if (index === columns - 1) {
                widthClass = 'w-2/5';
            }

            const tone = index === 0 ? 'bg-gray-200' : 'bg-gray-100';

            return `<td class="px-3 py-3 sm:px-4"><div class="h-4 rounded ${tone} ${widthClass}"></div></td>`;
        }).join('');

        return `<tr data-table-skeleton-row aria-hidden="true">${cells}</tr>`;
    }).join('');

    return `<div class="table-skeleton animate-pulse" data-table-skeleton role="status" aria-busy="true"><span class="sr-only">Loading table</span><div class="datatable-skolaris-table-wrap"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">${header}<tbody class="divide-y divide-gray-100 bg-white">${body}</tbody></table></div></div></div>`;
};

export const pulseTableSkeletonHtml = (columns = 6, rows = 8, showHeader = true) => {
    const template = document.getElementById('pulse-table-skeleton-template');

    if (template) {
        return template.innerHTML;
    }

    return pulseTableSkeletonFallback(columns, rows, showHeader);
};

export const pulseTableSkeletonRowsHtml = (columns = 6, rows = 8) => {
    const template = document.getElementById('pulse-table-skeleton-rows-template');

    if (template) {
        return template.innerHTML;
    }

    return Array.from({ length: rows }, () => {
        const cells = Array.from({ length: columns }, (_, index) => {
            let widthClass = 'w-full';

            if (index === 0) {
                widthClass = 'w-3/4';
            } else if (index === columns - 1) {
                widthClass = 'w-2/5';
            }

            const tone = index === 0 ? 'bg-gray-200' : 'bg-gray-100';

            return `<td class="px-3 py-3 sm:px-4"><div class="h-4 animate-pulse rounded ${tone} ${widthClass}"></div></td>`;
        }).join('');

        return `<tr data-table-skeleton-row aria-hidden="true">${cells}</tr>`;
    }).join('');
};
