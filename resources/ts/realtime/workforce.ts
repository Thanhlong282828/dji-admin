import { schemaEcho } from './echo';

type WorkforceChange = { company_id: number; entity_id?: number; run_id?: number; journal_id?: number; adjustment_id?: number; version?: number; calculation_version?: number; refresh_scope?: string; refresh_scopes?: string[] };
const accountingMatch = window.location.pathname.match(/^\/admin\/payroll\/accounting\/journals(?:\/(\d+))?$/);
if (accountingMatch) {
    const main = document.querySelector<HTMLElement>('main');
    const company = new URLSearchParams(window.location.search).get('company_id');
    if (main && company) {
        main.dataset.workforcePage = 'accounting';
        main.dataset.companyId = company;
        main.dataset.entityId = accountingMatch[1] ?? '0';
        main.dataset.refreshScope = accountingMatch[1] ? 'accounting-journal-detail' : 'accounting-journal-list';
        main.dataset.refreshUrl = window.location.href;
    }
}
const adjustmentMatch = window.location.pathname.match(/^\/admin\/payroll\/adjustments(?:\/(\d+))?$/);
if (adjustmentMatch) {
    const main = document.querySelector<HTMLElement>('main');
    const company = new URLSearchParams(window.location.search).get('company_id');
    if (main && company) {
        main.dataset.workforcePage = 'adjustment'; main.dataset.companyId = company;
        main.dataset.entityId = adjustmentMatch[1] ?? '0'; main.dataset.refreshScope = adjustmentMatch[1] ? 'detail' : 'list'; main.dataset.refreshUrl = window.location.href;
    }
}
const roots = new Set(Array.from(document.querySelectorAll<HTMLElement>('[data-workforce-page], [data-workforce-region]')));
const pending = new WeakMap<HTMLElement, number>();
const requests = new WeakMap<HTMLElement, AbortController>();

const dependentScopes: Record<string, string[]> = {
    'leave-request-list': ['leave-request-list', 'leave-balance-summary', 'leave-ledger'],
};

const refresh = async (root: HTMLElement, change: WorkforceChange): Promise<void> => {
    const url = root.dataset.refreshUrl;
    if (!url) return;
    requests.get(root)?.abort();
    const controller = new AbortController();
    requests.set(root, controller);
    try {
        const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) return;
        const documentCopy = new DOMParser().parseFromString(await response.text(), 'text/html');
        const scope = root.dataset.refreshScope;
        const selector = scope ? `[data-refresh-scope="${CSS.escape(scope)}"]` : '[data-workforce-page]';
        const replacement = documentCopy.querySelector<HTMLElement>(selector) ?? (['accounting','adjustment'].includes(root.dataset.workforcePage ?? '') ? documentCopy.querySelector<HTMLElement>('main') : null);
        if (replacement) {
            if (['accounting','adjustment'].includes(root.dataset.workforcePage ?? '')) {
                replacement.dataset.workforcePage = root.dataset.workforcePage ?? '';
                replacement.dataset.companyId = root.dataset.companyId ?? '';
                replacement.dataset.entityId = root.dataset.entityId ?? '0';
                replacement.dataset.refreshScope = root.dataset.refreshScope ?? '';
                replacement.dataset.refreshUrl = root.dataset.refreshUrl ?? '';
            }
            const responseVersion = Number(replacement.dataset.recordVersion ?? 0);
            const responseCalculationVersion = Number(replacement.dataset.calculationVersion ?? 0);
            if (responseVersion < Number(change.version ?? 0) || responseCalculationVersion < Number(change.calculation_version ?? 0)) return;
            roots.delete(root);
            root.replaceWith(replacement);
            roots.add(replacement);
        }
    } catch { /* Forms and manual refresh remain the safe fallback. */ }
    finally { if (requests.get(root) === controller) requests.delete(root); }
};

const schedule = (change: WorkforceChange): void => roots.forEach(root => {
    const scopes = change.refresh_scopes ?? (change.refresh_scope ? dependentScopes[change.refresh_scope] ?? [change.refresh_scope] : []);
    const entityId = Number(root.dataset.entityId ?? 0);
    const changedEntityId = Number(change.adjustment_id ?? change.journal_id ?? change.run_id ?? change.entity_id ?? 0);
    if (Number(root.dataset.companyId) !== change.company_id || (changedEntityId > 0 && entityId > 0 && entityId !== changedEntityId) || !root.dataset.refreshScope || !scopes.includes(root.dataset.refreshScope)) return;
    if (Number(root.dataset.recordVersion ?? 0) > Number(change.version ?? Number.MAX_SAFE_INTEGER)) return;
    if (Number(root.dataset.calculationVersion ?? 0) > Number(change.calculation_version ?? Number.MAX_SAFE_INTEGER)) return;
    const old = pending.get(root); if (old) window.clearTimeout(old);
    pending.set(root, window.setTimeout(() => void refresh(root, change), 250));
});

if (schemaEcho) {
    const companies = new Set(Array.from(roots).map(root => root.dataset.companyId).filter((id): id is string => Boolean(id)));
    companies.forEach(company => {
        const channel = schemaEcho.private(`company.${company}`);
        ['workforce.attendance.changed','workforce.leave.changed','workforce.overtime.changed','workforce.payroll-input.changed','payroll.run.changed','payroll.configuration.changed','payroll.accounting-journal.changed','payroll.adjustment.changed'].forEach(event => channel.listen(`.${event}`, (change: WorkforceChange) => schedule(change)));
    });
}
