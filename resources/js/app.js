document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('app-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const closeBtn = document.getElementById('sidebar-close');
    const desktopQuery = window.matchMedia('(min-width: 1024px)');

    const isDesktop = () => desktopQuery.matches;

    const openSidebar = () => {
        sidebar?.classList.remove('-translate-x-full');
        if (!isDesktop()) {
            overlay?.classList.remove('hidden');
            document.body.classList.add('sidebar-open');
        }
    };

    const closeSidebar = () => {
        if (isDesktop()) {
            return;
        }

        sidebar?.classList.add('-translate-x-full');
        overlay?.classList.add('hidden');
        document.body.classList.remove('sidebar-open');
    };

    const syncSidebar = () => {
        if (isDesktop()) {
            sidebar?.classList.remove('-translate-x-full');
            overlay?.classList.add('hidden');
            document.body.classList.remove('sidebar-open');
        } else {
            sidebar?.classList.add('-translate-x-full');
            overlay?.classList.add('hidden');
            document.body.classList.remove('sidebar-open');
        }
    };

    syncSidebar();
    desktopQuery.addEventListener('change', syncSidebar);

    toggle?.addEventListener('click', () => {
        if (sidebar?.classList.contains('-translate-x-full')) {
            openSidebar();
        } else {
            closeSidebar();
        }
    });

    overlay?.addEventListener('click', closeSidebar);
    closeBtn?.addEventListener('click', closeSidebar);

    document.querySelectorAll('[data-sidebar-close]').forEach((link) => {
        link.addEventListener('click', () => {
            if (!isDesktop()) {
                closeSidebar();
            }
        });
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

    const openModal = (modal) => {
        if (!modal) {
            return;
        }

        document.querySelectorAll('.modal-overlay:not(.hidden)').forEach((openModalEl) => {
            if (openModalEl !== modal) {
                closeModal(openModalEl);
            }
        });

        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    };

    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            openModal(document.getElementById(trigger.dataset.modalOpen));
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            closeModal(trigger.closest('.modal-overlay'));
        });
    });

    document.querySelectorAll('[data-modal-auto-open]').forEach((modal) => {
        openModal(modal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.modal-overlay:not(.hidden)').forEach((modal) => {
            closeModal(modal);
        });
    });
});
