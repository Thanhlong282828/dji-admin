const sidebar = document.querySelector<HTMLElement>('[data-sidebar]');
const openButton = document.querySelector<HTMLButtonElement>('[data-sidebar-open]');
const closeButton = document.querySelector<HTMLButtonElement>('[data-sidebar-close]');
const overlay = document.querySelector<HTMLElement>('[data-sidebar-overlay]');
const navigation = document.querySelector<HTMLElement>('[data-primary-navigation]');
const search = document.querySelector<HTMLInputElement>('[data-nav-search]');

const openGroupStorageKey = 'schema.admin.navigation.open-groups';
const scrollStorageKey = 'schema.admin.navigation.scroll-top';

if (sidebar && openButton && closeButton && overlay && navigation && navigation.dataset.initialized !== 'true') {
    navigation.dataset.initialized = 'true';
    const mobile = (): boolean => window.matchMedia('(max-width: 760px)').matches;
    const groups = Array.from(navigation.querySelectorAll<HTMLElement>('[data-nav-group]'));
    const focusable = (): HTMLElement[] => Array
        .from(sidebar.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),input:not([disabled])'))
        .filter(element => !element.hidden && element.offsetParent !== null);

    const setDrawer = (open: boolean, restoreFocus = true): void => {
        sidebar.classList.toggle('is-open', open);
        sidebar.setAttribute('aria-hidden', mobile() && !open ? 'true' : 'false');
        openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        overlay.hidden = !open;
        document.body.classList.toggle('sidebar-drawer-open', open);
        if (open) closeButton.focus();
        else if (restoreFocus) openButton.focus();
    };

    const setGroup = (group: HTMLElement, expanded: boolean): void => {
        const toggle = group.querySelector<HTMLButtonElement>('[data-nav-group-toggle]');
        const items = group.querySelector<HTMLElement>('.nav-group-items');
        if (!toggle || !items) return;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        items.hidden = !expanded;
        group.classList.toggle('is-open', expanded);
    };

    const validRenderedGroup = (key: unknown): string | null => {
        if (typeof key !== 'string') return null;
        return groups.some(group => group.dataset.navGroup === key) ? key : null;
    };

    const readPersistedGroup = (): string | null => {
        try {
            const value: unknown = JSON.parse(localStorage.getItem(openGroupStorageKey) ?? '[]');
            return validRenderedGroup(Array.isArray(value) ? value.at(0) : null);
        } catch {
            return null;
        }
    };

    const persistGroup = (key: string | null): void => {
        try {
            localStorage.setItem(openGroupStorageKey, JSON.stringify(key ? [key] : []));
        } catch {
            // UI preference storage is optional; permissions remain server-side.
        }
    };

    const activeKey = validRenderedGroup(groups.find(group => group.dataset.routeActive === 'true')?.dataset.navGroup);
    let normalOpenKey = activeKey ?? readPersistedGroup();
    let searching = false;

    const applyNormalGroupState = (): void => {
        groups.forEach(group => setGroup(group, group.dataset.navGroup === normalOpenKey));
    };

    const saveScrollPosition = (): void => {
        try {
            sessionStorage.setItem(scrollStorageKey, String(Math.max(0, Math.round(navigation.scrollTop))));
        } catch {
            // Session storage can be unavailable in privacy modes.
        }
    };

    const readScrollPosition = (): number | null => {
        try {
            const value = Number.parseInt(sessionStorage.getItem(scrollStorageKey) ?? '', 10);
            return Number.isFinite(value) && value >= 0 ? value : null;
        } catch {
            return null;
        }
    };

    const revealActiveItemMinimally = (): void => {
        const active = navigation.querySelector<HTMLElement>('.nav-item[aria-current="page"]');
        if (!active) return;
        const containerRect = navigation.getBoundingClientRect();
        const activeRect = active.getBoundingClientRect();
        const edgePadding = 8;
        if (activeRect.top < containerRect.top + edgePadding) {
            navigation.scrollTop -= containerRect.top + edgePadding - activeRect.top;
        } else if (activeRect.bottom > containerRect.bottom - edgePadding) {
            navigation.scrollTop += activeRect.bottom - containerRect.bottom + edgePadding;
        }
    };

    const restoreNavigationState = (): void => {
        normalOpenKey = activeKey ?? readPersistedGroup();
        applyNormalGroupState();
        const scrollTop = readScrollPosition();
        if (scrollTop !== null) navigation.scrollTop = scrollTop;
        revealActiveItemMinimally();
        document.documentElement.dataset.adminNavReady = 'true';
        delete document.documentElement.dataset.adminNavInitializing;
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
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.addEventListener('resize', () => {
        if (!mobile()) {
            sidebar.classList.remove('is-open');
            sidebar.setAttribute('aria-hidden', 'false');
            overlay.hidden = true;
            document.body.classList.remove('sidebar-drawer-open');
        } else if (!sidebar.classList.contains('is-open')) {
            sidebar.setAttribute('aria-hidden', 'true');
        }
    });

    groups.forEach(group => {
        group.querySelector<HTMLButtonElement>('[data-nav-group-toggle]')?.addEventListener('click', () => {
            const key = group.dataset.navGroup ?? null;
            const willOpen = group.querySelector('[data-nav-group-toggle]')?.getAttribute('aria-expanded') !== 'true';
            normalOpenKey = willOpen ? key : null;
            searching = false;
            applyNormalGroupState();
            persistGroup(normalOpenKey);
        });
    });

    navigation.querySelectorAll<HTMLAnchorElement>('a[href]').forEach(link => {
        link.addEventListener('click', () => {
            persistGroup(link.closest<HTMLElement>('[data-nav-group]')?.dataset.navGroup ?? normalOpenKey);
            saveScrollPosition();
            if (mobile()) setDrawer(false, false);
        });
    });

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase('vi');
        if (query !== '') searching = true;
        let visible = 0;
        groups.forEach(group => {
            const items = Array.from(group.querySelectorAll<HTMLAnchorElement>('.nav-item'));
            const groupMatches = (group.querySelector('.nav-group-toggle')?.textContent ?? '').toLocaleLowerCase('vi').includes(query);
            let matches = 0;
            items.forEach(item => {
                const label = (item.dataset.navLabel ?? item.textContent ?? '').toLocaleLowerCase('vi');
                const match = query === '' || groupMatches || label.includes(query);
                item.hidden = !match;
                if (match) matches++;
            });
            group.hidden = query !== '' && matches === 0;
            if (query !== '' && matches > 0) setGroup(group, true);
            visible += matches;
        });
        if (query === '') {
            searching = false;
            applyNormalGroupState();
        }
        const empty = navigation.querySelector<HTMLElement>('[data-nav-search-empty]');
        if (empty) empty.hidden = visible > 0;
    });

    window.addEventListener('pagehide', () => {
        if (!searching) persistGroup(normalOpenKey);
        saveScrollPosition();
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) restoreNavigationState();
    });

    restoreNavigationState();
    if (mobile()) setDrawer(false, false);
}

