const GOV_ID_MAX_DIGITS = {
    sss: 10,
    philhealth: 12,
    pagibig: 12,
    tin: 12,
};

const segmentDigits = (digits, lengths) => {
    const parts = [];
    let offset = 0;

    for (const length of lengths) {
        const part = digits.slice(offset, offset + length);

        if (part === '') {
            break;
        }

        parts.push(part);
        offset += length;
    }

    if (offset < digits.length) {
        parts.push(digits.slice(offset));
    }

    return parts.join('-');
};

export const digitsOnly = (value) => String(value ?? '').replace(/\D/g, '');

export const formatGovernmentId = (value, type) => {
    const digits = digitsOnly(value).slice(0, GOV_ID_MAX_DIGITS[type] ?? 12);

    if (digits === '') {
        return '';
    }

    switch (type) {
        case 'sss':
            return segmentDigits(digits, [2, 7, 1]);
        case 'philhealth':
            return segmentDigits(digits, [2, 9, 1]);
        case 'pagibig':
            return segmentDigits(digits, [4, 4, 4]);
        case 'tin':
            return digits.length > 9
                ? segmentDigits(digits, [3, 3, 3, 3])
                : segmentDigits(digits, [3, 3, 3]);
        default:
            return digits;
    }
};

export const initGovernmentIdInputs = (root = document) => {
    root.querySelectorAll('[data-gov-id-input]').forEach((input) => {
        if (input.dataset.govIdBound === '1') {
            return;
        }

        const type = input.dataset.govIdType;

        if (!type) {
            return;
        }

        input.dataset.govIdBound = '1';
        input.value = formatGovernmentId(input.value, type);

        input.addEventListener('input', () => {
            input.value = formatGovernmentId(input.value, type);
        });

        input.addEventListener('blur', () => {
            input.value = formatGovernmentId(input.value, type);
        });
    });
};
