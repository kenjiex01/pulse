import { initSearchableSelects, refreshSearchableSelect } from './searchable-select.js';

document.addEventListener('DOMContentLoaded', () => {
    const loader = document.getElementById('pulse-full-screen-loader');
    const loaderText = document.getElementById('pulse-loader-text');

    const pulseLoader = {
        show(text = 'Loading...') {
            if (!loader) {
                return;
            }

            if (loaderText) {
                loaderText.textContent = text;
            }

            loader.classList.remove('hidden');
            loader.setAttribute('aria-hidden', 'false');
        },
        hide() {
            if (!loader) {
                return;
            }

            loader.classList.add('hidden');
            loader.setAttribute('aria-hidden', 'true');
        },
    };

    window.PulseLoader = pulseLoader;

    window.addEventListener('load', () => {
        pulseLoader.hide();
    });

    if (document.readyState === 'complete') {
        pulseLoader.hide();
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || link.dataset.noLoader !== undefined) {
            return;
        }

        if (link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        if (link.hasAttribute('data-modal-open') || link.closest('[data-modal-open]')) {
            return;
        }

        const href = link.getAttribute('href') ?? '';

        if (href.startsWith('#') || href.startsWith('javascript:')) {
            return;
        }

        let url;

        try {
            url = new URL(link.href, window.location.origin);
        } catch {
            return;
        }

        if (url.origin !== window.location.origin) {
            return;
        }

        pulseLoader.show('Loading...');
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.noLoader !== undefined) {
            return;
        }

        pulseLoader.show('Loading...');
    });

    const toggle = document.getElementById('sidebar-toggle');
    const overlay = document.getElementById('sidebar-overlay');
    const closeBtn = document.getElementById('sidebar-close');
    const desktopQuery = window.matchMedia('(min-width: 1024px)');

    const isDesktop = () => desktopQuery.matches;
    const isSidebarOpen = () => document.documentElement.dataset.sidebar === 'open';

    const setSidebarOpen = (open) => {
        document.documentElement.dataset.sidebar = open ? 'open' : 'closed';
        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open && !isDesktop()) {
            overlay?.classList.remove('hidden');
            document.body.classList.add('sidebar-mobile-open');
        } else {
            overlay?.classList.add('hidden');
            document.body.classList.remove('sidebar-mobile-open');
        }
    };

    const closeSidebar = () => setSidebarOpen(false);
    const toggleSidebar = () => setSidebarOpen(!isSidebarOpen());

    if (!document.documentElement.dataset.sidebar) {
        document.documentElement.dataset.sidebar = isDesktop() ? 'open' : 'closed';
    }

    setSidebarOpen(document.documentElement.dataset.sidebar === 'open');

    desktopQuery.addEventListener('change', () => {
        setSidebarOpen(isDesktop());
    });

    toggle?.addEventListener('click', toggleSidebar);
    overlay?.addEventListener('click', closeSidebar);
    closeBtn?.addEventListener('click', closeSidebar);

    document.querySelectorAll('[data-sidebar-close]').forEach((link) => {
        link.addEventListener('click', () => {
            if (!isDesktop()) {
                closeSidebar();
            }
        });
    });

    const sidebarSearch = document.getElementById('sidebar-search');
    const sidebarSearchEmpty = document.getElementById('sidebar-search-empty');

    const filterSidebarModules = () => {
        if (!sidebarSearch) {
            return;
        }

        const query = sidebarSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        document.querySelectorAll('[data-sidebar-group]').forEach((group) => {
            const groupName = group.dataset.sidebarItem.toLowerCase();
            const subItems = group.querySelectorAll('[data-sidebar-sub-list] [data-sidebar-item]');
            let groupVisible = query === '' || groupName.includes(query);

            subItems.forEach((item) => {
                const name = item.dataset.sidebarItem.toLowerCase();
                const matches = query === '' || name.includes(query) || groupName.includes(query);
                item.classList.toggle('hidden', !matches);

                if (matches) {
                    groupVisible = true;
                    visibleCount += 1;
                }
            });

            group.classList.toggle('hidden', !groupVisible);

            if (groupVisible && query !== '' && group.tagName === 'DETAILS') {
                setSidebarGroupOpenInstant(group, true);
            }
        });

        document.querySelectorAll('[data-sidebar-item]:not([data-sidebar-group])').forEach((item) => {
            if (item.closest('[data-sidebar-group]')) {
                return;
            }

            const name = item.dataset.sidebarItem.toLowerCase();
            const matches = query === '' || name.includes(query);
            item.classList.toggle('hidden', !matches);

            if (matches) {
                visibleCount += 1;
            }
        });

        document.querySelectorAll('[data-sidebar-section]').forEach((section) => {
            const hasVisibleItems = section.querySelector('[data-sidebar-item]:not(.hidden), [data-sidebar-group]:not(.hidden)');
            section.classList.toggle('hidden', !hasVisibleItems);
        });

        sidebarSearchEmpty?.classList.toggle('hidden', visibleCount > 0 || query === '');
    };

    sidebarSearch?.addEventListener('input', filterSidebarModules);

    const SIDEBAR_SUB_PANEL_MS = 300;

    const getSidebarGroups = () => document.querySelectorAll('[data-sidebar-group]');

    const getSidebarSubPanel = (group) => group.querySelector('.sidebar-sub-panel');

    const setSidebarGroupOpenInstant = (group, open) => {
        const panel = getSidebarSubPanel(group);

        group.open = open;

        if (panel) {
            panel.style.gridTemplateRows = open ? '1fr' : '0fr';
            panel.style.transition = 'none';

            requestAnimationFrame(() => {
                panel.style.transition = '';
                panel.style.gridTemplateRows = '';
            });
        }
    };

    const waitForSidebarSubPanelTransition = (panel) => new Promise((resolve) => {
        let settled = false;

        const finish = () => {
            if (settled) {
                return;
            }

            settled = true;
            panel.removeEventListener('transitionend', onEnd);
            clearTimeout(fallback);
            resolve();
        };

        const onEnd = (event) => {
            if (event.target !== panel || event.propertyName !== 'grid-template-rows') {
                return;
            }

            finish();
        };

        const fallback = setTimeout(finish, SIDEBAR_SUB_PANEL_MS + 50);

        panel.addEventListener('transitionend', onEnd);
    });

    const animateSidebarGroup = async (group, open) => {
        const panel = getSidebarSubPanel(group);

        if (!panel) {
            group.open = open;
            return;
        }

        if (open) {
            group.open = true;
            panel.style.gridTemplateRows = '0fr';

            await new Promise((resolve) => {
                requestAnimationFrame(() => {
                    requestAnimationFrame(resolve);
                });
            });

            panel.style.gridTemplateRows = '1fr';
            await waitForSidebarSubPanelTransition(panel);
            panel.style.gridTemplateRows = '';

            return;
        }

        if (!group.open) {
            return;
        }

        panel.style.gridTemplateRows = '1fr';

        await new Promise((resolve) => {
            requestAnimationFrame(() => {
                panel.style.gridTemplateRows = '0fr';
                resolve();
            });
        });

        await waitForSidebarSubPanelTransition(panel);
        group.open = false;
        panel.style.gridTemplateRows = '';
    };

    const closeOtherSidebarGroups = async (currentGroup, { animate = true } = {}) => {
        const others = [...getSidebarGroups()].filter(
            (group) => group !== currentGroup && group.open
        );

        if (others.length === 0) {
            return;
        }

        if (!animate) {
            others.forEach((group) => setSidebarGroupOpenInstant(group, false));
            return;
        }

        await Promise.all(others.map((group) => animateSidebarGroup(group, false)));
    };

    const initSidebarAccordion = () => {
        getSidebarGroups().forEach((group) => {
            if (group.open) {
                setSidebarGroupOpenInstant(group, true);
            }

            const summary = group.querySelector('summary');

            summary?.addEventListener('click', async (event) => {
                event.preventDefault();

                const willOpen = !group.open;

                if (willOpen) {
                    await closeOtherSidebarGroups(group);
                    await animateSidebarGroup(group, true);
                    return;
                }

                await animateSidebarGroup(group, false);
            });
        });

        const normalizeOpenSidebarGroups = () => {
            const openGroups = [...getSidebarGroups()].filter((group) => group.open);

            if (openGroups.length <= 1) {
                return;
            }

            const activeGroup = openGroups.find((group) => group.querySelector('.sidebar-sub-link-active'));

            openGroups.forEach((group) => {
                if (group !== (activeGroup ?? openGroups[0])) {
                    setSidebarGroupOpenInstant(group, false);
                }
            });
        };

        normalizeOpenSidebarGroups();
    };

    initSidebarAccordion();

    document.querySelectorAll('.permission-full-control').forEach((fullControlCheckbox) => {
        const moduleId = fullControlCheckbox.dataset.permissionModule;
        const subModuleId = fullControlCheckbox.dataset.permissionSubModule;
        const selector = subModuleId
            ? `[data-permission-sub-module="${subModuleId}"].permission-checkbox`
            : `[data-permission-module="${moduleId}"].permission-checkbox`;

        const syncFullControl = () => {
            document.querySelectorAll(selector).forEach((checkbox) => {
                if (fullControlCheckbox.checked) {
                    checkbox.checked = true;
                    checkbox.disabled = true;
                } else {
                    checkbox.disabled = false;
                }
            });
        };

        fullControlCheckbox.addEventListener('change', syncFullControl);
        syncFullControl();
    });

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) {
                return;
            }

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.textContent = isHidden ? 'HIDE' : 'SHOW';
        });
    });

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.classList.add('hidden');

        if (!document.querySelector('.modal-overlay:not(.hidden)')) {
            document.body.classList.remove('modal-open');
        }
    };

    const openModal = (modal, { stack = false } = {}) => {
        if (!modal) {
            return;
        }

        if (!stack) {
            document.querySelectorAll('.modal-overlay:not(.hidden)').forEach((openModalEl) => {
                if (openModalEl !== modal) {
                    closeModal(openModalEl);
                }
            });
        }

        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    };

    document.addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-modal-open]');

        if (openTrigger) {
            openModal(document.getElementById(openTrigger.dataset.modalOpen));

            return;
        }

        const closeTrigger = event.target.closest('[data-modal-close]');

        if (closeTrigger) {
            closeModal(closeTrigger.closest('.modal-overlay'));
        }
    });

    document.querySelectorAll('[data-modal-auto-open]').forEach((modal) => {
        openModal(modal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const openModals = [...document.querySelectorAll('.modal-overlay:not(.hidden)')];

        if (openModals.length === 0) {
            return;
        }

        openModals.sort((left, right) => {
            const leftZ = parseInt(window.getComputedStyle(left).zIndex, 10) || 0;
            const rightZ = parseInt(window.getComputedStyle(right).zIndex, 10) || 0;

            return rightZ - leftZ;
        });

        closeModal(openModals[0]);
    });

    document.querySelectorAll('[data-extended-profile-root]').forEach((root) => {
        const indexes = JSON.parse(root.dataset.extendedIndexes || '{}');

        const nextIndex = (collection) => {
            const current = Number(indexes[collection] ?? 0);
            indexes[collection] = current + 1;

            return current;
        };

        root.addEventListener('click', (event) => {
            const addButton = event.target.closest('[data-extended-add]');

            if (addButton) {
                const collection = addButton.dataset.extendedAdd;
                const relationshipType = addButton.dataset.relationshipType;
                const template = root.querySelector(`[data-extended-template="${collection}"]`);

                if (!template) {
                    return;
                }

                const index = nextIndex(collection);
                let html = template.innerHTML
                    .replaceAll('__INDEX__', String(index))
                    .replaceAll('__TYPE__', relationshipType || '');

                const container = relationshipType
                    ? root.querySelector(`[data-extended-rows="${collection}"][data-relationship-type="${relationshipType}"]`)
                    : root.querySelector(`[data-extended-rows="${collection}"]:not([data-relationship-type])`) || root.querySelector(`[data-extended-rows="${collection}"]`);

                container?.insertAdjacentHTML('beforeend', html);

                return;
            }

            const removeButton = event.target.closest('[data-extended-remove]');

            if (removeButton) {
                removeButton.closest('[data-extended-row]')?.remove();
            }
        });
    });

    document.querySelectorAll('[data-campus-wizard-form]').forEach((form) => {
        const cards = form.querySelectorAll('.campus-card');
        let isSubmitting = false;

        const syncCampusSelection = () => {
            const selected = form.querySelector('input[name="campus_id"]:checked');

            cards.forEach((card) => {
                card.classList.toggle('campus-card-selected', card.contains(selected));
            });
        };

        const submitCampusSelection = () => {
            if (isSubmitting) {
                return;
            }

            isSubmitting = true;
            cards.forEach((card) => {
                card.classList.add('pointer-events-none', 'opacity-70');
            });

            form.requestSubmit();
        };

        cards.forEach((card) => {
            card.addEventListener('click', (event) => {
                if (event.target.closest('a')) {
                    return;
                }

                const input = card.querySelector('input[name="campus_id"]');

                if (!input) {
                    return;
                }

                const wasAlreadySelected = input.checked;
                input.checked = true;
                syncCampusSelection();

                if (!wasAlreadySelected) {
                    submitCampusSelection();
                }
            });
        });

        syncCampusSelection();
    });

    const populateSelect = (select, items, config) => {
        const selectedValue = select.dataset.selectedValue || select.value;
        select.innerHTML = `<option value="">${config.placeholder}</option>`;

        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item[config.valueKey];
            option.textContent = config.label ? config.label(item) : item[config.valueKey];

            if (config.data) {
                Object.entries(config.data(item)).forEach(([key, value]) => {
                    option.dataset[key] = value ?? '';
                });
            }

            if (selectedValue && option.value === selectedValue) {
                option.selected = true;
            }

            select.appendChild(option);
        });

        select.disabled = items.length === 0;
        refreshSearchableSelect(select);
    };

    document.querySelectorAll('[data-employee-address-root]').forEach((root) => {
        const countrySelect = root.querySelector('[data-address-country]');
        const phFields = root.querySelector('[data-address-ph-fields]');
        const intlFields = root.querySelector('[data-address-intl-fields]');
        const regionSelect = root.querySelector('[data-address-region]');
        const provinceSelect = root.querySelector('[data-address-province]');
        const citySelect = root.querySelector('[data-address-city]');
        const postalInput = document.getElementById('postal_code');
        const provincesUrl = root.dataset.provincesUrl;
        const citiesUrl = root.dataset.citiesUrl;

        const intlInputs = root.querySelectorAll('[data-address-intl-name]');

        const setAddressMode = (isPhilippines) => {
            phFields?.classList.toggle('hidden', !isPhilippines);
            intlFields?.classList.toggle('hidden', isPhilippines);

            const fields = [
                { ph: regionSelect, intl: root.querySelector('[data-address-intl-name="region"]'), name: 'region' },
                { ph: provinceSelect, intl: root.querySelector('[data-address-intl-name="province"]'), name: 'province' },
                { ph: citySelect, intl: root.querySelector('[data-address-intl-name="city_municipality"]'), name: 'city_municipality' },
            ];

            fields.forEach(({ ph, intl, name }) => {
                if (isPhilippines) {
                    ph?.setAttribute('name', name);
                    ph?.removeAttribute('disabled');
                    intl?.removeAttribute('name');
                    intl?.setAttribute('disabled', 'disabled');
                } else {
                    ph?.removeAttribute('name');
                    ph?.setAttribute('disabled', 'disabled');
                    intl?.setAttribute('name', name);
                    intl?.removeAttribute('disabled');
                }
            });
        };

        const isPhilippinesCountry = () => {
            const selected = countrySelect?.selectedOptions?.[0];

            return selected?.dataset.isPhilippines === '1';
        };

        const loadProvinces = async (regionId, selectedProvince = '') => {
            if (!regionId || !provincesUrl) {
                populateSelect(provinceSelect, [], { placeholder: 'Select Province', valueKey: 'province_name' });
                populateSelect(citySelect, [], { placeholder: 'Select City', valueKey: 'city_name' });

                return;
            }

            provinceSelect.dataset.selectedValue = selectedProvince;
            const response = await fetch(`${provincesUrl}?region_id=${encodeURIComponent(regionId)}`, {
                headers: { Accept: 'application/json' },
            });
            const provinces = await response.json();

            populateSelect(provinceSelect, provinces, {
                placeholder: 'Select Province',
                valueKey: 'province_name',
                data: (item) => ({ provinceId: String(item.province_id) }),
            });
        };

        const loadCities = async (provinceId, selectedCity = '') => {
            if (!provinceId || !citiesUrl) {
                populateSelect(citySelect, [], { placeholder: 'Select City', valueKey: 'city_name' });

                return;
            }

            citySelect.dataset.selectedValue = selectedCity;
            const response = await fetch(`${citiesUrl}?province_id=${encodeURIComponent(provinceId)}`, {
                headers: { Accept: 'application/json' },
            });
            const cities = await response.json();

            populateSelect(citySelect, cities, {
                placeholder: 'Select City',
                valueKey: 'city_name',
                label: (item) => `${item.city_name} (${item.type})`,
                data: (item) => ({ postalCode: item.postal_code ?? '' }),
            });
        };

        countrySelect?.addEventListener('change', () => {
            setAddressMode(isPhilippinesCountry());
        });

        regionSelect?.addEventListener('change', async () => {
            const regionId = regionSelect.selectedOptions[0]?.dataset.regionId;
            provinceSelect.dataset.selectedValue = '';
            citySelect.dataset.selectedValue = '';
            await loadProvinces(regionId);
            populateSelect(citySelect, [], { placeholder: 'Select City', valueKey: 'city_name' });
        });

        provinceSelect?.addEventListener('change', async () => {
            const provinceId = provinceSelect.selectedOptions[0]?.dataset.provinceId;
            citySelect.dataset.selectedValue = '';
            await loadCities(provinceId);
        });

        citySelect?.addEventListener('change', () => {
            const postalCode = citySelect.selectedOptions[0]?.dataset.postalCode;

            if (postalCode && postalInput && !postalInput.value) {
                postalInput.value = postalCode;
            }
        });

        setAddressMode(isPhilippinesCountry());
    });

    const campusFilterOptionCache = new Map();

    document.querySelectorAll('[data-campus-filter]').forEach((select) => {
        campusFilterOptionCache.set(
            select.id,
            Array.from(select.querySelectorAll('option')).map((option) => ({
                value: option.value,
                label: option.textContent,
                campusId: option.dataset.campusId ?? '',
                selected: option.selected,
            })),
        );
    });

    const filterCampusLinkedSelects = (campusId) => {
        document.querySelectorAll('[data-campus-filter]').forEach((select) => {
            const allOptions = campusFilterOptionCache.get(select.id) ?? [];
            const currentValue = select.value;
            let hasVisibleSelection = false;

            select.replaceChildren();

            allOptions.forEach((optionData) => {
                const isPlaceholder = optionData.value === '';
                const matchesCampus = !optionData.campusId || optionData.campusId === String(campusId);

                if (!isPlaceholder && campusId && !matchesCampus) {
                    return;
                }

                const option = document.createElement('option');
                option.value = optionData.value;
                option.textContent = optionData.label;

                if (optionData.campusId) {
                    option.dataset.campusId = optionData.campusId;
                }

                if (optionData.value === currentValue && (isPlaceholder || matchesCampus)) {
                    option.selected = true;
                    hasVisibleSelection = true;
                }

                select.appendChild(option);
            });

            if (!hasVisibleSelection) {
                select.value = '';
            }

            select.disabled = !campusId;
            refreshSearchableSelect(select);
        });
    };

    document.querySelectorAll('#campus_id, [data-wizard-campus-id]').forEach((element) => {
        if (element.id === 'campus_id') {
            filterCampusLinkedSelects(element.value);
            element.addEventListener('change', () => filterCampusLinkedSelects(element.value));
        } else if (element.dataset.wizardCampusId) {
            filterCampusLinkedSelects(element.dataset.wizardCampusId);
        }
    });

    initSearchableSelects();

    const setEmploymentPanelEnabled = (panel, enabled) => {
        if (!panel) {
            return;
        }

        if (panel.matches('fieldset[data-form-panel-group]')) {
            panel.disabled = !enabled;

            panel.querySelectorAll('select.form-input').forEach((select) => {
                refreshSearchableSelect(select);
            });

            return;
        }

        panel.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !enabled;
        });

        panel.querySelectorAll('select.form-input').forEach((select) => {
            refreshSearchableSelect(select);
        });
    };

    const syncInactiveFormPanelNames = (form) => {
        const isHybrid = Boolean(form.querySelector('[data-employment-hybrid-toggle]')?.checked);

        form.querySelectorAll('[data-form-panel-group]').forEach((panel) => {
            const group = panel.dataset.formPanelGroup ?? '';
            const isActive = (group.endsWith('-single') && !isHybrid) || (group.endsWith('-hybrid') && isHybrid);

            panel.querySelectorAll('[name]').forEach((field) => {
                if (isActive) {
                    if (field.dataset.stashedName) {
                        field.setAttribute('name', field.dataset.stashedName);
                        delete field.dataset.stashedName;
                    }

                    return;
                }

                if (!field.dataset.stashedName && field.name) {
                    field.dataset.stashedName = field.name;
                }

                field.removeAttribute('name');
            });
        });
    };

    const syncSalaryPanels = (scope) => {
        const hybridToggle = scope.querySelector?.('[data-employment-hybrid-toggle]');
        const isHybrid = Boolean(hybridToggle?.checked);

        scope.querySelectorAll?.('[data-employee-salary-root]')?.forEach((root) => {
            const singlePanel = root.querySelector('[data-employee-salary-single-panel]');
            const hybridPanels = root.querySelector('[data-employee-salary-hybrid-panels]');

            singlePanel?.classList.toggle('hidden', isHybrid);
            hybridPanels?.classList.toggle('hidden', !isHybrid);
            setEmploymentPanelEnabled(singlePanel, !isHybrid);
            setEmploymentPanelEnabled(hybridPanels, isHybrid);
        });
    };

    const getSalaryTemplateMarkup = (template) => {
        if (!template) {
            return '';
        }

        if (template.content.childNodes.length > 0) {
            const wrapper = document.createElement('tbody');
            wrapper.appendChild(template.content.cloneNode(true));

            return wrapper.innerHTML;
        }

        return template.innerHTML;
    };

    document.querySelectorAll('[data-employment-information-root]').forEach((root) => {
        const hybridToggle = root.querySelector('[data-employment-hybrid-toggle]');
        const singlePanel = root.querySelector('[data-employment-single-panel]');
        const hybridPanels = root.querySelector('[data-employment-hybrid-panels]');
        const form = root.closest('form');

        const syncEmploymentPanels = () => {
            const isHybrid = Boolean(hybridToggle?.checked);

            if (form) {
                form.dataset.isHybrid = isHybrid ? '1' : '0';
            }

            singlePanel?.classList.toggle('hidden', isHybrid);
            hybridPanels?.classList.toggle('hidden', !isHybrid);

            setEmploymentPanelEnabled(singlePanel, !isHybrid);
            setEmploymentPanelEnabled(hybridPanels, isHybrid);
        };

        hybridToggle?.addEventListener('change', () => {
            if (hybridToggle.dataset.employmentHybridReload === '1' && form && !form.dataset.employeeWizardForm) {
                const url = new URL(window.location.href);
                url.searchParams.set('is_hybrid', hybridToggle.checked ? '1' : '0');
                url.searchParams.set('tab', form.querySelector('[data-employee-active-tab]')?.value || 'employment');
                window.location.href = url.toString();

                return;
            }

            syncEmploymentPanels();
            syncSalaryPanels(hybridToggle.closest('form') || document);
        });

        syncEmploymentPanels();
        syncSalaryPanels(hybridToggle?.closest('form') || document);
    });

    const enableEmployeeFormPanelsForSubmit = (form) => {
        const isHybrid = Boolean(form.querySelector('[data-employment-hybrid-toggle]')?.checked);

        setEmploymentPanelEnabled(form.querySelector('[data-employment-single-panel]'), !isHybrid);
        setEmploymentPanelEnabled(form.querySelector('[data-employment-hybrid-panels]'), isHybrid);
        setEmploymentPanelEnabled(form.querySelector('[data-employee-salary-single-panel]'), !isHybrid);
        setEmploymentPanelEnabled(form.querySelector('[data-employee-salary-hybrid-panels]'), isHybrid);
        syncInactiveFormPanelNames(form);
    };

    const prepareEmployeeFormForSubmit = (form) => {
        enableEmployeeFormPanelsForSubmit(form);

        form.querySelectorAll('[data-employee-tab-panel].hidden [required]').forEach((field) => {
            field.removeAttribute('required');
        });

        form.querySelectorAll('[data-salary-subtab-panel].hidden [required]').forEach((field) => {
            field.removeAttribute('required');
        });

        form.querySelectorAll(
            '[data-employee-salary-single-panel].hidden [required], [data-employee-salary-hybrid-panels].hidden [required]',
        ).forEach((field) => {
            field.removeAttribute('required');
        });

        form.querySelectorAll(
            '[data-employment-single-panel].hidden [required], [data-employment-hybrid-panels].hidden [required]',
        ).forEach((field) => {
            field.removeAttribute('required');
        });
    };

    const focusEmployeeFormValidationError = (form) => {
        const invalidField = form.querySelector(':invalid');

        if (!invalidField) {
            return;
        }

        const tabPanel = invalidField.closest('[data-employee-tab-panel]');

        if (tabPanel && tabPanel.classList.contains('hidden')) {
            const tabId = tabPanel.dataset.employeeTabPanel;
            const tabButton = form.querySelector(`[data-employee-tab="${tabId}"]`);
            tabButton?.click();
        }

        const salarySubtabPanel = invalidField.closest('[data-salary-subtab-panel]');

        if (salarySubtabPanel?.classList.contains('hidden')) {
            const subtabId = salarySubtabPanel.dataset.salarySubtabPanel;
            const subtabButton = salarySubtabPanel
                .closest('[data-employee-salary-panel]')
                ?.querySelector(`[data-salary-subtab="${subtabId}"]`);
            subtabButton?.click();
        }

        window.requestAnimationFrame(() => {
            invalidField.focus({ preventScroll: false });
            invalidField.reportValidity();
        });
    };

    const bindEmployeeFormSubmitPrep = (form) => {
        if (form.dataset.employeeFormSubmitBound === 'true') {
            return;
        }

        form.dataset.employeeFormSubmitBound = 'true';

        form.addEventListener('submit', () => {
            prepareEmployeeFormForSubmit(form);
        });
    };

    document.querySelectorAll('#employee-form, [data-employee-form]').forEach(bindEmployeeFormSubmitPrep);

    const formatHourlyRate = (value) => {
        if (value === null || Number.isNaN(value)) {
            return '—';
        }

        return value.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    const syncEmployeeSalaryDaysPerPeriod = (panel, previousPayTypeId = null) => {
        const payTypeSelect = panel.querySelector('[data-salary-pay-type]');
        const daysInput = panel.querySelector('[data-salary-days-per-period]');
        const requiredIndicator = panel.querySelector('[data-salary-days-required-indicator]');
        const dailyPayTypeId = panel.dataset.payTypeDaily;

        if (!payTypeSelect || !daysInput || !dailyPayTypeId) {
            return;
        }

        const isDaily = payTypeSelect.value === dailyPayTypeId;
        const switchedFromDaily = previousPayTypeId === dailyPayTypeId && ! isDaily;

        if (isDaily) {
            daysInput.value = '1';
            daysInput.readOnly = true;
            daysInput.required = false;
            daysInput.classList.add('bg-gray-50');
            requiredIndicator?.classList.add('hidden');
        } else {
            if (switchedFromDaily) {
                daysInput.value = '';
            }

            daysInput.readOnly = false;
            daysInput.required = true;
            daysInput.classList.remove('bg-gray-50');
            requiredIndicator?.classList.remove('hidden');
        }
    };

    const syncEmployeeSalaryHourlyRate = (panel) => {
        const output = panel.querySelector('[data-salary-hourly-rate]');
        const hint = panel.querySelector('[data-salary-hourly-rate-hint]');
        const basicIncomeTypeId = panel.dataset.basicIncomeTypeId;
        const daysInput = panel.querySelector('[data-salary-days-per-period]');
        const hoursInput = panel.querySelector('[name*="[hours_per_day]"]');
        const useBasicIncomeCheckbox = panel.querySelector('[data-salary-use-basic-income-as-hourly-rate]');
        const useBasicIncomeAsHourlyRate = useBasicIncomeCheckbox?.checked ?? false;

        if (!output || !basicIncomeTypeId) {
            return;
        }

        let basicIncomeAmount = 0;

        panel.querySelectorAll('[data-salary-income-row]').forEach((row) => {
            const incomeTypeSelect = row.querySelector('select[name*="[income_type_id]"]');

            if (!incomeTypeSelect || incomeTypeSelect.value !== basicIncomeTypeId) {
                return;
            }

            const taxable = parseFloat(row.querySelector('[name*="[taxable]"]')?.value || '0') || 0;
            const nonTaxable = parseFloat(row.querySelector('[name*="[non_taxable]"]')?.value || '0') || 0;
            basicIncomeAmount += taxable + nonTaxable;
        });

        let hourlyRate = null;

        if (useBasicIncomeAsHourlyRate) {
            hourlyRate = basicIncomeAmount > 0 ? basicIncomeAmount : null;
        } else {
            const daysPerPeriod = parseFloat(daysInput?.value || '0') || 0;
            const hoursPerDay = parseFloat(hoursInput?.value || '0') || 0;
            hourlyRate = daysPerPeriod > 0 && hoursPerDay > 0 && basicIncomeAmount > 0
                ? basicIncomeAmount / daysPerPeriod / hoursPerDay
                : null;
        }

        output.value = formatHourlyRate(hourlyRate);

        if (hint) {
            hint.textContent = useBasicIncomeAsHourlyRate
                ? 'Uses Basic Income amount directly.'
                : 'Auto-computed from Basic Income, Days Per Period, and Hours Per Day.';
        }
    };

    const initEmployeeSalaryPanel = (panel) => {
        panel.querySelectorAll('[data-salary-subtabs]').forEach((tabBar) => {
            const buttons = tabBar.querySelectorAll('[data-salary-subtab]');
            const container = tabBar.closest('[data-employee-salary-panel]');

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    const tabId = button.dataset.salarySubtab;

                    buttons.forEach((item) => {
                        item.classList.toggle('employee-salary-subtab-btn-active', item === button);
                    });

                    container?.querySelectorAll('[data-salary-subtab-panel]').forEach((tabPanel) => {
                        tabPanel.classList.toggle('hidden', tabPanel.dataset.salarySubtabPanel !== tabId);
                    });
                });
            });
        });

        const salaryIndex = panel.dataset.salaryIndex;

        panel.querySelector(`[data-salary-add-income="${salaryIndex}"]`)?.addEventListener('click', () => {
            const tbody = panel.querySelector(`[data-salary-income-rows="${salaryIndex}"]`);
            const template = panel.querySelector(`[data-salary-income-template="${salaryIndex}"]`);
            if (!tbody || !template) {
                return;
            }

            const index = tbody.querySelectorAll('[data-salary-income-row]').length;
            const html = getSalaryTemplateMarkup(template).replaceAll('__INDEX__', String(index));
            tbody.insertAdjacentHTML('beforeend', html);
            tbody.querySelectorAll('select.form-input').forEach((select) => refreshSearchableSelect(select));
            syncEmployeeSalaryHourlyRate(panel);
        });

        panel.querySelector(`[data-salary-delete-income="${salaryIndex}"]`)?.addEventListener('click', () => {
            const tbody = panel.querySelector(`[data-salary-income-rows="${salaryIndex}"]`);
            tbody?.querySelectorAll('[data-salary-income-row]').forEach((row) => {
                const checkbox = row.querySelector('[data-salary-income-select]');
                if (checkbox?.checked) {
                    row.remove();
                }
            });
            syncEmployeeSalaryHourlyRate(panel);
        });

        panel.querySelector(`[data-salary-add-deduction="${salaryIndex}"]`)?.addEventListener('click', () => {
            const tbody = panel.querySelector(`[data-salary-deduction-rows="${salaryIndex}"]`);
            const template = panel.querySelector(`[data-salary-deduction-template="${salaryIndex}"]`);
            if (!tbody || !template) {
                return;
            }

            const index = tbody.querySelectorAll('[data-salary-deduction-row]').length;
            const html = getSalaryTemplateMarkup(template).replaceAll('__INDEX__', String(index));
            tbody.insertAdjacentHTML('beforeend', html);
            tbody.querySelectorAll('select.form-input').forEach((select) => refreshSearchableSelect(select));
        });

        panel.querySelector(`[data-salary-delete-deduction="${salaryIndex}"]`)?.addEventListener('click', () => {
            const tbody = panel.querySelector(`[data-salary-deduction-rows="${salaryIndex}"]`);
            tbody?.querySelectorAll('[data-salary-deduction-row]').forEach((row) => {
                const checkbox = row.querySelector('[data-salary-deduction-select]');
                if (checkbox?.checked) {
                    row.remove();
                }
            });
        });

        panel.addEventListener('input', (event) => {
            if (event.target.matches('[name*="[taxable]"], [name*="[non_taxable]"], [name*="[days_per_period]"], [name*="[hours_per_day]"]')) {
                syncEmployeeSalaryHourlyRate(panel);
            }
        });

        panel.addEventListener('change', (event) => {
            if (event.target.matches('[data-salary-pay-type]')) {
                const previousPayTypeId = event.target.dataset.lastValue || null;

                syncEmployeeSalaryDaysPerPeriod(panel, previousPayTypeId);
                event.target.dataset.lastValue = event.target.value;
                syncEmployeeSalaryHourlyRate(panel);
            }

            if (event.target.matches('select[name*="[income_type_id]"]')) {
                syncEmployeeSalaryHourlyRate(panel);
            }

            if (event.target.matches('[data-salary-use-basic-income-as-hourly-rate]')) {
                syncEmployeeSalaryHourlyRate(panel);
            }
        });

        const payTypeSelect = panel.querySelector('[data-salary-pay-type]');

        if (payTypeSelect) {
            payTypeSelect.dataset.lastValue = payTypeSelect.value;
        }

        syncEmployeeSalaryDaysPerPeriod(panel);
        syncEmployeeSalaryHourlyRate(panel);
    };

    document.querySelectorAll('[data-employee-salary-panel]').forEach((panel) => {
        initEmployeeSalaryPanel(panel);
    });

    document.querySelectorAll('[data-employee-form-tabs]').forEach((form) => {
        const activeTabInput = form.querySelector('[data-employee-active-tab]');
        const tabButtons = form.querySelectorAll('[data-employee-tab]');
        const tabPanels = form.querySelectorAll('[data-employee-tab-panel]');

        const activateTab = (tabId) => {
            tabButtons.forEach((button) => {
                const isActive = button.dataset.employeeTab === tabId;
                button.classList.toggle('employee-tab-btn-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            tabPanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.employeeTabPanel !== tabId);
            });

            if (activeTabInput) {
                activeTabInput.value = tabId;
            }
        };

        tabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                activateTab(button.dataset.employeeTab);
            });
        });

        const complianceSelect = form.querySelector('[data-compliance-status-select]');
        const complianceBanner = document.querySelector('[data-employee-compliance-banner]');

        const syncComplianceBanner = () => {
            if (!complianceSelect || !complianceBanner) {
                return;
            }

            const status = complianceSelect.value || 'pending';
            const label = complianceBanner.querySelector('[data-compliance-status-label]');

            complianceBanner.classList.remove(
                'border-green-200', 'bg-green-50', 'text-green-700',
                'border-red-200', 'bg-red-50', 'text-red-700',
                'border-amber-200', 'bg-amber-50', 'text-amber-800',
            );

            if (status === 'compliant') {
                complianceBanner.classList.add('border-green-200', 'bg-green-50', 'text-green-700');
            } else if (status === 'withheld') {
                complianceBanner.classList.add('border-red-200', 'bg-red-50', 'text-red-700');
            } else {
                complianceBanner.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-800');
            }

            if (label) {
                label.textContent = `Compliance Status: ${status.toUpperCase()}`;
            }
        };

        complianceSelect?.addEventListener('change', syncComplianceBanner);
    });

    const initRoleMembersRoot = (root) => {
        if (!root || root.dataset.roleMembersInitialized === 'true') {
            return;
        }

        root.dataset.roleMembersInitialized = 'true';

        const users = JSON.parse(root.dataset.users || '[]');
        const roleId = root.dataset.roleId ? Number(root.dataset.roleId) : null;
        const tableBody = root.querySelector('[data-role-members-table]');
        const inputsWrap = root.querySelector('[data-role-members-inputs]');
        const addModal = document.getElementById(`${root.dataset.roleMembersId}-add-modal`);
        const picker = addModal?.querySelector('[data-role-members-picker]');
        const selectAll = addModal?.querySelector('[data-role-members-select-all]');
        const selectedCount = addModal?.querySelector('[data-role-members-selected-count]');
        const addConfirm = addModal?.querySelector('[data-role-members-add-confirm]');
        const addOpenButton = root.querySelector(`[data-role-members-add-open="${root.dataset.roleMembersId}-add-modal"]`);

        const escapeHtml = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');

        const getSelectedMemberIds = () => {
            return [...inputsWrap.querySelectorAll('[data-member-id]')].map((input) => Number(input.dataset.memberId));
        };

        const getAvailableUsers = () => {
            const selectedIds = getSelectedMemberIds();

            return users.filter((user) => !selectedIds.includes(user.id));
        };

        const syncEmptyRow = () => {
            const hasRows = tableBody.querySelector('[data-role-member-row]');

            tableBody.querySelector('[data-role-members-empty]')?.remove();

            if (!hasRows) {
                tableBody.insertAdjacentHTML(
                    'beforeend',
                    '<tr data-role-members-empty><td colspan="3" class="py-8 text-center text-sm text-gray-500">No members added yet. Click "Add Members" to assign users.</td></tr>',
                );
            }
        };

        const renderPicker = () => {
            if (!picker) {
                return;
            }

            const availableUsers = getAvailableUsers();
            picker.innerHTML = '';

            if (availableUsers.length === 0) {
                picker.innerHTML = '<p class="px-4 py-8 text-center text-sm text-gray-500">All available users are already assigned to this role.</p>';
                selectAll.checked = false;
                selectAll.disabled = true;
                addConfirm.disabled = true;
                selectedCount.textContent = '0 selected';

                return;
            }

            selectAll.disabled = false;

            availableUsers.forEach((user) => {
                const otherRoles = user.role_names?.length
                    ? `<span class="mt-0.5 block text-[10px] text-amber-600">Other roles: ${escapeHtml(user.role_names.join(', '))}</span>`
                    : '';

                picker.insertAdjacentHTML(
                    'beforeend',
                    `<label class="role-members-option">
                        <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" data-role-member-pick="${user.id}">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-gray-900">${escapeHtml(user.name)}</span>
                            <span class="block truncate text-xs text-gray-500">${escapeHtml(user.email)}</span>
                            ${otherRoles}
                        </span>
                    </label>`,
                );
            });

            syncPickerSelectionState();
        };

        const syncPickerSelectionState = () => {
            const picks = [...picker.querySelectorAll('[data-role-member-pick]')];
            const checked = picks.filter((input) => input.checked);

            selectedCount.textContent = `${checked.length} selected`;
            addConfirm.disabled = checked.length === 0;
            selectAll.checked = picks.length > 0 && checked.length === picks.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < picks.length;
        };

        const addMembers = (memberIds) => {
            memberIds.forEach((memberId) => {
                const user = users.find((entry) => entry.id === memberId);

                if (!user) {
                    return;
                }

                tableBody.querySelector('[data-role-members-empty]')?.remove();

                tableBody.insertAdjacentHTML(
                    'beforeend',
                    `<tr data-role-member-row="${user.id}">
                        <td class="font-medium text-gray-900">${escapeHtml(user.name)}</td>
                        <td class="text-gray-600">${escapeHtml(user.email)}</td>
                        <td class="text-right">
                            <button type="button" class="btn-icon text-red-500 hover:bg-red-50 hover:text-red-600" data-role-member-remove="${user.id}" title="Remove">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </td>
                    </tr>`,
                );

                inputsWrap.insertAdjacentHTML(
                    'beforeend',
                    `<input type="hidden" name="member_ids[]" value="${user.id}" data-member-id="${user.id}">`,
                );
            });

            syncEmptyRow();
        };

        addOpenButton?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            renderPicker();
            openModal(addModal, { stack: true });
        });

        selectAll?.addEventListener('change', () => {
            picker.querySelectorAll('[data-role-member-pick]').forEach((input) => {
                input.checked = selectAll.checked;
            });

            syncPickerSelectionState();
        });

        picker?.addEventListener('change', (event) => {
            if (event.target.matches('[data-role-member-pick]')) {
                syncPickerSelectionState();
            }
        });

        addConfirm?.addEventListener('click', () => {
            const memberIds = [...picker.querySelectorAll('[data-role-member-pick]:checked')].map((input) => Number(input.dataset.roleMemberPick));

            addMembers(memberIds);
            closeModal(addModal);
        });

        tableBody?.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-role-member-remove]');

            if (!removeButton) {
                return;
            }

            const memberId = removeButton.dataset.roleMemberRemove;

            tableBody.querySelector(`[data-role-member-row="${memberId}"]`)?.remove();
            inputsWrap.querySelector(`[data-member-id="${memberId}"]`)?.remove();
            syncEmptyRow();
        });
    };

    document.querySelectorAll('[data-role-members-root]').forEach(initRoleMembersRoot);

    const reindexTcfCustomRows = (tbody) => {
        tbody.querySelectorAll('[data-tcf-custom-row]').forEach((row, index) => {
            row.querySelectorAll('input').forEach((input) => {
                const name = input.getAttribute('name') ?? '';
                if (name.startsWith('custom_fields[')) {
                    input.name = name.replace(/custom_fields\[\d+\]/, `custom_fields[${index}]`);
                }
            });
        });
    };

    const syncTimeCaptureFormatPanels = (form) => {
        const sameColumn = form.querySelector('[data-tcf-same-column-toggle]')?.checked ?? false;
        const reasonEnabled = form.querySelector('[data-tcf-reason-toggle]')?.checked ?? false;
        const timeOutRow = form.querySelector('[data-tcf-time-out-row]');
        const timeOutToggle = form.querySelector('[data-tcf-time-out-toggle]');
        const timeOutColumn = form.querySelector('[data-tcf-time-out-column]');
        const timeInType = form.querySelector('[data-tcf-time-in-type]');
        const timeInColumn = form.querySelector('[data-tcf-time-in-column]');
        const timeInRow = form.querySelector('[data-tcf-time-in-row]');
        const indicatorFields = form.querySelector('[data-tcf-indicator-fields]');
        const reasonColumn = form.querySelector('[data-tcf-reason-column]');

        const worktimeColumn = form.querySelector('[data-tcf-worktime-column]');
        const indicatorColumn = form.querySelector('[data-tcf-indicator-column]');
        const timeInIdentifier = form.querySelector('[data-tcf-time-in-identifier]');
        const timeOutIdentifier = form.querySelector('[data-tcf-time-out-identifier]');

        if (reasonColumn) {
            reasonColumn.disabled = !reasonEnabled;
        }

        if (sameColumn) {
            timeOutToggle.checked = false;
            timeOutToggle.disabled = true;
            timeOutColumn.disabled = true;
            timeOutColumn.value = '';
            timeInRow?.classList.add('opacity-50');

            if (timeInType) {
                timeInType.value = '';
                timeInType.disabled = true;
            }

            if (timeInColumn) {
                timeInColumn.value = '';
                timeInColumn.disabled = true;
                timeInColumn.removeAttribute('required');
            }

            indicatorFields?.classList.remove('hidden');
            worktimeColumn.disabled = false;
            indicatorColumn.disabled = false;
            timeInIdentifier.disabled = false;
            timeOutIdentifier.disabled = false;

            if (!worktimeColumn.value) {
                worktimeColumn.value = '3';
            }

            if (!timeInIdentifier.value) {
                timeInIdentifier.value = '1';
            }

            if (!timeOutIdentifier.value) {
                timeOutIdentifier.value = '0';
            }
        } else {
            timeOutToggle.disabled = false;
            timeOutColumn.disabled = !timeOutToggle.checked;
            timeInRow?.classList.remove('opacity-50');

            if (timeInType) {
                if (!timeInType.value) {
                    timeInType.value = 'time_in';
                }
                timeInType.disabled = false;
            }

            if (timeInColumn) {
                timeInColumn.disabled = false;
                timeInColumn.setAttribute('required', 'required');
            }

            indicatorFields?.classList.add('hidden');
            worktimeColumn.disabled = true;
            indicatorColumn.disabled = true;
            timeInIdentifier.disabled = true;
            timeOutIdentifier.disabled = true;
            worktimeColumn.value = '';
            indicatorColumn.value = '';
            timeInIdentifier.value = '';
            timeOutIdentifier.value = '';
        }
    };

    const initTimeCaptureFormatForm = (form) => {
        const tbody = form.querySelector('[data-tcf-custom-rows]');
        const template = form.querySelector('[data-tcf-custom-template]');

        form.querySelector('[data-tcf-same-column-toggle]')?.addEventListener('change', () => syncTimeCaptureFormatPanels(form));
        form.querySelector('[data-tcf-reason-toggle]')?.addEventListener('change', () => syncTimeCaptureFormatPanels(form));
        form.querySelector('[data-tcf-time-out-toggle]')?.addEventListener('change', () => syncTimeCaptureFormatPanels(form));

        form.querySelector('[data-tcf-custom-add]')?.addEventListener('click', () => {
            if (!tbody || !template) {
                return;
            }

            const index = tbody.querySelectorAll('[data-tcf-custom-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            tbody.insertAdjacentHTML('beforeend', html);
            reindexTcfCustomRows(tbody);
        });

        form.querySelector('[data-tcf-custom-remove]')?.addEventListener('click', () => {
            if (!tbody) {
                return;
            }

            const rows = tbody.querySelectorAll('[data-tcf-custom-row]');
            if (rows.length <= 1) {
                rows[0]?.querySelectorAll('input[type="text"], input[type="number"]').forEach((input) => {
                    input.value = '';
                });

                return;
            }

            rows[rows.length - 1].remove();
            reindexTcfCustomRows(tbody);
        });

        syncTimeCaptureFormatPanels(form);
        if (tbody) {
            reindexTcfCustomRows(tbody);
        }
    };

    const initTimekeepingTemplateForm = (form) => {
        const typeSelect = form.querySelector('[data-tkt-template-type]');
        const contentField = form.querySelector('[data-tkt-content]');
        const placeholderList = form.querySelector('[data-tkt-placeholders]');
        const placeholderHint = form.querySelector('[data-tkt-placeholder-hint]');

        if (!typeSelect || !contentField || !placeholderList) {
            return;
        }

        let placeholderMap = {};

        try {
            placeholderMap = JSON.parse(form.dataset.tktPlaceholderMap || '{}');
        } catch {
            placeholderMap = {};
        }

        const renderPlaceholders = (typeId, clearContent = false) => {
            const tokens = placeholderMap[String(typeId)] || [];

            placeholderList.innerHTML = '';

            if (!typeId || tokens.length === 0) {
                placeholderList.hidden = true;
                placeholderHint?.removeAttribute('hidden');

                return;
            }

            placeholderList.hidden = false;
            placeholderHint?.setAttribute('hidden', 'hidden');

            tokens.forEach((token) => {
                const item = document.createElement('li');
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'w-full rounded px-1 py-0.5 text-left hover:bg-white hover:underline';
                button.dataset.tktInsertPlaceholder = `[${token}]`;
                button.textContent = `[${token}]`;
                button.addEventListener('click', () => {
                    contentField.focus();
                    contentField.value += `[${token}]`;
                });

                item.appendChild(button);
                placeholderList.appendChild(item);
            });

            if (clearContent) {
                contentField.value = '';
            }
        };

        typeSelect.addEventListener('change', () => {
            renderPlaceholders(typeSelect.value, true);
        });

        renderPlaceholders(typeSelect.value, false);
    };

    const initDualListSelect = (root) => {
        if (root.dataset.dlInitialized === '1') {
            return;
        }

        const available = root.querySelector('[data-dl-available]');
        const selected = root.querySelector('[data-dl-selected]');
        const hiddenInputs = root.querySelector('[data-dl-hidden-inputs]');
        const inputName = root.dataset.dlInputName;

        if (!available || !selected || !hiddenInputs || !inputName) {
            return;
        }

        root.dataset.dlInitialized = '1';

        const syncHiddenInputs = () => {
            hiddenInputs.innerHTML = '';

            Array.from(selected.options).forEach((option) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName;
                input.value = option.value;
                hiddenInputs.appendChild(input);
            });
        };

        const notifyChange = () => {
            root.dispatchEvent(new CustomEvent('dual-list:change', { bubbles: true, detail: { inputName } }));
        };

        const appendOptionToAvailable = (targetAvailable, option) => {
            const groupLabel = option.dataset.dlGroup;

            if (groupLabel) {
                let group = Array.from(targetAvailable.children).find(
                    (node) => node.tagName === 'OPTGROUP' && node.label === groupLabel,
                );

                if (!group) {
                    group = document.createElement('optgroup');
                    group.label = groupLabel;
                    targetAvailable.appendChild(group);
                }

                group.appendChild(option);

                return;
            }

            targetAvailable.appendChild(option);
        };

        const transferOptions = (from, to) => {
            Array.from(from.selectedOptions).forEach((option) => {
                if (to === available) {
                    appendOptionToAvailable(available, option);
                } else {
                    to.appendChild(option);
                }
            });

            syncHiddenInputs();
            notifyChange();
        };

        root.querySelector('[data-dl-add]')?.addEventListener('click', () => transferOptions(available, selected));
        root.querySelector('[data-dl-remove]')?.addEventListener('click', () => transferOptions(selected, available));

        const form = root.closest('form');
        form?.addEventListener('submit', syncHiddenInputs);
        syncHiddenInputs();
    };

    const initPayrollCalendarForm = (form) => {
        const collegesWrap = form.querySelector('[data-pc-colleges-wrap]');
        const userTypesRoot = form.querySelector('[data-pc-user-types-root]');

        if (!collegesWrap || !userTypesRoot) {
            return;
        }

        const collegesDualList = collegesWrap.querySelector('[data-dual-list-select]');
        const requiredMarker = collegesWrap.querySelector('[data-dl-required-marker]');
        const hint = collegesWrap.querySelector('[data-dl-hint]');

        const syncCollegesRequired = () => {
            const selected = userTypesRoot.querySelector('[data-dl-selected]');
            const values = selected ? Array.from(selected.options).map((option) => option.value) : [];
            const adminOnly = values.length === 1 && values[0] === 'admin';

            requiredMarker?.classList.toggle('hidden', adminOnly);

            if (hint) {
                hint.textContent = adminOnly
                    ? 'Colleges are not used for Admin-only pay periods.'
                    : 'Select colleges covered by this pay period (all campuses for each college).';
            }

            collegesWrap.querySelectorAll('[data-dl-available], [data-dl-selected]').forEach((select) => {
                select.disabled = adminOnly;
            });
            collegesWrap.querySelectorAll('[data-dl-add], [data-dl-remove]').forEach((button) => {
                button.disabled = adminOnly;
            });
            collegesDualList?.classList.toggle('opacity-60', adminOnly);
            collegesDualList?.classList.toggle('pointer-events-none', adminOnly);

            if (adminOnly) {
                const collegesAvailable = collegesWrap.querySelector('[data-dl-available]');
                const collegesSelected = collegesWrap.querySelector('[data-dl-selected]');
                const hiddenInputs = collegesWrap.querySelector('[data-dl-hidden-inputs]');

                if (collegesAvailable && collegesSelected && collegesSelected.options.length > 0) {
                    Array.from(collegesSelected.options).forEach((option) => {
                        const groupLabel = option.dataset.dlGroup;

                        if (groupLabel) {
                            let group = Array.from(collegesAvailable.children).find(
                                (node) => node.tagName === 'OPTGROUP' && node.label === groupLabel,
                            );

                            if (!group) {
                                group = document.createElement('optgroup');
                                group.label = groupLabel;
                                collegesAvailable.appendChild(group);
                            }

                            group.appendChild(option);

                            return;
                        }

                        collegesAvailable.appendChild(option);
                    });

                    if (hiddenInputs) {
                        hiddenInputs.innerHTML = '';
                    }
                }
            }
        };

        userTypesRoot.addEventListener('dual-list:change', syncCollegesRequired);
        syncCollegesRequired();
    };

    const payrollBatchFormState = new WeakMap();

    const parsePayrollBatchFormData = (form) => {
        if (payrollBatchFormState.has(form)) {
            return payrollBatchFormState.get(form);
        }

        const data = {
            yearsByPayType: JSON.parse(form.getAttribute('data-pb-years') || '{}'),
            periodsByPayType: JSON.parse(form.getAttribute('data-pb-periods') || '{}'),
            defaults: {},
        };

        const defaultsNode = form.querySelector('[data-pb-defaults]');

        if (defaultsNode) {
            try {
                data.defaults = JSON.parse(defaultsNode.textContent || '{}');
            } catch {
                data.defaults = {};
            }
        }

        payrollBatchFormState.set(form, data);

        return data;
    };

    const payrollBatchUniqueYears = (yearsByPayType, payTypeId) => [...new Set(
        (yearsByPayType[payTypeId] || yearsByPayType[String(payTypeId)] || []).map((year) => Number(year)),
    )]
        .filter((year) => Number.isFinite(year))
        .sort((left, right) => right - left);

    const payrollBatchPeriodsFor = (periodsByPayType, payTypeId, payYear) => periodsByPayType[payTypeId]?.[payYear]
        || periodsByPayType[payTypeId]?.[String(payYear)]
        || periodsByPayType[String(payTypeId)]?.[payYear]
        || periodsByPayType[String(payTypeId)]?.[String(payYear)]
        || [];

    const updatePayrollUploadTemplateLink = (form) => {
        const link = form.querySelector('[data-payroll-upload-template-link]');
        const payPeriodSelect = form.querySelector('[data-pb-pay-period]');
        const baseUrl = link?.dataset.payrollUploadTemplateBase;

        if (!link || !baseUrl) {
            return;
        }

        const calendarId = payPeriodSelect?.value;

        link.href = calendarId
            ? `${baseUrl}?payroll_calendar_id=${encodeURIComponent(calendarId)}`
            : baseUrl;
    };

    const fillPayrollBatchSelect = (select, options, selectedValue, emptyLabel) => {
        if (!select) {
            return;
        }

        select.innerHTML = '';

        if (!options.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = emptyLabel;
            select.appendChild(option);
            select.value = '';
            select.disabled = true;
            select.removeAttribute('required');
            refreshSearchableSelect(select);

            return;
        }

        select.disabled = false;
        select.setAttribute('required', 'required');

        let hasSelection = false;

        options.forEach(({ value, label }) => {
            const option = document.createElement('option');
            option.value = String(value);
            option.textContent = label;

            if (String(value) === String(selectedValue)) {
                option.selected = true;
                hasSelection = true;
            }

            select.appendChild(option);
        });

        if (!hasSelection && select.options.length > 0) {
            select.options[0].selected = true;
        }

        refreshSearchableSelect(select);
    };

    const syncPayrollBatchPeriod = (form) => {
        const payTypeSelect = form.querySelector('[data-pb-pay-type]');
        const payYearSelect = form.querySelector('[data-pb-pay-year]');
        const payPeriodSelect = form.querySelector('[data-pb-pay-period]');

        if (!payTypeSelect || !payYearSelect || !payPeriodSelect) {
            return;
        }

        const { periodsByPayType, defaults } = parsePayrollBatchFormData(form);
        const payTypeId = payTypeSelect.value;
        const payYear = payYearSelect.value;
        const hasPayYear = payYear !== '' && !payYearSelect.disabled;
        const periods = hasPayYear
            ? payrollBatchPeriodsFor(periodsByPayType, payTypeId, payYear)
            : [];
        const selectedPeriod = periods.some((period) => String(period.id) === String(defaults.payrollCalendarId))
            ? defaults.payrollCalendarId
            : periods[0]?.id;

        fillPayrollBatchSelect(
            payPeriodSelect,
            periods.map((period) => ({ value: period.id, label: period.label })),
            selectedPeriod,
            'No pay period defined',
        );

        defaults.payrollCalendarId = null;

        if (form.matches('[data-payroll-upload-form]')) {
            updatePayrollUploadTemplateLink(form);
        }
    };

    const syncPayrollBatchYearAndPeriod = (form) => {
        const payTypeSelect = form.querySelector('[data-pb-pay-type]');
        const payYearSelect = form.querySelector('[data-pb-pay-year]');
        const payPeriodSelect = form.querySelector('[data-pb-pay-period]');

        if (!payTypeSelect || !payYearSelect) {
            return;
        }

        fillPayrollBatchSelect(payPeriodSelect, [], null, 'No pay period defined');

        const { yearsByPayType, defaults } = parsePayrollBatchFormData(form);
        const payTypeId = payTypeSelect.value;
        const years = payrollBatchUniqueYears(yearsByPayType, payTypeId);
        const selectedYear = years.includes(Number(defaults.payYear)) && payTypeId === String(defaults.payTypeId)
            ? defaults.payYear
            : years[0];

        fillPayrollBatchSelect(
            payYearSelect,
            years.map((year) => ({ value: year, label: String(year) })),
            selectedYear,
            'No pay year defined',
        );

        defaults.payYear = null;
        syncPayrollBatchPeriod(form);
    };

    const initPayrollBatchForm = (form) => {
        if (!form.querySelector('[data-pb-pay-type]')) {
            return;
        }

        try {
            parsePayrollBatchFormData(form);
        } catch {
            return;
        }

        syncPayrollBatchYearAndPeriod(form);
    };

    document.addEventListener('change', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLSelectElement)) {
            return;
        }

        const form = target.closest('[data-payroll-batch-form]');

        if (!form) {
            return;
        }

        if (target.matches('[data-pb-pay-type]')) {
            syncPayrollBatchYearAndPeriod(form);
        } else if (target.matches('[data-pb-pay-year]')) {
            syncPayrollBatchPeriod(form);
        }
    });

    document.addEventListener('input', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLSelectElement)) {
            return;
        }

        const form = target.closest('[data-payroll-batch-form]');

        if (!form) {
            return;
        }

        if (target.matches('[data-pb-pay-type]')) {
            syncPayrollBatchYearAndPeriod(form);
        } else if (target.matches('[data-pb-pay-year]')) {
            syncPayrollBatchPeriod(form);
        }
    });

    const initHolidayGroupForm = (form) => {
        form.querySelectorAll('[data-dual-list-select]').forEach(initDualListSelect);
    };

    const reinitLiveTableContent = (container) => {
        if (!container) {
            return;
        }

        container.querySelectorAll('.payroll-maintenance-form').forEach(initPayrollMaintenanceForm);
        container.querySelectorAll('[data-role-members-root]').forEach(initRoleMembersRoot);
        container.querySelectorAll('[data-time-capture-format-form]').forEach(initTimeCaptureFormatForm);
        container.querySelectorAll('[data-timekeeping-template-form]').forEach(initTimekeepingTemplateForm);
        container.querySelectorAll('[data-holiday-group-form]').forEach(initHolidayGroupForm);
        container.querySelectorAll('[data-payroll-calendar-form]').forEach(initPayrollCalendarForm);
        container.querySelectorAll('[data-payroll-batch-form]').forEach(initPayrollBatchForm);
        container.querySelectorAll('[data-dual-list-select]').forEach(initDualListSelect);
        initSearchableSelects(container);
    };

    const getPayrollMaintenanceFieldValue = (form, fieldName) => {
        const checkbox = form.querySelector(`input[type="checkbox"][name="${CSS.escape(fieldName)}"]`);

        if (checkbox instanceof HTMLInputElement) {
            return checkbox.checked ? '1' : '0';
        }

        const field = form.querySelector(`[name="${CSS.escape(fieldName)}"]`);

        return field instanceof HTMLInputElement || field instanceof HTMLSelectElement
            ? field.value
            : '';
    };

    const syncPayrollMaintenanceField = (form, fieldWrap) => {
        const dependsOn = fieldWrap.dataset.dependsOn;
        const dependsValue = fieldWrap.dataset.dependsValue;

        if (!dependsOn) {
            fieldWrap.classList.remove('opacity-50');
            fieldWrap.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = false;
            });

            return;
        }

        const currentValue = getPayrollMaintenanceFieldValue(form, dependsOn);
        const expected = dependsValue === 'true' ? '1' : dependsValue === 'false' ? '0' : dependsValue;
        const enabled = currentValue === expected;

        fieldWrap.classList.toggle('opacity-50', !enabled);
        fieldWrap.querySelectorAll('input, select, textarea').forEach((input) => {
            input.disabled = !enabled;
        });
    };

    const initPayrollMaintenanceForm = (form) => {
        form.querySelectorAll('.payroll-maintenance-field[data-depends-on]').forEach((fieldWrap) => {
            syncPayrollMaintenanceField(form, fieldWrap);
        });

        form.addEventListener('change', (event) => {
            const target = event.target;

            if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement)) {
                return;
            }

            form.querySelectorAll('.payroll-maintenance-field[data-depends-on]').forEach((fieldWrap) => {
                if (fieldWrap.dataset.dependsOn === target.name) {
                    syncPayrollMaintenanceField(form, fieldWrap);
                }
            });
        });
    };

    document.querySelectorAll('.payroll-maintenance-form').forEach(initPayrollMaintenanceForm);

    document.addEventListener('click', (event) => {
        const modalOpen = event.target.closest('[data-modal-open]');

        if (!modalOpen) {
            return;
        }

        window.setTimeout(() => {
            const modalId = modalOpen.getAttribute('data-modal-open');
            const modal = modalId ? document.getElementById(modalId) : null;

            modal?.querySelectorAll('.payroll-maintenance-form').forEach(initPayrollMaintenanceForm);
            modal?.querySelectorAll('[data-payroll-batch-form]').forEach(initPayrollBatchForm);
            modal?.querySelectorAll('[data-payroll-upload-form]').forEach(syncPayrollUploadTemplateLink);
            syncPayrollBatchRemoveSelection(modal ?? document);
            syncPayrollBatchAddSelection(modal ?? document);
        }, 0);
    });

    const initLiveTable = (root) => {
        const baseUrl = root.dataset.liveTableUrl;

        if (!baseUrl) {
            return;
        }

        const searchInput = root.querySelector('[data-live-table-search]');
        const perPageSelect = root.querySelector('[data-live-table-per-page]');
        const totalDisplay = root.querySelector('[data-live-table-total]');
        const results = root.querySelector('[data-live-table-results]');
        const loading = root.querySelector('[data-live-table-loading]');
        const debounceMs = Number(root.dataset.liveTableDebounce || 300);
        const totalLabel = totalDisplay?.dataset.totalLabel || 'records';
        let debounceTimer = null;
        let activeController = null;

        const syncTotalDisplay = () => {
            const totalUpdate = results?.querySelector('[data-live-table-total-update]');

            if (!totalDisplay || !totalUpdate) {
                return;
            }

            const total = Number(totalUpdate.dataset.total || 0).toLocaleString();
            totalDisplay.textContent = `Total: ${total} ${totalLabel}`;
        };

        const setLoading = (isLoading) => {
            if (!loading) {
                return;
            }

            loading.classList.toggle('hidden', !isLoading);
            loading.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
        };

        const buildUrl = (pageUrl = null) => {
            if (pageUrl) {
                return pageUrl;
            }

            const url = new URL(baseUrl, window.location.origin);
            const searchValue = searchInput?.value ?? '';

            if (searchValue.trim() !== '') {
                url.searchParams.set('search', searchValue.trim());
            } else {
                url.searchParams.delete('search');
            }

            root.querySelectorAll('[data-live-table-filter]').forEach((field) => {
                if (!field.name) {
                    return;
                }

                if (field.value && field.value !== 'all') {
                    url.searchParams.set(field.name, field.value);
                } else {
                    url.searchParams.delete(field.name);
                }
            });

            if (perPageSelect?.value) {
                url.searchParams.set('per_page', perPageSelect.value);
            }

            url.searchParams.delete('page');

            return url.toString();
        };

        const loadResults = async (pageUrl = null) => {
            if (!results) {
                return;
            }

            activeController?.abort();
            activeController = new AbortController();
            setLoading(true);

            try {
                const response = await fetch(buildUrl(pageUrl), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                    signal: activeController.signal,
                });

                if (!response.ok) {
                    throw new Error('Failed to load table results.');
                }

                results.innerHTML = await response.text();
                reinitLiveTableContent(results);
                syncTotalDisplay();

                const displayUrl = pageUrl
                    ? new URL(pageUrl, window.location.origin)
                    : new URL(buildUrl(), window.location.origin);
                window.history.replaceState({}, '', displayUrl.toString());
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                }
            } finally {
                setLoading(false);
            }
        };

        const queueReload = () => {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(() => {
                loadResults();
            }, debounceMs);
        };

        searchInput?.addEventListener('input', queueReload);

        root.querySelectorAll('[data-live-table-filter]').forEach((field) => {
            field.addEventListener('change', queueReload);
        });

        perPageSelect?.addEventListener('change', () => {
            loadResults();
        });

        root.addEventListener('click', (event) => {
            const pageLink = event.target.closest('[data-live-table-page]');

            if (!pageLink || !root.contains(pageLink)) {
                return;
            }

            event.preventDefault();
            loadResults(pageLink.href);
        });
    };

    document.querySelectorAll('[data-live-table]').forEach(initLiveTable);

    const incomeTaxOptionsUrl = document.querySelector('[data-rate-definition-form]')?.dataset.incomeTaxUrl
        || '/payroll/rate-definitions/income-tax-options';

    const syncRateBasisColumns = (form) => {
        const basisSelect = form.querySelector('[data-rate-basis-select]');
        const ratesRoot = form.querySelector('[data-rate-definition-rates]');

        if (!basisSelect || !ratesRoot) {
            return;
        }

        const fixedBasisId = ratesRoot.dataset.fixedBasis || '2';
        const isFixed = basisSelect.value === fixedBasisId;

        ratesRoot.querySelectorAll('.rate-definition-cb-col').forEach((cell) => {
            cell.classList.toggle('hidden', isFixed);
        });

        ratesRoot.querySelectorAll('[data-rate-column-label]').forEach((rateLabel) => {
            rateLabel.textContent = isFixed ? 'Amount Per Hour' : 'Rate';
        });
    };

    const syncIncomeTaxSelect = async (incomeSelect) => {
        const targetId = incomeSelect.dataset.incomeTaxTarget;
        const taxableSelect = targetId ? document.getElementById(targetId) : null;

        if (!taxableSelect) {
            return;
        }

        const incomeTypeId = incomeSelect.value;

        if (!incomeTypeId) {
            taxableSelect.innerHTML = '<option value="">—</option>';
            return;
        }

        try {
            const url = new URL(incomeTaxOptionsUrl, window.location.origin);
            url.searchParams.set('income_type_id', incomeTypeId);
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const options = payload.options || {};
            const currentValue = taxableSelect.value;

            taxableSelect.innerHTML = Object.entries(options)
                .map(([value, label]) => `<option value="${value}">${label}</option>`)
                .join('');

            if (currentValue && Object.prototype.hasOwnProperty.call(options, currentValue)) {
                taxableSelect.value = currentValue;
            }
        } catch {
            // Keep existing options when the lookup fails.
        }
    };

    const initRateDefinitionDayTabs = (root) => {
        const tabs = root.querySelectorAll('[data-rate-day-tab]');
        const panels = root.querySelectorAll('[data-rate-day-panel]');

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const dayTypeId = tab.dataset.rateDayTab;

                tabs.forEach((item) => {
                    const isActive = item.dataset.rateDayTab === dayTypeId;
                    item.classList.toggle('bg-[#00A3E6]/10', isActive);
                    item.classList.toggle('font-medium', isActive);
                    item.classList.toggle('text-[#00A3E6]', isActive);
                    item.classList.toggle('text-gray-600', !isActive);
                    item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panels.forEach((panel) => {
                    const isActive = panel.dataset.rateDayPanel === dayTypeId;
                    panel.classList.toggle('hidden', !isActive);
                    panel.classList.toggle('is-active', isActive);
                    panel.hidden = !isActive;
                });
            });
        });
    };

    const initRateDefinitionForm = (form) => {
        form.querySelectorAll('[data-rate-definition-rates]').forEach(initRateDefinitionDayTabs);
        syncRateBasisColumns(form);

        form.querySelector('[data-rate-basis-select]')?.addEventListener('change', () => {
            syncRateBasisColumns(form);
        });

        form.querySelectorAll('.rate-definition-income-type').forEach((select) => {
            select.addEventListener('change', () => {
                syncIncomeTaxSelect(select);
            });
        });
    };

    document.querySelectorAll('[data-rate-definition-form]').forEach(initRateDefinitionForm);

    const syncFlexiFields = (form) => {
        const selected = form.querySelector('input[name="is_allow_flexi_time"]:checked');
        const enabled = selected?.value === '1';

        form.querySelectorAll('[data-flexi-panel]').forEach((panel) => {
            panel.classList.toggle('opacity-50', !enabled);
        });

        form.querySelectorAll('[data-flexi-field]').forEach((field) => {
            field.disabled = !enabled;
            if (! enabled) {
                field.value = '';
            }
        });
    };

    const syncExcessHourFields = (form) => {
        const select = form.querySelector('[data-excess-hour-select]');
        const disabled = select instanceof HTMLSelectElement && select.value === '1';

        form.querySelectorAll('[data-ot-field]').forEach((wrap) => {
            wrap.classList.toggle('opacity-50', disabled);
            wrap.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = disabled;
            });
        });
    };

    const syncBreakTardinessFields = (form) => {
        const enabled = form.querySelector('[data-break-tardiness-toggle]')?.checked ?? false;

        form.querySelectorAll('[data-break-tardiness-panel]').forEach((panel) => {
            panel.classList.toggle('opacity-50', !enabled);
        });

        form.querySelectorAll('[data-break-tardiness-field]').forEach((field) => {
            field.disabled = !enabled;
        });
    };

    const syncRestDayFields = (form) => {
        const enabled = form.querySelector('[data-rest-day-toggle]')?.checked ?? false;

        form.querySelectorAll('[data-rest-day-panel]').forEach((panel) => {
            panel.classList.toggle('opacity-50', !enabled);
        });

        form.querySelectorAll('[data-rest-day-field]').forEach((field) => {
            field.disabled = !enabled;
        });
    };

    const syncNotificationFields = (form) => {
        const enabled = form.querySelector('[data-notification-toggle]')?.checked ?? false;

        form.querySelectorAll('[data-notification-field]').forEach((field) => {
            field.disabled = !enabled;
        });
    };

    const syncToilFields = (form) => {
        const enabled = form.querySelector('[data-toil-toggle]')?.checked ?? false;

        form.querySelectorAll('[data-toil-panel]').forEach((panel) => {
            panel.classList.toggle('opacity-50', !enabled);
        });

        form.querySelectorAll('[data-toil-field]').forEach((field) => {
            field.disabled = !enabled;
        });
    };

    const syncNightDiffFields = (form) => {
        const enabled = form.querySelector('[data-nd-compute-toggle]')?.checked ?? false;

        form.querySelectorAll('[data-nd-compute-panel]').forEach((panel) => {
            panel.classList.toggle('opacity-50', !enabled);
        });

        form.querySelectorAll('[data-nd-time-field]').forEach((field) => {
            field.disabled = !enabled;
            if (!enabled) {
                field.value = '';
            }
        });
    };

    const syncLogsTaggingFields = (form) => {
        const enabled = form.querySelector('[data-logs-tagging-toggle]')?.checked ?? false;

        form.querySelectorAll('[data-logs-tagging-panel]').forEach((panel) => {
            panel.classList.toggle('opacity-50', !enabled);
        });

        form.querySelectorAll('[data-logs-tagging-field]').forEach((field) => {
            field.disabled = !enabled;
        });
    };

    const initTimekeepingPolicyRoot = (root) => {
        root.querySelectorAll('[data-timekeeping-settings]').forEach((form) => {
            syncFlexiFields(form);
            syncExcessHourFields(form);
            syncBreakTardinessFields(form);
            syncNotificationFields(form);
            syncRestDayFields(form);
            syncToilFields(form);
            syncLogsTaggingFields(form);
            syncNightDiffFields(form);

            form.addEventListener('change', (event) => {
                const target = event.target;

                if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement)) {
                    return;
                }

                if (target.matches('[name="is_allow_flexi_time"], [data-flexi-toggle]')) {
                    syncFlexiFields(form);
                }

                if (target.matches('[data-excess-hour-select]')) {
                    syncExcessHourFields(form);
                }

                if (target.matches('[data-break-tardiness-toggle]')) {
                    syncBreakTardinessFields(form);
                }

                if (target.matches('[data-notification-toggle]')) {
                    syncNotificationFields(form);
                }

                if (target.matches('[data-rest-day-toggle]')) {
                    syncRestDayFields(form);
                }

                if (target.matches('[data-toil-toggle]')) {
                    syncToilFields(form);
                }

                if (target.matches('[data-logs-tagging-toggle]')) {
                    syncLogsTaggingFields(form);
                }

                if (target.matches('[data-nd-compute-toggle]')) {
                    syncNightDiffFields(form);
                }
            });
        });
    };

    document.querySelectorAll('[data-timekeeping-policy-root]').forEach(initTimekeepingPolicyRoot);

    const reindexShiftBreakRows = (tbody) => {
        tbody.querySelectorAll('[data-shift-break-row]').forEach((row, index) => {
            const label = row.querySelector('[data-shift-break-label]');
            if (label) {
                label.textContent = `Break ${index + 1}`;
            }

            row.querySelectorAll('input').forEach((input) => {
                const name = input.getAttribute('name') ?? '';
                if (name.includes('[break_minute]')) {
                    input.name = `breaks[${index}][break_minute]`;
                } else if (name.includes('[is_paid_break]')) {
                    input.name = `breaks[${index}][is_paid_break]`;
                }
            });
        });
    };

    const initShiftCodeForm = (form) => {
        const tbody = form.querySelector('[data-shift-break-rows]');
        const template = form.querySelector('[data-shift-break-template]');
        if (!tbody || !template) {
            return;
        }

        form.querySelector('[data-shift-break-add]')?.addEventListener('click', () => {
            const index = tbody.querySelectorAll('[data-shift-break-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            tbody.insertAdjacentHTML('beforeend', html);
            reindexShiftBreakRows(tbody);
        });

        form.querySelector('[data-shift-break-remove]')?.addEventListener('click', () => {
            const rows = tbody.querySelectorAll('[data-shift-break-row]');
            if (rows.length === 0) {
                return;
            }

            rows[rows.length - 1].remove();
            reindexShiftBreakRows(tbody);
        });

        reindexShiftBreakRows(tbody);
    };

    const syncPayrollUploadTemplateLink = (form) => {
        if (form.dataset.payrollUploadTemplateLinkInit === '1') {
            updatePayrollUploadTemplateLink(form);

            return;
        }

        const link = form.querySelector('[data-payroll-upload-template-link]');
        const baseUrl = link?.dataset.payrollUploadTemplateBase;

        if (!link || !baseUrl) {
            return;
        }

        form.dataset.payrollUploadTemplateLinkInit = '1';

        link.addEventListener('click', () => {
            updatePayrollUploadTemplateLink(form);
        });

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-pb-pay-type], [data-pb-pay-year], [data-pb-pay-period]')) {
                if (event.target.matches('[data-pb-pay-type], [data-pb-pay-year]')) {
                    window.setTimeout(() => updatePayrollUploadTemplateLink(form), 0);
                } else {
                    updatePayrollUploadTemplateLink(form);
                }
            }
        });

        updatePayrollUploadTemplateLink(form);
    };

    document.querySelectorAll('[data-shift-code-form]').forEach(initShiftCodeForm);
    document.querySelectorAll('[data-time-capture-format-form]').forEach(initTimeCaptureFormatForm);
    document.querySelectorAll('[data-timekeeping-template-form]').forEach(initTimekeepingTemplateForm);
    document.querySelectorAll('[data-holiday-group-form]').forEach(initHolidayGroupForm);
    document.querySelectorAll('[data-payroll-calendar-form]').forEach(initPayrollCalendarForm);
    document.querySelectorAll('[data-payroll-batch-form]').forEach(initPayrollBatchForm);
    document.querySelectorAll('[data-modal-auto-open] [data-payroll-batch-form]').forEach(initPayrollBatchForm);
    document.querySelectorAll('[data-payroll-upload-form]').forEach(syncPayrollUploadTemplateLink);
    document.querySelectorAll('[data-modal-auto-open] [data-payroll-upload-form]').forEach(syncPayrollUploadTemplateLink);
    document.querySelectorAll('[data-dual-list-select]').forEach(initDualListSelect);

    const syncTimeLogsTemplateLink = (form) => {
        const select = form.querySelector('[data-time-logs-format-select]');
        const link = form.querySelector('[data-time-logs-template-link]');

        if (!select || !link) {
            return;
        }

        const update = () => {
            const option = select.selectedOptions[0];
            const url = option?.dataset.templateUrl;

            if (url) {
                link.href = url;
                link.classList.remove('hidden');
            } else {
                link.href = '#';
                link.classList.add('hidden');
            }
        };

        select.addEventListener('change', update);
        update();
    };

    const syncTimeLogsPurgeSelection = () => {
        const purgeBtn = document.querySelector('[data-time-logs-purge-btn]');
        const countLabel = document.querySelector('[data-time-logs-selected-count]');

        if (!purgeBtn) {
            return;
        }

        const update = () => {
            const checked = document.querySelectorAll('[data-time-logs-row-select]:checked').length;
            purgeBtn.disabled = checked === 0;

            if (countLabel) {
                countLabel.textContent = `${checked} selected`;
            }
        };

        document.addEventListener('change', (event) => {
            if (event.target.matches('[data-time-logs-select-all]')) {
                document.querySelectorAll('[data-time-logs-row-select]').forEach((checkbox) => {
                    checkbox.checked = event.target.checked;
                });
                update();

                return;
            }

            if (event.target.matches('[data-time-logs-row-select]')) {
                update();
            }
        });

        update();
    };

    document.querySelectorAll('[data-time-logs-upload-form]').forEach(syncTimeLogsTemplateLink);
    syncTimeLogsPurgeSelection();

    const syncPayrollUploadPurgeSelection = () => {
        const purgeBtn = document.querySelector('[data-payroll-upload-purge-btn]');
        const countLabel = document.querySelector('[data-payroll-upload-selected-count]');

        if (!purgeBtn) {
            return;
        }

        const update = () => {
            const checked = document.querySelectorAll('[data-payroll-upload-row-select]:checked').length;
            purgeBtn.disabled = checked === 0;

            if (countLabel) {
                countLabel.textContent = `${checked} selected`;
            }
        };

        document.addEventListener('change', (event) => {
            if (event.target.matches('[data-payroll-upload-select-all]')) {
                document.querySelectorAll('[data-payroll-upload-row-select]').forEach((checkbox) => {
                    checkbox.checked = event.target.checked;
                });
                update();
            }

            if (event.target.matches('[data-payroll-upload-row-select]')) {
                update();
            }
        });

        update();
    };

    syncPayrollUploadPurgeSelection();

    const syncPayrollBatchRemoveSelection = (root = document) => {
        const removeBtn = root.querySelector('[data-payroll-batch-remove-btn]');
        const countLabel = root.querySelector('[data-payroll-batch-selected-count]');
        const selectAll = root.querySelector('[data-payroll-batch-select-all]');

        if (!removeBtn) {
            return;
        }

        const rowChecks = () => root.querySelectorAll('[data-payroll-batch-row-select]');

        const update = () => {
            const checked = root.querySelectorAll('[data-payroll-batch-row-select]:checked').length;
            removeBtn.disabled = checked === 0;

            if (countLabel) {
                countLabel.textContent = `${checked} selected`;
            }

            if (selectAll) {
                const rows = rowChecks();
                selectAll.checked = rows.length > 0 && checked === rows.length;
                selectAll.indeterminate = checked > 0 && checked < rows.length;
            }
        };

        selectAll?.addEventListener('change', () => {
            rowChecks().forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            update();
        });

        rowChecks().forEach((checkbox) => {
            checkbox.addEventListener('change', update);
        });

        update();
    };

    const syncPayrollBatchAddSelection = (root = document) => {
        const form = root.querySelector('[data-payroll-batch-add-form]');

        if (!form) {
            return;
        }

        const selectAll = form.querySelector('[data-payroll-batch-add-select-all]');
        const countLabel = form.querySelector('[data-payroll-batch-add-selected-count]');
        const includeAll = form.querySelector('[data-payroll-batch-include-all]');
        const submitBtn = form.querySelector('[data-payroll-batch-add-submit]');
        const rowChecks = () => form.querySelectorAll('[data-payroll-batch-add-row]');

        const update = () => {
            const includeAllChecked = includeAll?.checked ?? false;
            const checked = includeAllChecked
                ? rowChecks().length
                : form.querySelectorAll('[data-payroll-batch-add-row]:checked').length;

            rowChecks().forEach((checkbox) => {
                checkbox.disabled = includeAllChecked;
                if (includeAllChecked) {
                    checkbox.checked = false;
                }
            });

            if (selectAll) {
                selectAll.disabled = includeAllChecked;
                if (includeAllChecked) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else {
                    const rows = rowChecks();
                    selectAll.checked = rows.length > 0 && checked === rows.length;
                    selectAll.indeterminate = checked > 0 && checked < rows.length;
                }
            }

            if (countLabel) {
                countLabel.textContent = includeAllChecked
                    ? 'All eligible employees'
                    : `${checked} selected`;
            }

            if (submitBtn) {
                submitBtn.disabled = !includeAllChecked && checked === 0;
            }
        };

        selectAll?.addEventListener('change', () => {
            rowChecks().forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            update();
        });

        includeAll?.addEventListener('change', update);

        rowChecks().forEach((checkbox) => {
            checkbox.addEventListener('change', update);
        });

        update();
    };

    syncPayrollBatchRemoveSelection();
    syncPayrollBatchAddSelection();

    const syncEmployeeProfileRestDayRow = (restDayCheckbox) => {
        const dayId = restDayCheckbox.dataset.dayId;
        const paidCheckbox = restDayCheckbox
            .closest('tr')
            ?.querySelector(`[data-rest-day-paid="${dayId}"]`);

        if (!paidCheckbox) {
            return;
        }

        if (restDayCheckbox.checked) {
            paidCheckbox.disabled = false;
        } else {
            paidCheckbox.checked = false;
            paidCheckbox.disabled = true;
        }
    };

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-rest-day-checkbox]')) {
            syncEmployeeProfileRestDayRow(event.target);
        }
    });

    document.querySelectorAll('[data-rest-day-checkbox]').forEach(syncEmployeeProfileRestDayRow);

    const loadEmployeeProfileLazyPanel = async (panel) => {
        if (!panel || panel.dataset.loaded === 'true' || !panel.dataset.lazyUrl) {
            return;
        }

        panel.dataset.loaded = 'loading';
        panel.innerHTML = '<div class="py-6 text-center text-sm text-gray-500">Loading…</div>';

        try {
            const response = await fetch(panel.dataset.lazyUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load tab content.');
            }

            panel.innerHTML = await response.text();
            panel.dataset.loaded = 'true';
            delete panel.dataset.lazyPending;
        } catch {
            panel.innerHTML = '<div class="py-6 text-center text-sm text-red-600">Failed to load tab content.</div>';
            panel.dataset.loaded = 'false';
        }
    };

    const initEmployeeProfileSetupForm = (form) => {
        if (form.dataset.employeeProfileSetupInitialized === 'true') {
            return;
        }

        form.dataset.employeeProfileSetupInitialized = 'true';

        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-employee-tab-panel].hidden [required]').forEach((field) => {
                field.removeAttribute('required');
            });
        });

        const maybeLoadLazyTab = (tabId) => {
            const panel = form.querySelector(`[data-employee-tab-panel="${tabId}"][data-employee-profile-lazy-panel]`);

            if (panel) {
                loadEmployeeProfileLazyPanel(panel);
            }
        };

        form.querySelectorAll('[data-employee-tab]').forEach((button) => {
            button.addEventListener('click', () => {
                maybeLoadLazyTab(button.dataset.employeeTab);
            });
        });

        const activeTabInput = form.querySelector('[data-employee-active-tab]');
        if (activeTabInput?.value) {
            maybeLoadLazyTab(activeTabInput.value);
        }
    };

    document.querySelectorAll('[data-employee-profile-setup-form]').forEach(initEmployeeProfileSetupForm);

    const loadEmployeeProfileApprovalRoutes = async (select) => {
        const root = select.closest('[data-employee-profile-approval-root]');
        const container = root?.querySelector('[data-employee-profile-approval-routes]');
        const baseUrl = root?.dataset.routesUrl;

        if (!root || !container || !baseUrl) {
            return;
        }

        const formTypeId = select.value || '0';
        container.innerHTML = '<div class="py-6 text-center text-sm text-gray-500">Loading approval routes…</div>';

        try {
            const response = await fetch(`${baseUrl}?form_type_id=${encodeURIComponent(formTypeId)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load approval routes.');
            }

            container.innerHTML = await response.text();
        } catch {
            container.innerHTML = '<div class="py-6 text-center text-sm text-red-600">Failed to load approval routes.</div>';
        }
    };

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-employee-profile-form-type]')) {
            loadEmployeeProfileApprovalRoutes(event.target);
        }
    });

    const syncBulkDeleteControls = (root = document) => {
        const selectAll = root.querySelector('[data-bulk-select-all]');
        const items = root.querySelectorAll('[data-bulk-select-item]');
        const deleteButton = root.querySelector('[data-bulk-delete-btn]');

        if (!items.length || !deleteButton) {
            return;
        }

        const refresh = () => {
            const checkedCount = root.querySelectorAll('[data-bulk-select-item]:checked').length;
            deleteButton.disabled = checkedCount === 0;

            if (selectAll) {
                selectAll.checked = checkedCount > 0 && checkedCount === items.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < items.length;
            }
        };

        selectAll?.addEventListener('change', () => {
            items.forEach((item) => {
                item.checked = selectAll.checked;
            });
            refresh();
        });

        items.forEach((item) => {
            item.addEventListener('change', refresh);
        });

        refresh();
    };

    syncBulkDeleteControls();
});