const wireDialog = (
    triggerSelector: string,
    dialogSelector: string,
    bodySelector?: string
): void => {
    const trigger = document.querySelector<HTMLButtonElement>(triggerSelector);
    const dialog = document.querySelector<HTMLDialogElement>(dialogSelector);
    if (!trigger || !dialog || dialog.dataset.initialized === 'true') return;

    dialog.dataset.initialized = 'true';
    trigger.addEventListener('click', async () => {
        if (bodySelector && trigger.dataset.url) {
            const body = dialog.querySelector<HTMLElement>(bodySelector);
            if (body) {
                try {
                    const response = await fetch(trigger.dataset.url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
                    if (response.ok) body.innerHTML = await response.text();
                    else body.textContent = 'Không thể tải dữ liệu tham chiếu.';
                } catch {
                    body.textContent = 'Không thể tải dữ liệu tham chiếu.';
                }
            }
        }
        dialog.showModal();
    });

    dialog.querySelectorAll<HTMLButtonElement>('[data-dialog-close]').forEach(button => {
        button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('close', () => trigger.focus());
};

wireDialog('[data-admin-help-open]', '[data-admin-help-dialog]');
wireDialog('[data-compensation-reference-open]', '[data-compensation-reference-dialog]', '[data-compensation-reference-body]');

document.querySelectorAll<HTMLTableCellElement>('table thead th').forEach(header => {
    if (!header.hasAttribute('scope')) header.setAttribute('scope', 'col');
});

document.querySelectorAll<HTMLElement>('.table-scroll').forEach(container => {
    if (!container.hasAttribute('tabindex')) container.tabIndex = 0;
    if (!container.hasAttribute('role')) container.setAttribute('role', 'region');
    if (!container.hasAttribute('aria-label')) container.setAttribute('aria-label', 'Bảng dữ liệu có thể cuộn ngang');
});
