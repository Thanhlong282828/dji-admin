export function bootCommandHeader() {
    const menu = document.querySelector('[data-account-menu]');
    if (menu instanceof HTMLElement && menu.dataset.bound !== 'true') {
        menu.dataset.bound = 'true';
        const trigger = menu.querySelector('[data-account-menu-toggle]');
        const panel = menu.querySelector('[data-account-menu-panel]');
        const close = (restore = false) => {
            if (!(trigger instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) return;
            panel.hidden = true; trigger.setAttribute('aria-expanded', 'false');
            if (restore) trigger.focus({ preventScroll: true });
        };
        const open = () => {
            if (!(trigger instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) return;
            panel.hidden = false; trigger.setAttribute('aria-expanded', 'true');
            panel.querySelector('[role="menuitem"]')?.focus({ preventScroll: true });
        };
        trigger?.addEventListener('click', () => panel instanceof HTMLElement && panel.hidden ? open() : close());
        document.addEventListener('pointerdown', (event) => { if (!menu.contains(event.target)) close(); });
        menu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { event.preventDefault(); close(true); }
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
            const items = Array.from(menu.querySelectorAll('[role="menuitem"]')).filter((item) => item instanceof HTMLElement);
            const current = items.indexOf(document.activeElement);
            const next = event.key === 'ArrowDown' ? (current + 1) % items.length : (current - 1 + items.length) % items.length;
            event.preventDefault(); items[next]?.focus();
        });
    }

    document.querySelectorAll('[data-workspace-search-root]').forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.bound === 'true') return;
        root.dataset.bound = 'true';
        const input = root.querySelector('[data-workspace-search]');
        const results = root.querySelector('[data-workspace-results]');
        const empty = root.querySelector('[data-workspace-empty]');
        if (!(input instanceof HTMLInputElement) || !(results instanceof HTMLElement)) return;
        const close = () => { results.hidden = true; input.setAttribute('aria-expanded', 'false'); };
        const render = () => {
            const query = input.value.trim().toLocaleLowerCase('vi'); let visible = 0;
            root.querySelectorAll('[data-workspace-option]').forEach((option) => {
                if (!(option instanceof HTMLElement)) return;
                const match = query !== '' && (option.dataset.searchText ?? '').includes(query);
                option.hidden = !match; if (match) visible += 1;
            });
            if (empty instanceof HTMLElement) empty.hidden = visible > 0 || query === '';
            results.hidden = query === ''; input.setAttribute('aria-expanded', String(query !== ''));
        };
        input.addEventListener('input', render);
        input.addEventListener('focus', render);
        input.addEventListener('keydown', (event) => { if (event.key === 'Escape') { input.value = ''; close(); } });
        document.addEventListener('pointerdown', (event) => { if (!root.contains(event.target)) close(); });
    });
}
