import { initSearchableSelects, refreshSearchableSelect } from './searchable-select.js';
import { initGovernmentIdInputs } from './government-id-format.js';

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

    const startDesktopInstallerDownload = async (trigger) => {
        const href = trigger.getAttribute('href') || '';
        const filename = trigger.dataset.desktopInstallerFilename || 'People360-installer';
        const statusEl = document.querySelector('[data-desktop-installer-download-status]');

        if (!href) {
            return;
        }

        pulseLoader.show('Preparing installer download...');
        if (statusEl) {
            statusEl.hidden = false;
            statusEl.textContent = 'Preparing download…';
        }

        try {
            const response = await fetch(href, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.ok || !payload.url) {
                throw new Error(payload.message || 'Unable to prepare the installer download.');
            }

            pulseLoader.show('Starting download...');
            if (statusEl) {
                statusEl.textContent = `Downloading ${payload.filename || filename}…`;
            }

            // Prefer an iframe so Electron/NativePHP does not navigate the main window
            // away on large attachment responses (and S3 can stream directly).
            const frame = document.createElement('iframe');
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:fixed;width:0;height:0;border:0;opacity:0;pointer-events:none;';
            frame.src = payload.url;
            document.body.appendChild(frame);

            window.setTimeout(() => {
                frame.remove();
                pulseLoader.hide();
                if (statusEl) {
                    statusEl.textContent = 'Download started. Check your Downloads folder, then quit People360 and run the installer.';
                }
            }, 2500);
        } catch (error) {
            pulseLoader.hide();
            if (statusEl) {
                statusEl.textContent = error?.message || 'Download failed.';
            }
            window.alert(error?.message || 'Unable to start the installer download.');
        }
    };

    document.addEventListener('click', (event) => {
        const installerDownload = event.target.closest('[data-desktop-installer-download]');

        if (installerDownload) {
            event.preventDefault();
            startDesktopInstallerDownload(installerDownload);
        }
    });

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

        // AJAX pagination / lazy panel fetches — do not trigger full-page loader
        if (link.hasAttribute('data-live-table-page') || link.closest('[data-employee-profile-lazy-content]')) {
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

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const confirmMessage = form.dataset.confirmSubmit;

        if (confirmMessage && ! window.confirm(confirmMessage)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.noLoader !== undefined) {
            return;
        }

        // Confirm cancel / validation already blocked the submit — do not leave the loader up.
        if (event.defaultPrevented) {
            return;
        }

        // New-tab / download submits leave this document mounted — showing the
        // full-screen loader would stick forever (desktop report preview).
        if (form.target === '_blank' || form.hasAttribute('download')) {
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

    let credentialPreviewObjectUrl = null;

    const revokeCredentialPreviewObjectUrl = () => {
        if (credentialPreviewObjectUrl) {
            URL.revokeObjectURL(credentialPreviewObjectUrl);
            credentialPreviewObjectUrl = null;
        }
    };

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        if (modal.id === 'employee-credential-preview-modal') {
            const previewFrame = modal.querySelector('[data-credential-preview-frame]');
            revokeCredentialPreviewObjectUrl();
            if (previewFrame) {
                previewFrame.removeAttribute('srcdoc');
                previewFrame.src = 'about:blank';
            }
        }

        modal.classList.add('hidden');
        modal.classList.remove('modal-overlay-nested');

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
            modal.classList.remove('modal-overlay-nested');
        } else {
            modal.classList.add('modal-overlay-nested');
        }

        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    };

    const fillCredentialPreviewModal = async (trigger) => {
        const modal = document.getElementById('employee-credential-preview-modal');
        if (!modal || !trigger?.dataset?.credentialPreviewUrl) {
            return false;
        }

        const title = modal.querySelector('#employee-credential-preview-modal-title');
        const description = modal.querySelector('[data-credential-preview-description]');
        const frame = modal.querySelector('[data-credential-preview-frame]');

        if (title) {
            title.textContent = trigger.dataset.credentialPreviewTitle || 'Credential Preview';
        }

        if (description) {
            const filename = trigger.dataset.credentialPreviewFilename || '';
            description.textContent = filename;
            description.classList.toggle('hidden', filename === '');
        }

        if (!frame) {
            return false;
        }

        const kind = trigger.dataset.credentialPreviewKind || '';
        if (kind === 'word' || kind === 'spreadsheet') {
            const engineReady = await ensureDocumentPreviewEngine();
            if (!engineReady) {
                return false;
            }
        }

        revokeCredentialPreviewObjectUrl();
        frame.removeAttribute('srcdoc');
        frame.src = 'about:blank';

        const isDesktop = document.documentElement.dataset.pulseDesktop === '1';
        const contentUrl = trigger.dataset.credentialContentUrl || '';

        // Electron/NativePHP: load image/PDF bytes via blob URL so the iframe never
        // navigates to a binary Content-Disposition response (blank white frame).
        if (isDesktop && contentUrl && (kind === 'pdf' || kind === 'image' || kind === 'word' || kind === 'spreadsheet')) {
            try {
                const response = await fetch(contentUrl, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: '*/*',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Unable to load credential preview.');
                }

                const blob = await response.blob();
                const type = (blob.type || '').toLowerCase();
                credentialPreviewObjectUrl = URL.createObjectURL(blob);

                if (kind === 'image' || type.startsWith('image/')) {
                    frame.srcdoc = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>html,body{margin:0;background:#fff;text-align:center}img{max-width:100%;height:auto}</style></head><body><img src="${credentialPreviewObjectUrl}" alt=""></body></html>`;
                } else if (kind === 'pdf' || kind === 'word' || kind === 'spreadsheet' || type.includes('pdf')) {
                    frame.srcdoc = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>html,body,embed{margin:0;width:100%;height:100%;border:0;background:#525659}</style></head><body><embed src="${credentialPreviewObjectUrl}" type="application/pdf"></body></html>`;
                } else {
                    frame.src = trigger.dataset.credentialPreviewUrl;
                }

                return true;
            } catch {
                // Fall through to server HTML preview route.
            }
        }

        frame.src = trigger.dataset.credentialPreviewUrl;

        return true;
    };

    const ensureDocumentPreviewEngine = async () => {
        const engineModal = document.getElementById('document-preview-engine-modal');
        const statusUrl = engineModal?.dataset?.documentPreviewEngineStatusUrl || '';
        const installUrl = engineModal?.dataset?.documentPreviewEngineInstallUrl || '';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        if (!statusUrl || !installUrl) {
            return true;
        }

        try {
            const statusResponse = await fetch(statusUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!statusResponse.ok) {
                return true;
            }

            const status = await statusResponse.json();
            if (status.available || !status.enabled) {
                return true;
            }

            if (!engineModal) {
                return true;
            }

            const meta = engineModal.querySelector('[data-document-preview-engine-meta]');
            if (meta) {
                meta.textContent = `Platform: ${status.platform} · LibreOffice ${status.version} · ~${status.approximate_size_mb} MB`;
            }

            return await new Promise((resolve) => {
                const installBtn = engineModal.querySelector('[data-document-preview-engine-install]');
                let settled = false;

                const cleanup = () => {
                    installBtn?.removeEventListener('click', onInstall);
                    engineModal.removeEventListener('click', onDismiss, true);
                };

                const finish = (value) => {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    cleanup();
                    closeModal(engineModal);
                    resolve(value);
                };

                const onDismiss = (event) => {
                    if (event.target.closest('[data-modal-close]')) {
                        finish(false);
                    }
                };

                const onInstall = async () => {
                    installBtn.disabled = true;
                    window.pulseLoader?.show?.('Downloading preview engine...');
                    try {
                        const response = await fetch(installUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrf,
                                'Content-Type': 'application/json',
                            },
                            body: '{}',
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok || !payload.ok) {
                            window.alert(payload.message || 'Unable to install the document preview engine.');
                            finish(false);
                            return;
                        }
                        finish(true);
                    } catch {
                        window.alert('Unable to install the document preview engine.');
                        finish(false);
                    } finally {
                        installBtn.disabled = false;
                        window.pulseLoader?.hide?.();
                    }
                };

                installBtn?.addEventListener('click', onInstall);
                engineModal.addEventListener('click', onDismiss, true);
                openModal(engineModal, { stack: true });
            });
        } catch {
            return true;
        }
    };

    document.addEventListener('click', (event) => {
        const openTrigger = event.target.closest('[data-modal-open]');

        if (openTrigger) {
            const modal = document.getElementById(openTrigger.dataset.modalOpen);
            const shouldStack = openTrigger.hasAttribute('data-modal-stack')
                || Boolean(document.querySelector('.modal-overlay:not(.hidden)'));

            if (openTrigger.dataset.credentialPreviewUrl) {
                event.preventDefault();
                fillCredentialPreviewModal(openTrigger).then((ready) => {
                    if (ready) {
                        openModal(modal, { stack: shouldStack });
                    }
                });

                return;
            }

            openModal(modal, { stack: shouldStack });

            return;
        }

        const closeTrigger = event.target.closest('[data-modal-close]');

        if (closeTrigger) {
            closeModal(closeTrigger.closest('.modal-overlay'));
        }
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

    const filterAssignmentRowSelects = (row, campusId) => {
        if (!row) {
            return;
        }

        row.querySelectorAll('[data-assignment-college-select], [data-assignment-program-select]').forEach((select) => {
            if (!campusFilterOptionCache.has(select.id)) {
                campusFilterOptionCache.set(
                    select.id,
                    Array.from(select.querySelectorAll('option')).map((option) => ({
                        value: option.value,
                        label: option.textContent,
                        campusId: option.dataset.campusId ?? '',
                        selected: option.selected,
                    })),
                );
            }

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

    const reindexCampusAssignmentRows = (root) => {
        const rows = root.querySelectorAll('[data-campus-assignment-row]');

        rows.forEach((row, index) => {
            row.dataset.assignmentIndex = String(index);

            row.querySelectorAll('[name]').forEach((field) => {
                if (!field.name) {
                    return;
                }

                field.name = field.name.replace(
                    /campus_assignments\[\d+]/,
                    `campus_assignments[${index}]`,
                );

                if (field.id && field.id.startsWith('campus_assignment_')) {
                    field.id = field.id.replace(/campus_assignment_\d+/, `campus_assignment_${index}`);
                }
            });

            row.querySelectorAll('label[for]').forEach((label) => {
                if (label.htmlFor.startsWith('campus_assignment_')) {
                    label.htmlFor = label.htmlFor.replace(/campus_assignment_\d+/, `campus_assignment_${index}`);
                }
            });

            const removeButton = row.querySelector('[data-campus-assignment-remove]');

            if (removeButton) {
                removeButton.classList.toggle('hidden', rows.length <= 1);
            }

        });
    };

    const ensureOneMainAssignment = (root) => {
        const boxes = [...root.querySelectorAll('[data-campus-assignment-rows] [data-main-assignment-checkbox]')];

        if (boxes.length === 0) {
            return;
        }

        if (!boxes.some((box) => box.checked)) {
            boxes[0].checked = true;
        }
    };

    const initCampusAssignmentRow = (row, root) => {
        const campusSelect = row.querySelector('[data-assignment-campus-select]');

        if (campusSelect) {
            const syncRowFilters = () => filterAssignmentRowSelects(row, campusSelect.value);
            syncRowFilters();
            campusSelect.addEventListener('change', syncRowFilters);
        } else {
            const hiddenCampusId = row.querySelector('input[name*="[campus_id]"]')?.value;
            filterAssignmentRowSelects(row, hiddenCampusId ?? '');
        }

        row.querySelector('[data-campus-assignment-remove]')?.addEventListener('click', () => {
            if (root.querySelectorAll('[data-campus-assignment-row]').length <= 1) {
                return;
            }

            row.remove();
            reindexCampusAssignmentRows(root);
            ensureOneMainAssignment(root);
        });
    };

    const initCampusAssignmentsRoot = (root) => {
        if (root.dataset.campusAssignmentsBound === '1') {
            return;
        }

        root.dataset.campusAssignmentsBound = '1';

        root.querySelectorAll('[data-campus-assignment-row]').forEach((row) => {
            initCampusAssignmentRow(row, root);
        });

        root.querySelector('[data-campus-assignment-add]')?.addEventListener('click', () => {
            const template = root.querySelector('[data-campus-assignment-row-template]');
            const rowsContainer = root.querySelector('[data-campus-assignment-rows]');

            if (!template || !rowsContainer) {
                return;
            }

            const nextIndex = rowsContainer.querySelectorAll('[data-campus-assignment-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            const row = wrapper.firstElementChild;

            if (!row) {
                return;
            }

            rowsContainer.appendChild(row);
            reindexCampusAssignmentRows(root);
            initCampusAssignmentRow(row, root);
            initSearchableSelects(row);
            ensureOneMainAssignment(root);
        });

        root.addEventListener('change', (event) => {
            const target = event.target;

            if (!target.matches('[data-main-assignment-checkbox]')) {
                return;
            }

            if (target.checked) {
                root.querySelectorAll('[data-campus-assignment-rows] [data-main-assignment-checkbox]').forEach((box) => {
                    if (box !== target) {
                        box.checked = false;
                    }
                });

                return;
            }

            ensureOneMainAssignment(root);
        });

        reindexCampusAssignmentRows(root);
        ensureOneMainAssignment(root);
    };

    document.querySelectorAll('[data-campus-assignments-root]').forEach(initCampusAssignmentsRoot);

    const syncMiddleNameField = (form) => {
        const toggle = form.querySelector('[data-no-middle-name-toggle]');
        const input = form.querySelector('[data-middle-name-input]');
        const requiredMarker = form.querySelector('[data-middle-name-required-marker]');

        if (!toggle || !input) {
            return;
        }

        const noMiddleName = toggle.checked;

        input.disabled = noMiddleName;
        input.removeAttribute('required');

        requiredMarker?.classList.toggle('hidden', noMiddleName);

        if (noMiddleName) {
            input.value = '';
        }
    };

    document.querySelectorAll('[data-employee-wizard-form], [data-employee-form]').forEach((form) => {
        const middleNameToggle = form.querySelector('[data-no-middle-name-toggle]');

        if (middleNameToggle) {
            middleNameToggle.addEventListener('change', () => syncMiddleNameField(form));
            syncMiddleNameField(form);
        }

        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-campus-assignment-row] select:disabled, [data-campus-assignment-row] input:disabled')
                .forEach((field) => {
                    field.disabled = false;
                });
        });
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
            if (enabled) {
                field.disabled = false;
                field.removeAttribute('disabled');
            } else {
                field.disabled = true;
            }
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
        syncMiddleNameField(form);
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

    document.querySelectorAll('#employee-form, [data-employee-form], [data-employee-wizard-form]').forEach(bindEmployeeFormSubmitPrep);

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

    const activateEmployeeSalaryScopeTab = (button) => {
        const container = button.closest('[data-employee-salary-panel]');

        if (!container) {
            return;
        }

        const tabId = button.dataset.salaryScopeTab;

        container.querySelectorAll('[data-salary-scope-tab]').forEach((item) => {
            item.classList.toggle('employee-salary-subtab-btn-active', item === button);
        });

        container.querySelectorAll('[data-salary-scope-panel]').forEach((tabPanel) => {
            tabPanel.classList.toggle('hidden', tabPanel.dataset.salaryScopePanel !== tabId);
        });
    };

    if (document.body.dataset.employeeSalaryScopeTabsBound !== 'true') {
        document.body.dataset.employeeSalaryScopeTabsBound = 'true';

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-salary-scope-tab]');

            if (!button) {
                return;
            }

            event.preventDefault();
            activateEmployeeSalaryScopeTab(button);
        });
    }

    const initEmployeeSalaryPreviousPanel = (panel) => {
        const previousScope = panel.querySelector('[data-salary-scope-panel="previous"]');

        if (!previousScope || previousScope.dataset.previousSalaryInitialized === 'true') {
            return;
        }

        previousScope.dataset.previousSalaryInitialized = 'true';

        previousScope.querySelectorAll('[data-client-paginate]').forEach(initClientPagination);

        const rows = previousScope.querySelectorAll('[data-previous-salary-select]');
        const details = previousScope.querySelectorAll('[data-previous-salary-detail]');

        const selectPreviousSalary = (salaryId) => {
            rows.forEach((row) => {
                row.classList.toggle(
                    'employee-salary-previous-row-selected',
                    row.dataset.previousSalarySelect === salaryId,
                );
            });

            details.forEach((detail) => {
                detail.classList.toggle('hidden', detail.dataset.previousSalaryDetail !== salaryId);
            });
        };

        rows.forEach((row) => {
            row.addEventListener('click', () => {
                selectPreviousSalary(row.dataset.previousSalarySelect);
            });

            row.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectPreviousSalary(row.dataset.previousSalarySelect);
                }
            });
        });

        if (rows[0]) {
            selectPreviousSalary(rows[0].dataset.previousSalarySelect);
        }

        previousScope.addEventListener('click', (event) => {
            const button = event.target.closest('[data-previous-salary-subtab]');

            if (!button) {
                return;
            }

            const detailId = button.dataset.previousSalaryDetailId;
            const tabId = button.dataset.previousSalarySubtab;
            const detail = previousScope.querySelector(`[data-previous-salary-detail="${detailId}"]`);

            if (!detail) {
                return;
            }

            detail.querySelectorAll('[data-previous-salary-subtab]').forEach((item) => {
                item.classList.toggle('employee-salary-subtab-btn-active', item === button);
            });

            detail.querySelectorAll('[data-previous-salary-subtab-panel]').forEach((tabPanel) => {
                tabPanel.classList.toggle('hidden', tabPanel.dataset.previousSalarySubtabPanel !== tabId);
            });
        });
    };

    const initEmployeeSalaryPanel = (panel) => {
        initEmployeeSalaryPreviousPanel(panel);

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

    const loadEmployeeProfileLazyPanel = async (panel, url = null) => {
        const fetchUrl = url || panel?.dataset.lazyUrl;

        if (!panel || !fetchUrl) {
            return;
        }

        if (!url && panel.dataset.loaded === 'true') {
            return;
        }

        panel.dataset.loaded = 'loading';
        panel.innerHTML = '<div class="py-6 text-center text-sm text-gray-500">Loading…</div>';

        try {
            const response = await fetch(fetchUrl, {
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
            panel.querySelectorAll('[data-client-paginate]').forEach(initClientPagination);
        } catch {
            panel.innerHTML = '<div class="py-6 text-center text-sm text-red-600">Failed to load tab content.</div>';
            panel.dataset.loaded = 'false';
        }
    };

    const initEmployeeProfileFormTabs = (root = document) => {
        root.querySelectorAll('[data-employee-form-tabs]').forEach((form) => {
            const activeLazyPanel = form.querySelector('[data-employee-tab-panel]:not(.hidden)[data-employee-profile-lazy-panel]');

            if (form.dataset.employeeFormTabsInitialized === 'true') {
                if (activeLazyPanel?.dataset.lazyPending === 'true') {
                    loadEmployeeProfileLazyPanel(activeLazyPanel);
                }

                return;
            }

            form.dataset.employeeFormTabsInitialized = 'true';

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

                    const panel = form.querySelector(
                        `[data-employee-tab-panel="${button.dataset.employeeTab}"][data-employee-profile-lazy-panel]`,
                    );

                    if (panel) {
                        loadEmployeeProfileLazyPanel(panel);
                    }
                });
            });

            if (activeLazyPanel?.dataset.lazyPending === 'true') {
                loadEmployeeProfileLazyPanel(activeLazyPanel);
            }

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
    };

    const initEmployeeProfileSetupForm = (form) => {
        if (form.dataset.employeeProfileSetupInitialized === 'true') {
            return;
        }

        form.dataset.employeeProfileSetupInitialized = 'true';

        form.addEventListener('submit', () => {
            form.querySelectorAll('input[name="_method"]').forEach((field) => {
                field.remove();
            });

            form.querySelectorAll('[data-employee-tab-panel].hidden [required]').forEach((field) => {
                field.removeAttribute('required');
            });
        });
    };

    const initEmployeeProfileSetupRoots = (root = document) => {
        root.querySelectorAll('[data-employee-profile-setup-form]').forEach(initEmployeeProfileSetupForm);
    };

    initEmployeeProfileFormTabs();
    initEmployeeProfileSetupRoots();

    const SCHEME_USER_AMORTIZATION = '1';
    const SCHEME_BASED_ON_PAYMENTS = '2';

    const parseLoanNumber = (value) => {
        const parsed = Number.parseFloat(String(value ?? '').replace(/,/g, ''));
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const formatLoanMoney = (value) => (Math.round(value * 100) / 100).toFixed(2);

    const syncEmployeeLoanForm = (form) => {
        const scheme = form.querySelector('[data-loan-payment-scheme]');
        const nop = form.querySelector('[data-loan-number-of-payments]');
        const amortization = form.querySelector('[data-loan-amortization]');
        const amount = form.querySelector('[data-loan-amount]');
        const interest = form.querySelector('[data-loan-interest]');
        const paidPrevious = form.querySelector('[data-loan-paid-previous]');
        const deductedNew = form.querySelector('[data-loan-deducted-new]');
        const principal = form.querySelector('[data-loan-principal]');
        const balance = form.querySelector('[data-loan-balance]');

        if (!scheme || !amount) {
            return;
        }

        const schemeValue = String(scheme.value || SCHEME_USER_AMORTIZATION);
        const basedOnPayments = schemeValue === SCHEME_BASED_ON_PAYMENTS;

        if (nop) {
            nop.disabled = !basedOnPayments;
            nop.required = basedOnPayments;
        }

        if (amortization) {
            amortization.disabled = basedOnPayments;
            amortization.required = !basedOnPayments;
        }

        const loanAmount = parseLoanNumber(amount.value);
        const loanInterest = parseLoanNumber(interest?.value);
        const paid = parseLoanNumber(paidPrevious?.value);
        const deducted = parseLoanNumber(deductedNew?.value);

        if (basedOnPayments && amortization && nop) {
            const payments = Number.parseInt(String(nop.value || ''), 10);
            if (payments > 0) {
                amortization.value = formatLoanMoney(loanAmount / payments);
            }
        }

        if (principal) {
            principal.value = formatLoanMoney(loanAmount + loanInterest);
        }

        if (balance) {
            balance.value = formatLoanMoney(loanAmount + loanInterest - paid - deducted);
        }
    };

    const initEmployeeLoanForm = (form) => {
        if (!form || form.dataset.loanFormInitialized === 'true') {
            return;
        }

        form.dataset.loanFormInitialized = 'true';

        const refresh = () => syncEmployeeLoanForm(form);

        form.querySelectorAll([
            '[data-loan-payment-scheme]',
            '[data-loan-number-of-payments]',
            '[data-loan-amount]',
            '[data-loan-interest]',
            '[data-loan-paid-previous]',
            '[data-loan-deducted-new]',
            '[data-loan-amortization]',
        ].join(',')).forEach((field) => {
            field.addEventListener('input', refresh);
            field.addEventListener('change', refresh);
        });

        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-loan-number-of-payments], [data-loan-amortization]').forEach((field) => {
                if (field.disabled) {
                    field.disabled = false;
                    field.dataset.wasDisabled = '1';
                }
            });
        });

        refresh();
    };

    const initEmployeeLoanForms = (root = document) => {
        root.querySelectorAll('[data-employee-loan-form]').forEach(initEmployeeLoanForm);
    };

    initEmployeeLoanForms();

    document.querySelectorAll('[data-modal-auto-open]').forEach((modal) => {
        openModal(modal);
        initEmployeeProfileFormTabs(modal);
        initEmployeeProfileSetupRoots(modal);
        initEmployeeLoanForms(modal);
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

    const initPayrollCalendarScheduleForm = (form) => {
        if (form.dataset.philhealthExclusiveBound === '1') {
            return;
        }

        form.dataset.philhealthExclusiveBound = '1';

        const exclusiveCheckboxes = Array.from(form.querySelectorAll('[data-philhealth-exclusive]'));

        exclusiveCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (!checkbox.checked) {
                    return;
                }

                exclusiveCheckboxes.forEach((other) => {
                    if (other !== checkbox) {
                        other.checked = false;
                    }
                });
            });
        });
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
        container.querySelectorAll('[data-payroll-calendar-schedule-form]').forEach(initPayrollCalendarScheduleForm);
        container.querySelectorAll('[data-payroll-batch-form]').forEach(initPayrollBatchForm);
        container.querySelectorAll('[data-dual-list-select]').forEach(initDualListSelect);
        initSearchableSelects(container);
        initEmployeeProfileFormTabs(container);
        initEmployeeProfileSetupRoots(container);
        initEmployeeLoanForms(container);
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

    const syncBatchAddDeductionHours = (form) => {
        const typeSelect = form?.querySelector('[data-batch-add-deduction-type]');
        const ltdeWrap = form?.querySelector('[data-batch-add-deduction-ltde-wrap]');
        const hoursInput = form?.querySelector('[data-batch-add-deduction-hours]');
        const daysInput = form?.querySelector('[data-batch-add-deduction-days]');
        const referenceDateInput = form?.querySelector('[data-batch-add-deduction-reference-date]');
        const referenceDateRequired = form?.querySelector('[data-batch-add-deduction-reference-date-required]');

        if (!typeSelect || !ltdeWrap) {
            return;
        }

        const code = typeSelect.selectedOptions[0]?.dataset.code ?? '';
        const needsLateUndertimeFields = code === 'LTDE' || code === 'UTDE';

        ltdeWrap.classList.toggle('hidden', !needsLateUndertimeFields);

        if (hoursInput) {
            hoursInput.required = needsLateUndertimeFields;

            if (!needsLateUndertimeFields) {
                hoursInput.value = '';
            }
        }

        if (daysInput) {
            daysInput.value = needsLateUndertimeFields ? '1' : '';
        }

        if (referenceDateInput) {
            referenceDateInput.required = needsLateUndertimeFields;
        }

        referenceDateRequired?.classList.toggle('hidden', !needsLateUndertimeFields);
    };

    const initBatchAddDeductionForm = (form) => {
        if (!form || form.dataset.batchAddDeductionInit === 'true') {
            return;
        }

        const typeSelect = form.querySelector('[data-batch-add-deduction-type]');

        if (!typeSelect) {
            return;
        }

        form.dataset.batchAddDeductionInit = 'true';
        typeSelect.addEventListener('change', () => syncBatchAddDeductionHours(form));
        syncBatchAddDeductionHours(form);
    };

    document.querySelectorAll('[data-batch-add-deduction-form]').forEach(initBatchAddDeductionForm);

    const syncBatchAddIncomeHoursDays = (form) => {
        const typeSelect = form?.querySelector('[data-batch-add-income-type]');
        const hoursDaysWrap = form?.querySelector('[data-batch-add-income-hours-days-wrap]');
        const hoursInput = form?.querySelector('[data-batch-add-income-hours]');
        const daysInput = form?.querySelector('[data-batch-add-income-days]');

        if (!typeSelect || !hoursDaysWrap) {
            return;
        }

        const code = typeSelect.selectedOptions[0]?.dataset.code ?? '';
        const needsHoursDays = code === 'BASC' || code === 'OVRT';

        hoursDaysWrap.classList.toggle('hidden', !needsHoursDays);

        if (hoursInput) {
            hoursInput.required = needsHoursDays;

            if (!needsHoursDays) {
                hoursInput.value = '';
            }
        }

        if (daysInput) {
            daysInput.required = needsHoursDays;

            if (!needsHoursDays) {
                daysInput.value = '';
            }
        }
    };

    const initBatchAddIncomeForm = (form) => {
        if (!form || form.dataset.batchAddIncomeInit === 'true') {
            return;
        }

        const typeSelect = form.querySelector('[data-batch-add-income-type]');

        if (!typeSelect) {
            return;
        }

        form.dataset.batchAddIncomeInit = 'true';
        typeSelect.addEventListener('change', () => syncBatchAddIncomeHoursDays(form));
        syncBatchAddIncomeHoursDays(form);
    };

    document.querySelectorAll('[data-batch-add-income-form]').forEach(initBatchAddIncomeForm);

    document.addEventListener('change', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const typeSelect = event.target.closest('[data-batch-add-deduction-type]');

        if (!typeSelect) {
            return;
        }

        syncBatchAddDeductionHours(typeSelect.closest('[data-batch-add-deduction-form]'));
    });

    document.addEventListener('change', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const incomeTypeSelect = event.target.closest('[data-batch-add-income-type]');

        if (!incomeTypeSelect) {
            return;
        }

        syncBatchAddIncomeHoursDays(incomeTypeSelect.closest('[data-batch-add-income-form]'));
    });

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
            modal?.querySelectorAll('[data-equivalent-form-tardiness]').forEach(syncMarksAbsentEquivalent);
            modal?.querySelectorAll('[data-batch-add-deduction-form]').forEach((form) => {
                initBatchAddDeductionForm(form);
                syncBatchAddDeductionHours(form);
            });
            modal?.querySelectorAll('[data-batch-add-income-form]').forEach((form) => {
                initBatchAddIncomeForm(form);
                syncBatchAddIncomeHoursDays(form);
            });
            modal?.querySelectorAll('[data-time-logs-upload-form]').forEach((form) => {
                syncTimeLogsTemplateLink(form);
                syncTimeLogsDtrFileAccept(form);
            });
            modal?.querySelectorAll('[data-employee-upload-form]').forEach(initEmployeeUploadForm);
            syncPayrollBatchRemoveSelection(modal ?? document);
            syncPayrollBatchAddSelection(modal ?? document);
            initEmployeeProfileFormTabs(modal ?? document);
            initEmployeeProfileSetupRoots(modal ?? document);
            initEmployeeLoanForms(modal ?? document);
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
            field.addEventListener('change', () => {
                loadResults();
            });
        });

        perPageSelect?.addEventListener('change', () => {
            loadResults();
        });

        root.addEventListener('click', (event) => {
            const pageLink = event.target.closest('[data-live-table-page]');

            if (!pageLink || !root.contains(pageLink)) {
                return;
            }

            // Employee Profile modal tabs paginate via their own AJAX loader — not the list live-table.
            if (pageLink.closest('[data-employee-profile-lazy-content]')) {
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
        });

        form.querySelectorAll('[data-ot-input]').forEach((field) => {
            if (disabled) {
                field.setAttribute('disabled', 'disabled');
            } else {
                field.removeAttribute('disabled');
            }
        });
    };

    const syncBreakTardinessFields = (form) => {
        const toggle = form.querySelector('[data-break-tardiness-toggle]');
        const enabled = toggle?.checked ?? false;

        form.querySelectorAll('[data-break-tardiness-panel]').forEach((panel) => {
            panel.classList.toggle('opacity-50', !enabled);
        });

        form.querySelectorAll('[data-break-tardiness-field]').forEach((field) => {
            if (enabled) {
                field.removeAttribute('disabled');
            } else {
                field.setAttribute('disabled', 'disabled');
            }
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

    const syncMarksAbsentEquivalent = (form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const toggle = form.querySelector('[data-marks-absent-toggle]');
        const field = form.querySelector('[data-equivalent-field]');

        if (!toggle || !field) {
            return;
        }

        const apply = () => {
            if (toggle.checked) {
                field.value = '0';
                field.readOnly = true;
                field.removeAttribute('required');
            } else {
                field.readOnly = false;
                field.setAttribute('required', 'required');
            }
        };

        if (!toggle.dataset.marksAbsentBound) {
            toggle.dataset.marksAbsentBound = '1';
            toggle.addEventListener('change', apply);
        }

        apply();
    };

    const initTimekeepingPolicyRoot = (root) => {
        root.querySelectorAll('[data-timekeeping-settings]').forEach((form) => {
            syncFlexiFields(form);
            syncExcessHourFields(form);
            syncBreakTardinessFields(form);
            syncRestDayFields(form);
            syncToilFields(form);
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

                if (target.matches('[data-rest-day-toggle]')) {
                    syncRestDayFields(form);
                }

                if (target.matches('[data-toil-toggle]')) {
                    syncToilFields(form);
                }

                if (target.matches('[data-nd-compute-toggle]')) {
                    syncNightDiffFields(form);
                }
            });
        });
    };

    document.querySelectorAll('[data-timekeeping-policy-root]').forEach(initTimekeepingPolicyRoot);
    document.querySelectorAll('[data-equivalent-form-tardiness]').forEach(syncMarksAbsentEquivalent);

    const reindexShiftBreakRows = (tbody) => {
        tbody.querySelectorAll('[data-shift-break-row]').forEach((row, index) => {
            const label = row.querySelector('[data-shift-break-label]');
            if (label) {
                label.textContent = `Break ${index + 1}`;
            }

            row.querySelectorAll('input').forEach((input) => {
                const name = input.getAttribute('name') ?? '';
                if (name.includes('[break_out]')) {
                    input.name = `breaks[${index}][break_out]`;
                } else if (name.includes('[break_in]')) {
                    input.name = `breaks[${index}][break_in]`;
                } else if (name.includes('[break_minute]')) {
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

        const syncFlexiShiftFields = () => {
            const enabled = form.querySelector('[data-flexi-shift-toggle]')?.checked ?? false;
            const expectedPanel = form.querySelector('[data-flexi-expected-panel]');

            if (expectedPanel) {
                expectedPanel.classList.toggle('hidden', !enabled);
            }

            form.querySelectorAll('[data-flexi-expected-field]').forEach((field) => {
                field.disabled = !enabled;
                if (!enabled) {
                    field.removeAttribute('required');
                } else {
                    field.setAttribute('required', 'required');
                }
            });

            form.querySelectorAll('[data-shift-duty-field]').forEach((field) => {
                if (enabled) {
                    field.removeAttribute('required');
                } else {
                    field.setAttribute('required', 'required');
                }
            });

            form.querySelectorAll('[data-shift-duty-required-star]').forEach((star) => {
                star.classList.toggle('hidden', enabled);
            });
        };

        form.querySelector('[data-flexi-shift-toggle]')?.addEventListener('change', syncFlexiShiftFields);
        syncFlexiShiftFields();

        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-flexi-expected-field]:disabled')
                .forEach((field) => {
                    field.disabled = false;
                });
        });

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
    document.querySelectorAll('[data-payroll-calendar-schedule-form]').forEach(initPayrollCalendarScheduleForm);
    document.querySelectorAll('[data-payroll-batch-form]').forEach(initPayrollBatchForm);
    document.querySelectorAll('[data-modal-auto-open] [data-payroll-batch-form]').forEach(initPayrollBatchForm);
    document.querySelectorAll('[data-payroll-upload-form]').forEach(syncPayrollUploadTemplateLink);
    document.querySelectorAll('[data-modal-auto-open] [data-payroll-upload-form]').forEach(syncPayrollUploadTemplateLink);
    document.querySelectorAll('[data-dual-list-select]').forEach(initDualListSelect);

    const syncTimeLogsDtrFileAccept = (form) => {
        const campusSelect = form.querySelector('[data-time-logs-dtr-campus-select]');
        const fileInput = form.querySelector('[data-time-logs-dtr-file-input]');
        const hint = form.querySelector('[data-time-logs-dtr-file-hint]');

        if (!campusSelect || !fileInput) {
            return;
        }

        const update = () => {
            const selected = campusSelect.selectedOptions[0];
            const extension = selected?.dataset.fileExtension ?? '';
            const parser = selected?.dataset.parser ?? '';

            if (extension) {
                fileInput.accept = `.${extension}`;
            } else {
                fileInput.accept = '.xls,.xlsx';
            }

            if (hint) {
                if (!extension) {
                    hint.textContent = 'Select a campus first. Most campuses: .xls Timesheet Report. San Mateo: .xls Card Report. Sumulong: .xlsx DTR Report.';
                } else if (parser === 'san_mateo_card_report') {
                    hint.textContent = 'Upload San Mateo DTR (.XLS). Only Card Report worksheets are imported; summary tabs are ignored.';
                } else if (parser === 'sumulong_dtr_report') {
                    hint.textContent = 'Upload Sumulong DTR Report (.XLSX). Employee name plus ID number in parentheses.';
                } else if (parser === 'cainta_timesheet_report') {
                    hint.textContent = 'Upload Timesheet Report (.XLS). Employee: NAME (biometric ID) blocks with In/Out columns.';
                } else {
                    hint.textContent = `Upload the campus DTR file (.${extension.toUpperCase()} only).`;
                }
            }
        };

        if (form.dataset.timeLogsDtrInit !== 'true') {
            form.dataset.timeLogsDtrInit = 'true';
            campusSelect.addEventListener('change', update);
        }

        update();
    };

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

    document.querySelectorAll('[data-time-logs-upload-form]').forEach((form) => {
        syncTimeLogsTemplateLink(form);
        syncTimeLogsDtrFileAccept(form);
    });
    syncTimeLogsPurgeSelection();

    const updateEmployeeLoadTemplateLink = (form) => {
        const link = form.querySelector('[data-el-template-link]');
        const hint = form.querySelector('[data-el-template-hint]');
        const dateFrom = form.querySelector('[data-el-date-from]');
        const dateTo = form.querySelector('[data-el-date-to]');
        const base = link?.dataset.elTemplateBase;

        if (!link || !base) {
            return;
        }

        const from = dateFrom?.value;
        const to = dateTo?.value;
        const ready = Boolean(from && to);

        if (ready) {
            const params = new URLSearchParams({
                date_from: from,
                date_to: to,
            });
            link.href = `${base}?${params.toString()}`;
            link.dataset.disabled = 'false';
            if (hint) {
                hint.hidden = true;
            }
        } else {
            link.href = base;
            link.dataset.disabled = 'true';
            if (hint) {
                hint.hidden = false;
            }
        }
    };

    const initEmployeeLoadUploadForm = (form) => {
        if (form.dataset.employeeLoadInit === '1') {
            updateEmployeeLoadTemplateLink(form);

            return;
        }

        form.dataset.employeeLoadInit = '1';

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-el-date-from], [data-el-date-to]')) {
                updateEmployeeLoadTemplateLink(form);
            }
        });

        updateEmployeeLoadTemplateLink(form);
    };

    const syncEmployeeLoadPurgeSelection = () => {
        const purgeBtn = document.querySelector('[data-employee-load-purge-btn]');
        const countLabel = document.querySelector('[data-employee-load-selected-count]');

        if (!purgeBtn) {
            return;
        }

        const update = () => {
            const checked = document.querySelectorAll('[data-employee-load-row-select]:checked').length;
            purgeBtn.disabled = checked === 0;

            if (countLabel) {
                countLabel.textContent = `${checked} selected`;
            }
        };

        document.addEventListener('change', (event) => {
            if (event.target.matches('[data-employee-load-select-all]')) {
                document.querySelectorAll('[data-employee-load-row-select]').forEach((checkbox) => {
                    checkbox.checked = event.target.checked;
                });
                update();

                return;
            }

            if (event.target.matches('[data-employee-load-row-select]')) {
                update();
            }
        });

        update();
    };

    document.querySelectorAll('[data-employee-load-upload-form]').forEach(initEmployeeLoadUploadForm);
    document.querySelectorAll('[data-modal-auto-open] [data-employee-load-upload-form]').forEach(initEmployeeLoadUploadForm);

    const syncEmployeeUploadForm = (form) => {
        const typeSelect = form.querySelector('[data-employee-upload-type]');
        const templateLink = form.querySelector('[data-employee-upload-template-link]');
        const templateBase = templateLink?.dataset.employeeUploadTemplateBase;

        if (!typeSelect) {
            return;
        }

        const uploadType = typeSelect.value || 'master-file';

        form.querySelectorAll('[data-employee-upload-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.employeeUploadPanel !== uploadType;
        });

        if (templateLink && templateBase) {
            templateLink.href = `${templateBase}?type=${encodeURIComponent(uploadType)}`;
        }
    };

    const initEmployeeUploadForm = (form) => {
        if (form.dataset.employeeUploadBound === '1') {
            syncEmployeeUploadForm(form);

            return;
        }

        form.dataset.employeeUploadBound = '1';
        syncEmployeeUploadForm(form);

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-employee-upload-type]')) {
                syncEmployeeUploadForm(form);
            }
        });
    };

    document.querySelectorAll('[data-employee-upload-form]').forEach(initEmployeeUploadForm);
    document.querySelectorAll('[data-modal-auto-open] [data-employee-upload-form]').forEach(initEmployeeUploadForm);

    const initPayrollBatchMonthYearGuard = (root) => {
        root.querySelectorAll('[data-payroll-batch-month-guard], [data-sss-batch-select]').forEach((select) => {
            if (!select || select.dataset.payrollBatchGuardBound === '1') {
                return;
            }

            select.dataset.payrollBatchGuardBound = '1';

            select.addEventListener('change', () => {
                const selected = Array.from(select.selectedOptions);

                if (selected.length <= 1) {
                    return;
                }

                const anchor = selected[0];
                const payYear = anchor.dataset.payYear ?? '';
                const calendarMonth = anchor.dataset.calendarMonth ?? '';
                let removed = false;

                selected.slice(1).forEach((option) => {
                    if (
                        (option.dataset.payYear ?? '') !== payYear
                        || (option.dataset.calendarMonth ?? '') !== calendarMonth
                    ) {
                        option.selected = false;
                        removed = true;
                    }
                });

                if (removed) {
                    window.alert('Selected payroll batches must share the same pay month and pay year.');
                }
            });
        });
    };

    const initSssBatchMonthYearGuard = (root) => {
        initPayrollBatchMonthYearGuard(root);
    };

    const initBatchEmployeePicker = (root) => {
        const batchSelect = root.querySelector('[data-batch-employee-batch-select], [data-payslip-batch-select]');
        const employeeSelect = root.querySelector('[data-batch-employee-employee-select], [data-payslip-employee-select]');

        if (!batchSelect || !employeeSelect) {
            return;
        }

        const employeesUrl = batchSelect.dataset.employeesUrl ?? '';
        const employeesParam = batchSelect.dataset.employeesParam || 'payroll_batch_id';
        const emptyFilterMessage = batchSelect.dataset.employeesEmptyFilter
            || 'Select a posted batch to load employees…';
        const emptyResultsMessage = batchSelect.dataset.employeesEmptyResults
            || 'No employees found in this batch.';
        const isMultiBatch = Boolean(batchSelect.multiple);
        const selectedIds = new Set(
            [
                ...Array.from(employeeSelect.querySelectorAll('option:checked'))
                    .map((option) => option.value)
                    .filter(Boolean),
                ...(batchSelect.dataset.selectedEmployeeIds || '')
                    .split(',')
                    .map((value) => value.trim())
                    .filter(Boolean),
            ],
        );

        const selectedBatchValues = () => {
            if (isMultiBatch) {
                return Array.from(batchSelect.selectedOptions)
                    .map((option) => option.value)
                    .filter(Boolean);
            }

            return batchSelect.value ? [batchSelect.value] : [];
        };

        const setEmployeeOptions = (employees, preserveSelection = true) => {
            const nextSelected = preserveSelection ? selectedIds : new Set();

            employeeSelect.innerHTML = '';

            if (!employees.length) {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.disabled = true;
                placeholder.selected = true;
                placeholder.textContent = selectedBatchValues().length
                    ? emptyResultsMessage
                    : emptyFilterMessage;
                employeeSelect.appendChild(placeholder);

                return;
            }

            employees.forEach((employee) => {
                const option = document.createElement('option');
                option.value = String(employee.id);
                option.textContent = employee.label;

                if (nextSelected.has(String(employee.id))) {
                    option.selected = true;
                }

                employeeSelect.appendChild(option);
            });
        };

        const loadEmployees = async (filterValues, preserveSelection = true) => {
            const values = Array.isArray(filterValues)
                ? filterValues.filter(Boolean)
                : (filterValues ? [filterValues] : []);

            if (!values.length || !employeesUrl) {
                setEmployeeOptions([], preserveSelection);

                return;
            }

            employeeSelect.disabled = true;

            try {
                const url = new URL(employeesUrl, window.location.origin);

                if (isMultiBatch || employeesParam.includes('payroll_batch_ids')) {
                    values.forEach((value) => {
                        url.searchParams.append('payroll_batch_ids[]', value);
                    });
                } else {
                    url.searchParams.set(employeesParam, values[0]);
                }

                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load employees.');
                }

                const payload = await response.json();
                setEmployeeOptions(Array.isArray(payload.employees) ? payload.employees : [], preserveSelection);
            } catch {
                employeeSelect.innerHTML = '';
                const errorOption = document.createElement('option');
                errorOption.value = '';
                errorOption.disabled = true;
                errorOption.selected = true;
                errorOption.textContent = 'Unable to load employees.';
                employeeSelect.appendChild(errorOption);
            } finally {
                employeeSelect.disabled = false;
            }
        };

        batchSelect.addEventListener('change', () => {
            selectedIds.clear();
            loadEmployees(selectedBatchValues(), false);
        });

        const initialValues = selectedBatchValues();

        if (initialValues.length) {
            loadEmployees(initialValues, true);
        } else {
            setEmployeeOptions([], true);
        }
    };

    const initPayslipReportOptions = (root) => {
        initBatchEmployeePicker(root);
    };

    const initEmployeeMultiselect = (picker) => {
        if (!picker || picker.dataset.employeeMultiselectReady === '1') {
            return;
        }

        picker.dataset.employeeMultiselectReady = '1';

        const searchInput = picker.querySelector('[data-employee-multiselect-search]');
        const selectAll = picker.querySelector('[data-employee-multiselect-select-all]');
        const countLabel = picker.querySelector('[data-employee-multiselect-count]');

        const updateSelectedCount = () => {
            const checked = picker.querySelectorAll('[data-employee-multiselect-row]:checked').length;

            if (countLabel) {
                countLabel.textContent = `${checked} selected`;
            }

            return checked;
        };

        picker.querySelectorAll('[data-employee-multiselect-row]').forEach((checkbox) => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        selectAll?.addEventListener('change', () => {
            picker.querySelectorAll('[data-employee-multiselect-item]:not([hidden]) [data-employee-multiselect-row]').forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            updateSelectedCount();
        });

        searchInput?.addEventListener('input', () => {
            const term = (searchInput.value || '').trim().toLowerCase();

            picker.querySelectorAll('[data-employee-multiselect-item]').forEach((item) => {
                const haystack = item.dataset.employeeSearchText || '';
                item.hidden = term !== '' && !haystack.includes(term);
            });

            if (selectAll) {
                selectAll.checked = false;
            }
        });

        updateSelectedCount();
    };

    const initEmployeeMultiselects = (scope = document) => {
        if (!scope) {
            return;
        }

        if (scope.matches?.('[data-employee-multiselect]')) {
            initEmployeeMultiselect(scope);
        }

        scope.querySelectorAll?.('[data-employee-multiselect]').forEach(initEmployeeMultiselect);
    };

    const initPayrollReportsRoot = (root) => {
        const classificationSelect = root.querySelector('[data-payroll-report-classification]');
        const reportSelect = root.querySelector('[data-payroll-report-select]');
        const optionsPanel = root.querySelector('[data-payroll-report-options-panel]');
        const form = root.querySelector('[data-payroll-reports-form]');

        initSssBatchMonthYearGuard(root);

        form?.addEventListener('submit', async (event) => {
            const outputFormat = form.querySelector('[name="output_format"]')?.value ?? 'html';

            if (outputFormat === 'html') {
                const isPulseDesktop = document.documentElement.dataset.pulseDesktop === '1';

                if (isPulseDesktop) {
                    // Electron/NativePHP often opens an empty window for target="_blank".
                    form.removeAttribute('target');
                    pulseLoader.show('Generating report...');

                    return;
                }

                // Browser: preview in a new tab so this page stays on the form.
                form.target = '_blank';
                pulseLoader.hide();

                return;
            }

            // Excel/PDF attachment responses navigate Electron/NativePHP to a blank white
            // document. Fetch the file and trigger a client-side download instead.
            event.preventDefault();
            form.removeAttribute('target');
            const downloadLabel = outputFormat === 'pdf' ? 'Generating PDF...' : 'Generating Excel...';
            pulseLoader.show(downloadLabel);

            try {
                const response = await fetch(form.action, {
                    method: (form.method || 'POST').toUpperCase(),
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/pdf,application/octet-stream,*/*',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const contentType = response.headers.get('content-type') || '';
                const disposition = response.headers.get('content-disposition') || '';
                const isDownloadResponse = /attachment/i.test(disposition)
                    || contentType.includes('spreadsheet')
                    || contentType.includes('application/pdf');

                if (! response.ok || contentType.includes('text/html') || ! isDownloadResponse) {
                    const html = await response.text();
                    document.open();
                    document.write(html);
                    document.close();

                    return;
                }

                const filenameMatch = disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i);
                const rawName = filenameMatch?.[1] || filenameMatch?.[2] || (outputFormat === 'pdf' ? 'report.pdf' : 'report.xlsx');
                const filename = decodeURIComponent(rawName.trim());

                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = filename;
                link.rel = 'noopener';
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            } catch (error) {
                window.alert(error?.message || `${outputFormat === 'pdf' ? 'PDF' : 'Excel'} download failed.`);
            } finally {
                pulseLoader.hide();
            }
        });

        classificationSelect?.addEventListener('change', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('classification', classificationSelect.value);
            url.searchParams.delete('report_id');
            window.location.href = url.toString();
        });

        const loadReportOptions = async () => {
            const reportId = reportSelect?.value;

            if (!reportId || !optionsPanel) {
                return;
            }

            const templateUrl = reportSelect.dataset.optionsUrl ?? '';
            const optionsUrl = templateUrl.replace('__REPORT__', reportId);
            const classification = classificationSelect?.value ?? 'payroll';
            const url = `${optionsUrl}?classification=${encodeURIComponent(classification)}`;

            optionsPanel.innerHTML = '<p class="text-sm text-gray-500">Loading report options…</p>';

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load report options.');
                }

                optionsPanel.innerHTML = await response.text();
                initSssBatchMonthYearGuard(optionsPanel);
                initPayslipReportOptions(optionsPanel);
                initEmployeeMultiselects(optionsPanel);
            } catch {
                optionsPanel.innerHTML = '<p class="text-sm text-red-600">Unable to load report options.</p>';
            }
        };

        reportSelect?.addEventListener('change', () => {
            loadReportOptions();
        });

        const initialReportId = root.dataset.initialReportId;

        if (initialReportId && reportSelect?.value === initialReportId) {
            loadReportOptions();
        } else if (optionsPanel) {
            initSssBatchMonthYearGuard(optionsPanel);
            initPayslipReportOptions(optionsPanel);
            initEmployeeMultiselects(optionsPanel);
        }
    };

    document.querySelectorAll('[data-payroll-reports-root]').forEach(initPayrollReportsRoot);
    initEmployeeMultiselects(document);
    syncEmployeeLoadPurgeSelection();

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

    document.addEventListener('click', (event) => {
        const pageLink = event.target.closest('[data-live-table-page]');

        if (!pageLink) {
            return;
        }

        const lazyContent = pageLink.closest('[data-employee-profile-lazy-content]');

        if (!lazyContent) {
            return;
        }

        const panel = lazyContent.closest('[data-employee-tab-panel][data-employee-profile-lazy-panel]');

        if (!panel) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        loadEmployeeProfileLazyPanel(panel, pageLink.href);
    }, true);

    document.addEventListener('click', (event) => {
        const applyBtn = event.target.closest('[data-attendance-range-apply]');

        if (!applyBtn) {
            return;
        }

        const lazyContent = applyBtn.closest('[data-employee-profile-lazy-content]');
        const panel = lazyContent?.closest('[data-employee-tab-panel][data-employee-profile-lazy-panel]');
        const dateFrom = lazyContent?.querySelector('[data-attendance-date-from]')?.value;
        const dateTo = lazyContent?.querySelector('[data-attendance-date-to]')?.value;
        const baseUrl = applyBtn.dataset.attendanceBaseUrl || '';

        if (!panel || !baseUrl || !dateFrom || !dateTo) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.set('date_from', dateFrom);
        url.searchParams.set('date_to', dateTo);
        loadEmployeeProfileLazyPanel(panel, url.toString());
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        const target = event.target;
        if (!target?.matches?.('[data-attendance-date-from], [data-attendance-date-to]')) {
            return;
        }

        const lazyContent = target.closest('[data-employee-profile-lazy-content]');
        const applyBtn = lazyContent?.querySelector('[data-attendance-range-apply]');
        applyBtn?.click();
        event.preventDefault();
    });

    const showAttendanceCalendarDay = (root, dateKey) => {
        if (!root || !dateKey) {
            return;
        }

        const detail = root.querySelector('[data-calendar-day-detail]');
        const header = root.querySelector('[data-calendar-day-detail-header]');
        const title = root.querySelector('[data-calendar-day-detail-title]');
        const summary = root.querySelector('[data-calendar-day-detail-summary]');
        const placeholder = root.querySelector('[data-calendar-day-placeholder]');
        const panel = root.querySelector(`[data-calendar-day-panel="${dateKey}"]`);
        const dayButton = root.querySelector(`[data-calendar-day="${dateKey}"]`);

        if (!detail || !panel) {
            return;
        }

        detail.hidden = false;
        if (header) {
            header.hidden = false;
        }

        root.querySelectorAll('[data-calendar-day-panel]').forEach((el) => {
            el.hidden = el !== panel;
        });
        root.querySelectorAll('[data-calendar-day]').forEach((el) => {
            el.classList.toggle('is-selected', el === dayButton);
        });

        if (placeholder) {
            placeholder.hidden = true;
        }

        if (title) {
            title.textContent = dayButton?.dataset.calendarDayLabel || dateKey;
        }

        if (summary) {
            const firstIn = dayButton?.dataset.calendarFirstIn || '—';
            const lastOut = dayButton?.dataset.calendarLastOut || '—';
            summary.textContent = `First In: ${firstIn} · Last Out: ${lastOut}`;
        }

        detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    document.addEventListener('click', (event) => {
        const dayButton = event.target.closest('[data-calendar-day]');
        const closeButton = event.target.closest('[data-calendar-day-detail-close]');
        const root = event.target.closest('[data-attendance-calendar]');

        if (!root) {
            return;
        }

        if (closeButton) {
            const header = root.querySelector('[data-calendar-day-detail-header]');
            const placeholder = root.querySelector('[data-calendar-day-placeholder]');

            if (header) {
                header.hidden = true;
            }

            root.querySelectorAll('[data-calendar-day-panel]').forEach((el) => {
                el.hidden = true;
            });
            root.querySelectorAll('[data-calendar-day]').forEach((el) => {
                el.classList.remove('is-selected');
            });

            if (placeholder) {
                placeholder.hidden = false;
            }

            return;
        }

        if (dayButton) {
            showAttendanceCalendarDay(root, dayButton.dataset.calendarDay);
        }
    });

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

    function initClientPagination(container) {
        if (!container || container.dataset.clientPaginateInitialized === 'true') {
            return;
        }

        container.dataset.clientPaginateInitialized = 'true';

        const rows = Array.from(container.querySelectorAll('[data-paginate-row]'));
        const controls = container.querySelector('[data-paginate-controls]');
        const info = container.querySelector('[data-paginate-info]');
        const prevBtn = container.querySelector('[data-paginate-prev]');
        const nextBtn = container.querySelector('[data-paginate-next]');
        const pagesContainer = container.querySelector('[data-paginate-pages]');
        const perPageSelect = container.querySelector('[data-paginate-per-page]');
        const perPageWrap = container.querySelector('[data-paginate-per-page-wrap]');
        const showAllBtn = container.querySelector('[data-paginate-show-all]');
        const nav = container.querySelector('[data-paginate-nav]');
        const alwaysShow = container.dataset.paginateAlwaysShow === '1' || container.dataset.paginateAlwaysShow === 'true';
        const defaultPageSize = Math.max(1, parseInt(container.dataset.pageSize || '20', 10));
        const windowSize = 5;

        if (rows.length === 0) {
            controls?.classList.add('hidden');
            return;
        }

        let pageSize = defaultPageSize;
        let showingAll = false;
        let currentPage = container.dataset.paginateStart === 'last'
            ? Math.max(1, Math.ceil(rows.length / pageSize))
            : 1;

        const totalPages = () => Math.max(1, Math.ceil(rows.length / pageSize));

        const syncShowAllButton = () => {
            if (!showAllBtn) {
                return;
            }

            showAllBtn.textContent = showingAll ? 'Show pages' : 'Show all';
        };

        const syncNavVisibility = () => {
            const hideNav = showingAll || totalPages() <= 1;

            nav?.classList.toggle('hidden', hideNav);
            perPageWrap?.classList.toggle('hidden', showingAll);

            if (controls) {
                controls.classList.toggle('hidden', !alwaysShow && rows.length <= 1 && !showingAll);
            }
        };

        const goToPage = (page) => {
            currentPage = Math.min(totalPages(), Math.max(1, page));
            render();
        };

        const renderPageNumbers = () => {
            if (!pagesContainer || showingAll) {
                return;
            }

            pagesContainer.innerHTML = '';

            const lastPage = totalPages();
            let windowStart = Math.max(1, currentPage - Math.floor(windowSize / 2));
            let windowEnd = Math.min(lastPage, windowStart + windowSize - 1);
            windowStart = Math.max(1, windowEnd - windowSize + 1);

            const addEllipsis = () => {
                const span = document.createElement('span');
                span.className = 'px-1.5 text-xs text-gray-400';
                span.textContent = '…';
                pagesContainer.appendChild(span);
            };

            if (windowStart > 1) {
                addEllipsis();
            }

            for (let page = windowStart; page <= windowEnd; page++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = String(page);
                btn.className = page === currentPage
                    ? 'min-w-[1.9rem] rounded-md bg-[#0B318F] px-2 py-1.5 text-xs font-medium text-white'
                    : 'min-w-[1.9rem] rounded-md border border-gray-300 px-2 py-1.5 text-xs text-gray-700 hover:bg-gray-50';
                btn.addEventListener('click', () => goToPage(page));
                pagesContainer.appendChild(btn);
            }

            if (windowEnd < lastPage) {
                addEllipsis();
            }
        };

        const render = () => {
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;

            rows.forEach((row, index) => {
                row.classList.toggle('hidden', index < start || index >= end);
            });

            if (info) {
                if (showingAll) {
                    info.textContent = `Showing all ${rows.length} row${rows.length === 1 ? '' : 's'}`;
                } else if (rows.length <= pageSize) {
                    info.textContent = `Showing all ${rows.length} row${rows.length === 1 ? '' : 's'}`;
                } else {
                    info.textContent = `Showing ${start + 1}–${Math.min(end, rows.length)} of ${rows.length}`;
                }
            }

            if (prevBtn) {
                prevBtn.disabled = showingAll || currentPage === 1;
            }

            if (nextBtn) {
                nextBtn.disabled = showingAll || currentPage === totalPages();
            }

            syncNavVisibility();
            syncShowAllButton();
            renderPageNumbers();
        };

        perPageSelect?.addEventListener('change', () => {
            showingAll = false;
            pageSize = Math.max(1, parseInt(perPageSelect.value || String(defaultPageSize), 10));
            currentPage = 1;
            render();
        });

        showAllBtn?.addEventListener('click', () => {
            if (showingAll) {
                showingAll = false;
                pageSize = Math.max(1, parseInt(perPageSelect?.value || String(defaultPageSize), 10));
                currentPage = 1;
            } else {
                showingAll = true;
                pageSize = rows.length;
                currentPage = 1;
            }

            render();
        });

        prevBtn?.addEventListener('click', () => {
            if (!showingAll && currentPage > 1) {
                currentPage--;
                render();
            }
        });

        nextBtn?.addEventListener('click', () => {
            if (!showingAll && currentPage < totalPages()) {
                currentPage++;
                render();
            }
        });

        render();
    }

    document.querySelectorAll('[data-client-paginate]').forEach(initClientPagination);

    const initTeachingLoadPull = (root) => {
        const startUrl = root.dataset.tlPullStartUrl;
        const stepUrl = root.dataset.tlPullStepUrl;
        const reloadUrl = root.dataset.tlReloadUrl;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const startBtn = root.querySelector('[data-tl-start-pull]');
        const cancelBtn = root.querySelector('[data-tl-cancel-btn]');
        const progressPanel = root.querySelector('[data-tl-progress-panel]');
        const progressBar = root.querySelector('[data-tl-progress-bar]');
        const progressLabel = root.querySelector('[data-tl-progress-label]');
        const progressDetail = root.querySelector('[data-tl-progress-detail]');
        const errorLabel = root.querySelector('[data-tl-pull-error]');
        const selectedCount = root.querySelector('[data-tl-selected-count]');
        const selectAll = root.querySelector('[data-tl-select-all]');
        const dateFrom = root.querySelector('[data-tl-date-from]');
        const dateTo = root.querySelector('[data-tl-date-to]');
        const searchInput = root.querySelector('[data-tl-employee-search]');

        const updateSelectedCount = () => {
            const checked = root.querySelectorAll('[data-tl-employee-row]:checked').length;
            if (selectedCount) {
                selectedCount.textContent = `${checked} selected`;
            }
            return checked;
        };

        root.querySelectorAll('[data-tl-employee-row]').forEach((checkbox) => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        selectAll?.addEventListener('change', () => {
            root.querySelectorAll('[data-tl-employee-item]:not([hidden]) [data-tl-employee-row]').forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            updateSelectedCount();
        });

        searchInput?.addEventListener('input', () => {
            const term = (searchInput.value || '').trim().toLowerCase();

            root.querySelectorAll('[data-tl-employee-item]').forEach((item) => {
                const haystack = item.dataset.tlSearchText || '';
                item.hidden = term !== '' && !haystack.includes(term);
            });

            if (selectAll) {
                selectAll.checked = false;
            }
            updateSelectedCount();
        });

        updateSelectedCount();

        const showError = (message) => {
            if (!errorLabel) {
                return;
            }

            errorLabel.textContent = message;
            errorLabel.classList.remove('hidden');
        };

        const clearError = () => {
            errorLabel?.classList.add('hidden');
            if (errorLabel) {
                errorLabel.textContent = '';
            }
        };

        const setPulling = (pulling) => {
            if (startBtn) {
                startBtn.disabled = pulling;
            }
            if (cancelBtn) {
                cancelBtn.disabled = pulling;
            }

            // Do not disable/clear date fields during pull — values must stay visible.
            // Only lock selection controls and actions.
            root.querySelectorAll('[data-tl-employee-row], [data-tl-select-all], [data-tl-employee-search]').forEach((el) => {
                el.disabled = pulling;
            });
        };

        const resetPullForm = () => {
            if (dateFrom) {
                dateFrom.value = '';
            }
            if (dateTo) {
                dateTo.value = '';
            }
            if (searchInput) {
                searchInput.value = '';
                searchInput.disabled = false;
            }

            root.querySelectorAll('[data-tl-employee-item]').forEach((item) => {
                item.hidden = false;
            });
            root.querySelectorAll('[data-tl-employee-row]').forEach((checkbox) => {
                checkbox.checked = false;
                checkbox.disabled = false;
            });
            if (selectAll) {
                selectAll.checked = false;
                selectAll.disabled = false;
            }

            progressPanel?.classList.add('hidden');
            if (progressBar) {
                progressBar.style.width = '0%';
            }
            if (progressLabel) {
                progressLabel.textContent = '0 / 0';
            }
            if (progressDetail) {
                progressDetail.textContent = '';
            }

            clearError();
            setPulling(false);
            updateSelectedCount();
        };

        const modal = root.closest('.modal-overlay');

        if (modal) {
            const observer = new MutationObserver(() => {
                if (modal.classList.contains('hidden')) {
                    resetPullForm();
                }
            });
            observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
        }

        startBtn?.addEventListener('click', async () => {
            clearError();

            const employeeIds = [...root.querySelectorAll('[data-tl-employee-row]:checked')].map((el) => el.value);

            if (!dateFrom?.value || !dateTo?.value) {
                showError('Date From and Date To are required.');
                return;
            }

            if (dateFrom.value > dateTo.value) {
                showError('Date From must be on or before Date To.');
                return;
            }

            if (employeeIds.length === 0) {
                showError('Select at least one employee.');
                return;
            }

            setPulling(true);
            progressPanel?.classList.remove('hidden');

            try {
                const startResponse = await fetch(startUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        date_from: dateFrom.value,
                        date_to: dateTo.value,
                        employee_ids: employeeIds,
                    }),
                });

                const startPayload = await startResponse.json();

                if (!startResponse.ok || !startPayload.success) {
                    throw new Error(startPayload.message ?? 'Unable to start pull.');
                }

                let done = false;

                while (!done) {
                    const stepResponse = await fetch(stepUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ job_token: startPayload.token }),
                    });

                    const stepPayload = await stepResponse.json();

                    if (!stepResponse.ok || !stepPayload.success) {
                        throw new Error(stepPayload.message ?? 'Pull step failed.');
                    }

                    const current = stepPayload.current ?? 0;
                    const total = stepPayload.total ?? startPayload.total ?? 0;
                    const percent = stepPayload.percent ?? 0;

                    if (progressBar) {
                        progressBar.style.width = `${percent}%`;
                    }
                    if (progressLabel) {
                        progressLabel.textContent = `${current} / ${total}`;
                    }
                    if (progressDetail) {
                        const parts = [];
                        if (stepPayload.employee_number) {
                            parts.push(stepPayload.employee_number);
                        }
                        if (stepPayload.sync_status === 'unchanged') {
                            parts.push('unchanged — skipped');
                        } else if (stepPayload.sync_status === 'updated') {
                            parts.push(`updated — ${stepPayload.records_count ?? 0} session(s)`);
                        } else if (typeof stepPayload.records_count === 'number') {
                            parts.push(`${stepPayload.records_count} session(s)`);
                        }
                        if (stepPayload.error) {
                            parts.push(stepPayload.error);
                        }
                        progressDetail.textContent = parts.join(' — ');
                    }

                    done = Boolean(stepPayload.done);
                }

                window.location.href = reloadUrl;
            } catch (error) {
                showError(error.message ?? 'Teaching load pull failed.');
                setPulling(false);
            }
        });
    };

    document.querySelectorAll('[data-teaching-load-pull-root]').forEach(initTeachingLoadPull);
    document.querySelectorAll('[data-modal-auto-open] [data-teaching-load-pull-root]').forEach(initTeachingLoadPull);

    const initBiometricS3PullForm = (form) => {
        if (! form || form.dataset.biometricS3PullBound === '1') {
            return;
        }

        form.dataset.biometricS3PullBound = '1';

        const foldersUrl = form.dataset.foldersUrl;
        const yearInput = form.querySelector('[data-biometric-s3-year]');
        const monthSelect = form.querySelector('[data-biometric-s3-month]');
        const folderSelect = form.querySelector('[data-biometric-s3-folder]');
        const hint = form.querySelector('[data-biometric-s3-folder-hint]');

        if (! foldersUrl || ! yearInput || ! monthSelect || ! folderSelect) {
            return;
        }

        const selectedFolder = () => folderSelect.value || '';

        const loadFolders = async () => {
            const year = yearInput.value;
            const month = monthSelect.value;

            if (! year || ! month) {
                return;
            }

            const previous = selectedFolder();
            folderSelect.disabled = true;
            if (hint) {
                hint.textContent = 'Loading collector folders from S3…';
            }

            try {
                const url = new URL(foldersUrl, window.location.origin);
                url.searchParams.set('year', year);
                url.searchParams.set('month', month);

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok || ! payload.success) {
                    throw new Error(payload.message || 'Unable to list S3 folders.');
                }

                const folders = Array.isArray(payload.folders) ? payload.folders : [];
                folderSelect.innerHTML = '';

                const allOption = document.createElement('option');
                allOption.value = '';
                allOption.textContent = 'All collectors for the selected month';
                folderSelect.appendChild(allOption);

                folders.forEach((name) => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    if (previous && previous === name) {
                        option.selected = true;
                    }
                    folderSelect.appendChild(option);
                });

                if (hint) {
                    hint.textContent = folders.length
                        ? `${folders.length} collector folder(s) found for ${year}-${String(month).padStart(2, '0')}.`
                        : `No collector folders under biometric_logs/${year}/${String(month).padStart(2, '0')}/ yet.`;
                }
            } catch (error) {
                if (hint) {
                    hint.textContent = error.message || 'Could not load S3 folders.';
                }
            } finally {
                folderSelect.disabled = false;
            }
        };

        yearInput.addEventListener('change', loadFolders);
        monthSelect.addEventListener('change', loadFolders);

        form.addEventListener('submit', () => {
            if (window.PulseLoader) {
                window.PulseLoader.show('Pulling biometric logs from S3…');
            }
        });

        // Load once when the form is present (modal may already be open).
        loadFolders();
    };

    document.querySelectorAll('[data-biometric-s3-pull-form]').forEach(initBiometricS3PullForm);

    const printReportFromSource = (sourceId) => {
        const source = document.getElementById(sourceId);

        if (! source) {
            window.print();

            return;
        }

        pulseLoader.hide();

        const iframe = document.createElement('iframe');
        iframe.setAttribute('title', 'Print report');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none;';
        document.body.appendChild(iframe);

        const frameWindow = iframe.contentWindow;
        const frameDocument = frameWindow?.document;

        if (! frameDocument) {
            iframe.remove();
            window.print();

            return;
        }

        const title = document.title.replace(/</g, '');
        frameDocument.open();
        frameDocument.write(`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>${title}</title>
<style>
@page { margin: 12mm; }
html, body {
  margin: 0;
  padding: 0;
  background: #fff;
  color: #111;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
body { padding: 0; }
table { width: 100%; border-collapse: collapse; }
th, td { color: #111 !important; background: #fff !important; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
</style>
</head>
<body>${source.innerHTML}</body>
</html>`);
        frameDocument.close();

        const cleanup = () => {
            setTimeout(() => iframe.remove(), 500);
        };

        const triggerPrint = () => {
            try {
                frameWindow.focus();
                frameWindow.print();
            } finally {
                cleanup();
            }
        };

        // Safari needs the iframe document fully ready before print.
        if (frameDocument.readyState === 'complete') {
            setTimeout(triggerPrint, 50);
        } else {
            iframe.addEventListener('load', () => setTimeout(triggerPrint, 50), { once: true });
            setTimeout(triggerPrint, 300);
        }
    };

    window.printReportFromSource = printReportFromSource;

    document.querySelectorAll('[data-report-print]').forEach((button) => {
        button.addEventListener('click', () => {
            const sourceId = button.getAttribute('data-report-print-source') || 'report-print-document';
            printReportFromSource(sourceId);
        });
    });

    window.addEventListener('beforeprint', () => {
        pulseLoader.hide();
    });

    const loadOvertimeExcessPreview = async (form, { autofill = true } = {}) => {
        const previewUrl = form.getAttribute('data-ot-preview-url');
        const workDateInput = form.querySelector('[data-ot-work-date]');
        const startInput = form.querySelector('[data-ot-start]');
        const endInput = form.querySelector('[data-ot-end]');
        const hint = form.querySelector('[data-ot-excess-hint]');

        if (! previewUrl || ! workDateInput || ! hint) {
            return;
        }

        const workDate = workDateInput.value;

        if (! workDate) {
            hint.classList.add('hidden');
            hint.textContent = '';
            return;
        }

        hint.classList.remove('hidden');
        hint.textContent = 'Loading excess hours…';

        try {
            const url = new URL(previewUrl, window.location.origin);
            url.searchParams.set('work_date', workDate);
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (! response.ok) {
                hint.textContent = 'Unable to load excess hours for this date.';
                return;
            }

            const payload = await response.json();

            if (! payload.ok) {
                hint.textContent = payload.message || 'No excess hours for this date.';
                return;
            }

            const windows = Array.isArray(payload.windows) ? payload.windows : [];
            const labels = windows.map((window) => `${window.label} (${window.minutes} min)`).join('; ');
            const total = Number(payload.excess_minutes || 0);
            hint.textContent = labels
                ? `Excess outside shift: ${labels}. Total ${total} min — OT fields auto-filled; adjust if needed.`
                : `Excess outside shift: ${total} min.`;

            if (autofill && startInput && endInput && payload.suggested_ot_start && payload.suggested_ot_end) {
                startInput.value = payload.suggested_ot_start;
                endInput.value = payload.suggested_ot_end;
            }
        } catch {
            hint.textContent = 'Unable to load excess hours for this date.';
        }
    };

    document.addEventListener('change', (event) => {
        const workDateInput = event.target.closest('[data-ot-work-date]');

        if (! workDateInput) {
            return;
        }

        const form = workDateInput.closest('[data-ot-approval-form]');

        if (form) {
            loadOvertimeExcessPreview(form, { autofill: true });
        }
    });

    document.querySelectorAll('[data-ot-approval-form]').forEach((form) => {
        const startInput = form.querySelector('[data-ot-start]');
        const hasOldTimes = Boolean(startInput?.value);
        loadOvertimeExcessPreview(form, { autofill: ! hasOldTimes });
    });

    const initEmployeeSkolarisSync = () => {
        const root = document.querySelector('[data-employee-skolaris-sync]');
        if (!root) {
            return;
        }

        const pendingUrl = root.dataset.pendingUrl || '';
        const previewUrl = root.dataset.previewUrl || '';
        const applyUrl = root.dataset.applyUrl || '';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const rowsEl = root.querySelector('[data-employee-sync-rows]');
        const errorEl = root.querySelector('[data-employee-sync-error]');
        const successEl = root.querySelector('[data-employee-sync-success]');
        const selectedCountEl = root.querySelector('[data-employee-sync-selected-count]');
        const selectAll = root.querySelector('[data-employee-sync-select-all]');
        const searchInput = root.querySelector('[data-employee-sync-search]');
        const multipleBtn = root.querySelector('[data-employee-sync-multiple]');
        const approveAllBtn = root.querySelector('[data-employee-sync-all]');
        const countBadges = document.querySelectorAll('[data-employee-sync-count]');
        const viewModal = document.getElementById('employee-skolaris-sync-view-modal');
        let loaded = false;
        let loading = false;

        const setCount = (count) => {
            const numeric = Number(count);
            const show = count !== '—' && count !== '' && ! Number.isNaN(numeric) && numeric > 0;

            countBadges.forEach((badge) => {
                badge.textContent = show ? String(count) : '';
                badge.classList.toggle('hidden', !show);
                badge.classList.toggle('inline-flex', show);
            });
        };

        const setError = (message) => {
            if (!errorEl) {
                return;
            }

            errorEl.textContent = message || '';
            errorEl.classList.toggle('hidden', !message);
        };

        const setSuccess = (message) => {
            if (!successEl) {
                return;
            }

            successEl.textContent = message || '';
            successEl.classList.toggle('hidden', !message);
        };

        const visibleRows = () => [...root.querySelectorAll('[data-employee-sync-row]')].filter((row) => !row.classList.contains('hidden'));

        const rowNumbers = (rows) => rows
            .map((row) => row.dataset.employeeNumber)
            .filter(Boolean);

        const selectedNumbers = () => rowNumbers(visibleRows()
            .filter((row) => row.querySelector('[data-employee-sync-row-check]')?.checked));

        const syncSelectionUi = () => {
            const rows = visibleRows();
            const checked = selectedNumbers();
            if (selectedCountEl) {
                selectedCountEl.textContent = `${checked.length} selected`;
            }
            if (multipleBtn) {
                multipleBtn.disabled = checked.length === 0;
            }
            if (approveAllBtn) {
                approveAllBtn.disabled = rows.length === 0;
            }
            if (selectAll) {
                selectAll.checked = rows.length > 0 && rows.every((row) => row.querySelector('[data-employee-sync-row-check]')?.checked);
            }
        };

        const formatValue = (value) => {
            if (value === null || value === undefined || value === '') {
                return '—';
            }
            if (typeof value === 'boolean') {
                return value ? 'Yes' : 'No';
            }
            if (typeof value === 'object') {
                return JSON.stringify(value, null, 2);
            }

            return String(value);
        };

        const escapeHtml = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');

        const applySearch = () => {
            const query = (searchInput?.value || '').trim().toLowerCase();
            root.querySelectorAll('[data-employee-sync-row]').forEach((row) => {
                const haystack = row.dataset.searchText || '';
                row.classList.toggle('hidden', query !== '' && !haystack.includes(query));
            });
            syncSelectionUi();
        };

        const renderRows = (employees) => {
            if (!rowsEl) {
                return;
            }

            if (!employees.length) {
                rowsEl.innerHTML = `<tr data-employee-sync-empty><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No employee profiles need approval from ISKOLARIS.</td></tr>`;
                syncSelectionUi();
                return;
            }

            rowsEl.innerHTML = employees.map((employee) => {
                const kind = employee.kind === 'unmatched' ? 'Not in People360' : (employee.kind === 'new' ? 'New' : 'Changed');
                const kindClass = employee.kind === 'unmatched'
                    ? 'bg-amber-100 text-amber-800'
                    : (employee.kind === 'new' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800');
                const syncKey = escapeHtml(employee.employee_number || '');
                const displayNumber = escapeHtml(employee.pulse_employee_number || employee.employee_number || '');
                const name = escapeHtml(employee.name || '');
                const search = escapeHtml(`${employee.employee_number || ''} ${employee.pulse_employee_number || ''} ${employee.name || ''}`.toLowerCase());

                return `<tr class="border-t border-gray-100" data-employee-sync-row data-employee-number="${syncKey}" data-search-text="${search}">
                    <td class="px-3 py-2">
                        <input type="checkbox" class="rounded border-gray-300 text-[#00A3E6] focus:ring-[#00A3E6]" data-employee-sync-row-check value="${syncKey}">
                    </td>
                    <td class="px-3 py-2 font-medium text-gray-900">${displayNumber}</td>
                    <td class="px-3 py-2 text-gray-700">${name}</td>
                    <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${kindClass}">${kind}</span></td>
                    <td class="px-3 py-2 text-gray-600">${Number(employee.change_count || 0)}</td>
                    <td class="px-3 py-2">
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-ghost !px-2 !py-1 text-xs" data-employee-sync-view data-employee-number="${syncKey}">View</button>
                            <button type="button" class="btn-primary !px-2 !py-1 text-xs" data-employee-sync-one data-employee-number="${syncKey}">Approve</button>
                        </div>
                    </td>
                </tr>`;
            }).join('');

            applySearch();
        };

        const fetchPending = async ({ refresh = false, showLoader = true } = {}) => {
            if (!pendingUrl || loading) {
                return;
            }

            loading = true;
            if (showLoader) {
                window.PulseLoader?.show('Checking ISKOLARIS employee profiles...');
            }

            try {
                const url = new URL(pendingUrl, window.location.origin);
                if (refresh) {
                    url.searchParams.set('refresh', '1');
                }

                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok || payload.ok === false) {
                    throw new Error(payload.error || payload.message || 'Unable to load ISKOLARIS employee profiles.');
                }

                loaded = true;
                setCount(payload.count ?? (payload.employees || []).length);
                setError('');
                renderRows(Array.isArray(payload.employees) ? payload.employees : []);
            } catch (error) {
                setCount('—');
                setError(error.message || 'Unable to load ISKOLARIS employee profiles.');
                if (rowsEl && !loaded) {
                    rowsEl.innerHTML = `<tr data-employee-sync-empty><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Unable to load profiles. Check the ISKOLARIS API key permission for employee sync.</td></tr>`;
                }
            } finally {
                loading = false;
                if (showLoader) {
                    window.PulseLoader?.hide();
                }
            }
        };

        const applyNumbers = async (employeeNumbers) => {
            const unique = [...new Set(employeeNumbers.filter(Boolean))];
            if (!applyUrl || unique.length === 0) {
                return;
            }

            window.PulseLoader?.show('Approving employee profiles...');
            setSuccess('');
            setError('');

            const chunkSize = 200;
            let created = 0;
            let updated = 0;
            const failed = [];
            let lastCount = null;
            let lastMessage = '';

            try {
                for (let offset = 0; offset < unique.length; offset += chunkSize) {
                    const chunk = unique.slice(offset, offset + chunkSize);
                    const response = await fetch(applyUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ employee_numbers: chunk }),
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(payload.message || 'Approve failed.');
                    }

                    created += Number(payload.created || 0);
                    updated += Number(payload.updated || 0);
                    if (Array.isArray(payload.failed)) {
                        failed.push(...payload.failed);
                    }
                    if (typeof payload.count === 'number') {
                        lastCount = payload.count;
                    }
                    lastMessage = payload.message || lastMessage;
                }

                if (typeof lastCount === 'number') {
                    setCount(lastCount);
                }

                if (failed.length) {
                    setError(failed.map((item) => `${item.employee_number}: ${item.message}`).join(' '));
                }

                const summaryParts = [];
                if (created > 0) {
                    summaryParts.push(`${created} created`);
                }
                if (updated > 0) {
                    summaryParts.push(`${updated} updated`);
                }
                if (failed.length) {
                    summaryParts.push(`${failed.length} failed`);
                }

                setSuccess(summaryParts.length
                    ? `ISKOLARIS approval finished: ${summaryParts.join(', ')}.`
                    : (lastMessage || 'Approval finished.'));
                await fetchPending({ refresh: true, showLoader: false });
            } catch (error) {
                setError(error.message || 'Approve failed.');
            } finally {
                window.PulseLoader?.hide();
            }
        };

        const showPreview = async (employeeNumber) => {
            if (!previewUrl || !viewModal) {
                return;
            }

            const nameEl = viewModal.querySelector('[data-employee-sync-view-name]');
            const metaEl = viewModal.querySelector('[data-employee-sync-view-meta]');
            const bodyEl = viewModal.querySelector('[data-employee-sync-view-rows]');
            if (nameEl) {
                nameEl.textContent = employeeNumber;
            }
            if (metaEl) {
                metaEl.textContent = 'Loading...';
            }
            if (bodyEl) {
                bodyEl.innerHTML = `<tr><td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">Loading changes…</td></tr>`;
            }

            openModal(viewModal, { stack: true });

            try {
                const url = new URL(previewUrl, window.location.origin);
                url.searchParams.set('employee_number', employeeNumber);
                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.ok === false) {
                    throw new Error(payload.message || 'Unable to load changes.');
                }

                if (nameEl) {
                    nameEl.textContent = payload.name || employeeNumber;
                }
                if (metaEl) {
                    const kind = payload.kind === 'new' ? 'New in People360' : 'Changed fields';
                    metaEl.textContent = `${payload.employee_number || employeeNumber} · ${kind}`;
                }

                const changes = Array.isArray(payload.changes) ? payload.changes : [];
                if (bodyEl) {
                    if (!changes.length) {
                        bodyEl.innerHTML = `<tr><td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No field differences stored for this profile.</td></tr>`;
                    } else {
                        bodyEl.innerHTML = changes.map((change) => {
                            const oldValue = formatValue(change.old);
                            const newValue = formatValue(change.new);
                            const oldHtml = oldValue.includes('\n')
                                ? `<pre class="max-h-40 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-2 text-xs">${escapeHtml(oldValue)}</pre>`
                                : escapeHtml(oldValue);
                            const newHtml = newValue.includes('\n')
                                ? `<pre class="max-h-40 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-2 text-xs">${escapeHtml(newValue)}</pre>`
                                : escapeHtml(newValue);

                            return `<tr>
                                <td class="px-3 py-2 font-medium text-gray-900">${escapeHtml(change.label || change.field || '')}</td>
                                <td class="px-3 py-2 align-top text-gray-600">${oldHtml}</td>
                                <td class="px-3 py-2 align-top text-gray-900">${newHtml}</td>
                            </tr>`;
                        }).join('');
                    }
                }
            } catch (error) {
                if (metaEl) {
                    metaEl.textContent = error.message || 'Unable to load changes.';
                }
            }
        };

        document.addEventListener('click', (event) => {
            const openTrigger = event.target.closest('[data-modal-open="employee-skolaris-sync-modal"]');
            if (openTrigger && !loaded && !loading) {
                fetchPending({ showLoader: true });
            }
        });

        root.addEventListener('click', (event) => {
            if (event.target.closest('[data-employee-sync-refresh]')) {
                fetchPending({ refresh: true, showLoader: true });
                return;
            }

            const viewBtn = event.target.closest('[data-employee-sync-view]');
            if (viewBtn) {
                showPreview(viewBtn.dataset.employeeNumber || '');
                return;
            }

            const oneBtn = event.target.closest('[data-employee-sync-one]');
            if (oneBtn) {
                applyNumbers([oneBtn.dataset.employeeNumber || ''].filter(Boolean));
                return;
            }

            if (event.target.closest('[data-employee-sync-multiple]')) {
                applyNumbers(selectedNumbers());
                return;
            }

            if (event.target.closest('[data-employee-sync-all]')) {
                const numbers = rowNumbers(visibleRows());
                if (numbers.length === 0) {
                    return;
                }

                if (!window.confirm(`Approve all ${numbers.length} shown profile(s) from ISKOLARIS?`)) {
                    return;
                }

                applyNumbers(numbers);
            }
        });

        root.addEventListener('change', (event) => {
            if (event.target.matches('[data-employee-sync-select-all]')) {
                const checked = event.target.checked;
                visibleRows().forEach((row) => {
                    const box = row.querySelector('[data-employee-sync-row-check]');
                    if (box) {
                        box.checked = checked;
                    }
                });
                syncSelectionUi();
                return;
            }

            if (event.target.matches('[data-employee-sync-row-check]')) {
                syncSelectionUi();
            }
        });

        searchInput?.addEventListener('input', applySearch);
    };

    initEmployeeSkolarisSync();
    initGovernmentIdInputs();
});
