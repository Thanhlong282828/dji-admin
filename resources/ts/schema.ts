document.documentElement.dataset.application = 'schema-admin';

export const schemaAdminReady = true;

type OrganizationNode = {
    id: number;
    code: string;
    name: string;
    type: string;
    headcount_limit: number;
    people_count: number;
    allocated_fte: number;
    vacancy: number;
    cost_center_code?: string | null;
    detail_url: string;
    children: OrganizationNode[];
};

const escapeHtml = (value: unknown): string => {
    const element = document.createElement('span');
    element.textContent = String(value ?? '');
    return element.innerHTML;
};

const renderTreeNode = (node: OrganizationNode, companyId: string): string => {
    const children = node.children ?? [];
    return `<li data-tree-item data-type="${escapeHtml(node.type)}">
        <div class="tree-node">
            ${children.length ? `<button type="button" data-tree-toggle aria-expanded="true">−</button>` : ''}
            <span><b>${escapeHtml(node.code)}</b> ${escapeHtml(node.name)} <small>${escapeHtml(node.type)}</small></span>
            <span>Limit ${node.headcount_limit} · People ${node.people_count} · FTE ${Number(node.allocated_fte).toFixed(2)} · Vacancy ${Number(node.vacancy).toFixed(2)} · ${escapeHtml(node.cost_center_code || 'No cost center')}</span>
            <a href="${escapeHtml(node.detail_url)}?company_id=${encodeURIComponent(companyId)}">Chi tiết</a>
        </div>
        ${children.length ? `<ul data-tree-children>${children.map(child => renderTreeNode(child, companyId)).join('')}</ul>` : ''}
    </li>`;
};

document.querySelectorAll<HTMLElement>('[data-organization-tree]').forEach((root) => {
    const content = root.querySelector<HTMLElement>('[data-tree-content]');
    const state = root.querySelector<HTMLElement>('[data-tree-state]');
    const search = root.querySelector<HTMLInputElement>('[data-tree-search]');
    const type = root.querySelector<HTMLSelectElement>('[data-tree-type]');
    const endpoint = root.dataset.endpoint;
    const companyId = root.dataset.companyId ?? '';

    const filter = (): void => {
        const term = search?.value.trim().toLowerCase() ?? '';
        root.querySelectorAll<HTMLElement>('[data-tree-item]').forEach((item) => {
            const matchesText = !term || (item.querySelector(':scope > .tree-node')?.textContent ?? '').toLowerCase().includes(term);
            const matchesType = !type?.value || item.dataset.type === type.value;
            item.hidden = !(matchesText && matchesType);
        });
    };

    const reload = async (): Promise<void> => {
        if (!endpoint || !content || !state) return;
        state.textContent = 'Đang đồng bộ…';
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json() as { data: OrganizationNode[] };
            content.innerHTML = payload.data.length
                ? `<ul class="organization-tree">${payload.data.map(node => renderTreeNode(node, companyId)).join('')}</ul>`
                : '<div class="empty-state">Chưa có dữ liệu cây tổ chức.</div>';
            state.textContent = 'Đã đồng bộ';
            filter();
        } catch {
            state.textContent = 'Không thể tải cây tổ chức. Vui lòng thử lại.';
        }
    };

    root.addEventListener('click', (event) => {
        const button = (event.target as Element).closest<HTMLElement>('[data-tree-toggle]');
        if (button) {
            const children = button.closest('[data-tree-item]')?.querySelector<HTMLElement>(':scope > [data-tree-children]');
            if (children) {
                children.hidden = !children.hidden;
                button.textContent = children.hidden ? '+' : '−';
                button.setAttribute('aria-expanded', String(!children.hidden));
            }
        }
    });
    search?.addEventListener('input', filter);
    type?.addEventListener('change', filter);
    root.querySelector('[data-tree-expand]')?.addEventListener('click', () => root.querySelectorAll<HTMLElement>('[data-tree-children]').forEach(node => { node.hidden = false; }));
    root.querySelector('[data-tree-collapse]')?.addEventListener('click', () => root.querySelectorAll<HTMLElement>('[data-tree-children]').forEach(node => { node.hidden = true; }));

    const echo = (window as Window & { Echo?: { private(channel: string): { listen(event: string, callback: () => void): unknown } } }).Echo;
    if (echo) {
        const channel = echo.private(`company.${companyId}`);
        ['.organization-unit.created', '.organization-unit.updated', '.organization-unit.moved', '.organization-unit.deleted', '.headcount.updated']
            .forEach(event => channel.listen(event, reload));
    }
});
