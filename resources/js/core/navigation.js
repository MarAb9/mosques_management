const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

export function initNavigation() {
    const body = document.body;
    const sidebar = document.getElementById('appSidebar');
    const openButton = document.getElementById('mobileNavToggle');
    const closeButton = document.getElementById('mobileNavClose');
    const overlay = document.getElementById('sidebarOverlay');
    const collapseButton = document.getElementById('sidebarCollapse');
    let restoreFocus = null;

    const setOpen = (open) => {
        body.classList.toggle('sidebar-open', open);
        openButton?.setAttribute('aria-expanded', String(open));
        sidebar?.setAttribute('aria-hidden', String(!open && matchMedia('(max-width: 991.98px)').matches));
        if (open) {
            restoreFocus = document.activeElement;
            requestAnimationFrame(() => sidebar?.querySelector(focusableSelector)?.focus());
        } else if (restoreFocus instanceof HTMLElement) {
            restoreFocus.focus();
        }
    };

    openButton?.addEventListener('click', () => setOpen(true));
    closeButton?.addEventListener('click', () => setOpen(false));
    overlay?.addEventListener('click', () => setOpen(false));

    collapseButton?.addEventListener('click', () => {
        const collapsed = body.classList.toggle('sidebar-collapsed');
        collapseButton.setAttribute('aria-expanded', String(!collapsed));
        try { localStorage.setItem('atlas-noor-sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (_) {}
    });

    try {
        if (localStorage.getItem('atlas-noor-sidebar') === 'collapsed' && matchMedia('(min-width: 992px)').matches) {
            body.classList.add('sidebar-collapsed');
            collapseButton?.setAttribute('aria-expanded', 'false');
        }
    } catch (_) {}

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && body.classList.contains('sidebar-open')) setOpen(false);
        if (event.key !== 'Tab' || !body.classList.contains('sidebar-open') || !sidebar) return;
        const focusable = [...sidebar.querySelectorAll(focusableSelector)].filter((element) => element.offsetParent !== null);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });

    matchMedia('(min-width: 992px)').addEventListener?.('change', (event) => {
        if (event.matches) setOpen(false);
        sidebar?.removeAttribute('aria-hidden');
    });
}
