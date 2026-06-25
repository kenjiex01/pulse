const instances = new WeakMap();

class SearchableSelect {
    constructor(select) {
        this.select = select;
        this.highlightIndex = 0;
        this.build();
        this.bindEvents();
        this.refresh();
        this.syncDisabled();
    }

    build() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'searchable-select';

        this.select.classList.add('searchable-select-native');
        this.select.parentNode.insertBefore(this.wrapper, this.select);
        this.wrapper.appendChild(this.select);

        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'searchable-select-trigger form-input';
        this.trigger.setAttribute('aria-haspopup', 'listbox');
        this.trigger.setAttribute('aria-expanded', 'false');

        this.panel = document.createElement('div');
        this.panel.className = 'searchable-select-panel hidden';
        this.panel.setAttribute('role', 'listbox');

        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.placeholder = 'Search...';
        this.searchInput.className = 'searchable-select-search';
        this.searchInput.setAttribute('autocomplete', 'off');

        this.list = document.createElement('ul');
        this.list.className = 'searchable-select-list';

        this.emptyState = document.createElement('div');
        this.emptyState.className = 'searchable-select-empty hidden';
        this.emptyState.textContent = 'No options found';

        this.panel.appendChild(this.searchInput);
        this.panel.appendChild(this.list);
        this.panel.appendChild(this.emptyState);

        this.wrapper.appendChild(this.trigger);
        this.wrapper.appendChild(this.panel);
    }

    bindEvents() {
        this.trigger.addEventListener('click', () => {
            if (this.select.disabled) {
                return;
            }

            this.togglePanel(!this.isOpen());
        });

        this.searchInput.addEventListener('input', () => {
            this.highlightIndex = 0;
            this.renderOptions();
        });

        this.searchInput.addEventListener('keydown', (event) => {
            const selectableOptions = this.getSelectableOptions();

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.highlightIndex = Math.min(this.highlightIndex + 1, selectableOptions.length - 1);
                this.renderOptions();
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.highlightIndex = Math.max(this.highlightIndex - 1, 0);
                this.renderOptions();
            }

            if (event.key === 'Enter' && selectableOptions[this.highlightIndex]) {
                event.preventDefault();
                this.chooseOption(selectableOptions[this.highlightIndex].value);
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.togglePanel(false);
            }
        });

        this.select.addEventListener('change', () => {
            this.syncTriggerLabel();
        });

        document.addEventListener('mousedown', (event) => {
            if (!this.wrapper.contains(event.target)) {
                this.togglePanel(false);
            }
        });
    }

    isOpen() {
        return !this.panel.classList.contains('hidden');
    }

    togglePanel(open) {
        if (this.select.disabled) {
            return;
        }

        this.panel.classList.toggle('hidden', !open);
        this.trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        this.wrapper.classList.toggle('searchable-select-open', open);

        if (open) {
            this.searchInput.value = '';
            this.highlightIndex = 0;
            this.renderOptions();
            this.searchInput.focus();
        }
    }

    getOptions() {
        return Array.from(this.select.options).map((option) => ({
            value: option.value,
            label: option.textContent.trim(),
            disabled: option.disabled,
        }));
    }

    getSelectableOptions() {
        const query = this.searchInput.value.trim().toLowerCase();

        return this.getOptions().filter((option) => {
            if (option.value === '' || option.disabled) {
                return false;
            }

            return option.label.toLowerCase().includes(query);
        });
    }

    renderOptions() {
        const selectableOptions = this.getSelectableOptions();
        const query = this.searchInput.value.trim().toLowerCase();
        const visibleOptions = this.getOptions().filter((option) => {
            if (option.value === '') {
                return false;
            }

            return option.label.toLowerCase().includes(query);
        });

        if (this.highlightIndex >= selectableOptions.length) {
            this.highlightIndex = Math.max(selectableOptions.length - 1, 0);
        }

        this.list.replaceChildren();

        visibleOptions.forEach((option) => {
            const item = document.createElement('li');
            item.className = 'searchable-select-option';
            item.setAttribute('role', 'option');
            item.textContent = option.label;

            if (option.disabled) {
                item.classList.add('searchable-select-option-disabled');
            }

            if (this.select.value === option.value) {
                item.classList.add('searchable-select-option-selected');
            }

            const selectableIndex = selectableOptions.findIndex((entry) => entry.value === option.value);

            if (selectableIndex === this.highlightIndex) {
                item.classList.add('searchable-select-option-highlighted');
            }

            item.addEventListener('mousedown', (event) => {
                event.preventDefault();

                if (!option.disabled) {
                    this.chooseOption(option.value);
                }
            });

            this.list.appendChild(item);
        });

        this.emptyState.classList.toggle('hidden', selectableOptions.length > 0);
    }

    chooseOption(value) {
        this.select.value = value;
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this.syncTriggerLabel();
        this.togglePanel(false);
    }

    syncTriggerLabel() {
        const selected = this.select.selectedOptions[0];
        const placeholder = this.select.options[0]?.value === '' ? this.select.options[0].textContent.trim() : 'Select...';

        if (!selected || selected.value === '') {
            this.trigger.textContent = placeholder;
            this.trigger.classList.add('searchable-select-trigger-placeholder');

            return;
        }

        this.trigger.textContent = selected.textContent.trim();
        this.trigger.classList.remove('searchable-select-trigger-placeholder');
    }

    syncDisabled() {
        const disabled = this.select.disabled;
        this.trigger.disabled = disabled;
        this.wrapper.classList.toggle('searchable-select-disabled', disabled);

        if (disabled) {
            this.togglePanel(false);
        }
    }

    refresh() {
        this.syncTriggerLabel();
        this.syncDisabled();

        if (this.isOpen()) {
            this.renderOptions();
        }
    }
}

export function initSearchableSelect(select) {
    if (!(select instanceof HTMLSelectElement)) {
        return null;
    }

    if (select.dataset.searchableSelectInitialized === 'true') {
        return instances.get(select) ?? null;
    }

    select.dataset.searchableSelectInitialized = 'true';
    const instance = new SearchableSelect(select);
    instances.set(select, instance);

    return instance;
}

export function initSearchableSelects(root = document) {
    root.querySelectorAll('select.form-input:not([data-no-searchable-select]):not([data-live-table-filter]):not([multiple])').forEach((select) => {
        initSearchableSelect(select);
    });
}

export function refreshSearchableSelect(select) {
    instances.get(select)?.refresh();
}
