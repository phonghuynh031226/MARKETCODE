/* Shared responsive navigation. Existing server-rendered links remain untouched. */
(() => {
    'use strict';
    if (window.marketNavigationReady) return;
    window.marketNavigationReady = true;
    const pairs = [['mobileMenuBtn', 'mobileMenu'], ['notiMenuBtn', 'notiMenuDropdown'], ['userMenuBtn', 'userMenuDropdown']]
        .map(([button, panel]) => ({button: document.getElementById(button), panel: document.getElementById(panel)}))
        .filter(({button, panel}) => button && panel);
    const setOpen = (entry, open) => {
        entry.panel.classList.toggle('hidden', !open);
        entry.button.setAttribute('aria-expanded', String(open));
        if (open && entry.panel.id !== 'mobileMenu' && innerWidth >= 640) {
            entry.panel.style.transform = '';
            const bounds = entry.panel.getBoundingClientRect();
            const shift = bounds.left < 16 ? 16 - bounds.left : bounds.right > innerWidth - 16 ? innerWidth - 16 - bounds.right : 0;
            entry.panel.style.transform = `translateX(${shift}px)`;
        }
    };
    pairs.forEach(entry => entry.button.addEventListener('click', () => {
        const open = entry.button.getAttribute('aria-expanded') !== 'true';
        pairs.forEach(other => setOpen(other, other === entry && open));
    }));
    document.addEventListener('click', event => pairs.forEach(entry => {
        if (!entry.button.contains(event.target) && !entry.panel.contains(event.target)) setOpen(entry, false);
    }));
    const sidebar = document.getElementById('adminSidebar');
    const toggle = document.getElementById('adminMenuBtn');
    const backdrop = document.getElementById('adminMenuBackdrop');
    let previousFocus;
    const setSidebar = open => {
        if (!sidebar || !toggle || !backdrop) return;
        const isMobile = matchMedia('(max-width:1023px)').matches;
        open = open && isMobile;
        document.body.classList.toggle('admin-nav-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        sidebar.inert = isMobile && !open;
        sidebar.setAttribute('aria-hidden', String(isMobile && !open));
        backdrop.hidden = !open;
        if (open) {
            previousFocus = document.activeElement;
            sidebar.querySelector('button,a')?.focus();
        } else if (previousFocus && isMobile) { previousFocus.focus(); previousFocus = null; }
    };
    toggle?.addEventListener('click', () => setSidebar(!document.body.classList.contains('admin-nav-open')));
    backdrop?.addEventListener('click', () => setSidebar(false));
    document.getElementById('adminMenuClose')?.addEventListener('click', () => setSidebar(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            pairs.forEach(entry => { if (entry.button.getAttribute('aria-expanded') === 'true') { setOpen(entry, false); entry.button.focus(); } });
            setSidebar(false);
        }
        if (event.key === 'Tab' && sidebar && document.body.classList.contains('admin-nav-open')) {
            const focusable = [...sidebar.querySelectorAll('a[href],button,input,select,textarea,[tabindex="0"]')].filter(el => !el.disabled && el.getClientRects().length);
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    window.addEventListener('resize', () => {
        pairs.forEach(entry => setOpen(entry, false));
        setSidebar(false);
    });
    setSidebar(false);
})();
