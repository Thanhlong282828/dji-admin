const sidebar = document.querySelector<HTMLElement>('[data-sidebar]');
const openButton = document.querySelector<HTMLButtonElement>('[data-sidebar-open]');
const closeButton = document.querySelector<HTMLButtonElement>('[data-sidebar-close]');
const overlay = document.querySelector<HTMLElement>('[data-sidebar-overlay]');
const navigation = document.querySelector<HTMLElement>('[data-primary-navigation]');
const search = document.querySelector<HTMLInputElement>('[data-nav-search]');

if (sidebar && openButton && closeButton && overlay && navigation) {
    const mobile = () => window.matchMedia('(max-width: 760px)').matches;
    const focusable = () => Array.from(sidebar.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),input:not([disabled])')).filter(element => !element.hidden && element.offsetParent !== null);
    const setDrawer = (open: boolean, restoreFocus = true) => {
        sidebar.classList.toggle('is-open', open);
        sidebar.setAttribute('aria-hidden', mobile() && !open ? 'true' : 'false');
        openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        overlay.hidden = !open;
        document.body.classList.toggle('sidebar-drawer-open', open);
        if (open) closeButton.focus();
        else if (restoreFocus) openButton.focus();
    };
    openButton.addEventListener('click', () => setDrawer(true));
    closeButton.addEventListener('click', () => setDrawer(false));
    overlay.addEventListener('click', () => setDrawer(false));
    sidebar.addEventListener('keydown', event => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) setDrawer(false);
        if (event.key !== 'Tab' || !mobile() || !sidebar.classList.contains('is-open')) return;
        const elements = focusable();
        const first = elements.at(0);
        const last = elements.at(-1);
        if (!first || !last) return;
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    window.addEventListener('resize', () => {
        if (!mobile()) {
            sidebar.classList.remove('is-open');
            sidebar.setAttribute('aria-hidden', 'false');
            overlay.hidden = true;
            document.body.classList.remove('sidebar-drawer-open');
        } else if (!sidebar.classList.contains('is-open')) sidebar.setAttribute('aria-hidden', 'true');
    });

    const groups = Array.from(navigation.querySelectorAll<HTMLElement>('[data-nav-group]'));
    const storageKey = 'schema.admin.navigation.open-groups';
    const persisted = (() => { try { const value = JSON.parse(localStorage.getItem(storageKey) ?? '[]'); return Array.isArray(value) ? value.filter(item => typeof item === 'string').slice(0, 1) : []; } catch { return []; } })();
    const activeKey = groups.find(group => group.dataset.routeActive === 'true')?.dataset.navGroup;
    const initial = activeKey ? [activeKey] : persisted.slice(0, 1);
    let normalOpenKey = initial.at(0) ?? null;
    let searching = false;
    const setGroup = (group: HTMLElement, expanded: boolean) => {
        const toggle = group.querySelector<HTMLButtonElement>('[data-nav-group-toggle]');
        const items = group.querySelector<HTMLElement>('.nav-group-items');
        if (!toggle || !items) return;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        items.hidden = !expanded;
        group.classList.toggle('is-open', expanded);
    };
    groups.forEach(group => {
        setGroup(group, initial.includes(group.dataset.navGroup ?? ''));
        group.querySelector<HTMLButtonElement>('[data-nav-group-toggle]')?.addEventListener('click', () => {
            const open = group.querySelector('[data-nav-group-toggle]')?.getAttribute('aria-expanded') !== 'true';
            if (open) {
                groups.filter(candidate => candidate !== group).forEach(candidate => setGroup(candidate, false));
            }
            setGroup(group, open);
            normalOpenKey = open ? group.dataset.navGroup ?? null : null;
            searching = false;
            localStorage.setItem(storageKey, JSON.stringify(normalOpenKey ? [normalOpenKey] : []));
        });
    });
    navigation.querySelectorAll<HTMLAnchorElement>('a[href]').forEach(link => link.addEventListener('click', () => { if (mobile()) setDrawer(false, false); }));

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase('vi');
        if (query !== '' && !searching) searching = true;
        let visible = 0;
        groups.forEach(group => {
            const items = Array.from(group.querySelectorAll<HTMLAnchorElement>('.nav-item'));
            const groupMatches = group.querySelector('.nav-group-toggle')?.textContent?.toLocaleLowerCase('vi').includes(query) ?? false;
            let matches = 0;
            items.forEach(item => { const match = query === '' || groupMatches || (item.dataset.navLabel ?? '').includes(query); item.hidden = !match; if (match) matches++; });
            group.hidden = query !== '' && matches === 0;
            if (query !== '' && matches > 0) setGroup(group, true);
            if (matches > 0) visible += matches;
        });
        if (query === '') {
            searching = false;
            groups.forEach(group => setGroup(group, group.dataset.navGroup === normalOpenKey));
        }
        const empty = navigation.querySelector<HTMLElement>('[data-nav-search-empty]');
        if (empty) empty.hidden = visible > 0;
    });
    if (mobile()) setDrawer(false, false);
}

document.querySelectorAll<HTMLTableCellElement>('table thead th').forEach(header => {
    if (!header.hasAttribute('scope')) header.setAttribute('scope', 'col');
});
document.querySelectorAll<HTMLElement>('.table-scroll').forEach(container => {
    if (!container.hasAttribute('tabindex')) container.tabIndex = 0;
    if (!container.hasAttribute('role')) container.setAttribute('role', 'region');
    if (!container.hasAttribute('aria-label')) container.setAttribute('aria-label', 'Bảng dữ liệu có thể cuộn ngang');
});
