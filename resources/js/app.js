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
            button.textContent = isHidden ? 'ITAGO' : 'IPAKITA';
        });
    });
});
