import { schemaEcho } from './echo';

type RecruitmentChange = { company_id: number; entity: string; entity_id: number; action: string };

const roots: HTMLElement[] = [];

const bindRoot = (root: HTMLElement): void => {
    if (root.dataset.realtimeBound === 'true') return;
    root.dataset.realtimeBound = 'true';
    root.querySelector('[data-realtime-refresh]')?.addEventListener('click', () => void refreshRoot(root));
};

const refreshRoot = async (root: HTMLElement): Promise<void> => {
    const state = root.querySelector<HTMLElement>('[data-realtime-state]');
    if (state) state.textContent = 'Đang đồng bộ…';
    try {
        const response = await fetch(root.dataset.refreshUrl ?? window.location.href, {
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const copy = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = copy.querySelector<HTMLElement>('[data-recruitment-page]');
        if (!replacement) throw new Error('Missing realtime fragment');
        root.replaceWith(replacement);
        const rootIndex = roots.indexOf(root);
        if (rootIndex >= 0) roots[rootIndex] = replacement;
        bindRoot(replacement);
        const nextState = replacement.querySelector<HTMLElement>('[data-realtime-state]');
        if (nextState) nextState.textContent = 'Đã đồng bộ';
    } catch {
        if (state) state.textContent = 'Mất kết nối — dùng nút làm mới';
    }
};

const relevant = (root: HTMLElement, change: RecruitmentChange): boolean => {
    if (Number(root.dataset.companyId) !== change.company_id) return false;
    const entityId = Number(root.dataset.realtimeEntityId ?? 0);
    const rootEntity = root.dataset.realtimeEntity;
    const entityMatches = rootEntity === 'all'
        || rootEntity === change.entity
        || rootEntity === 'offer' && change.entity === 'candidate_offer'
        || rootEntity === 'interview' && change.entity === 'interview_evaluation';
    return entityMatches && (entityId === 0 || rootEntity === change.entity && entityId === change.entity_id || change.entity === 'interview_evaluation');
};

roots.push(...document.querySelectorAll<HTMLElement>('[data-recruitment-page]'));
roots.forEach(bindRoot);
if (schemaEcho) {
    const companies = new Set(roots.map(root => root.dataset.companyId).filter((value): value is string => Boolean(value)));
    companies.forEach(companyId => {
        schemaEcho.private(`company.${companyId}`).listen('.recruitment.workflow.changed', (change: RecruitmentChange) => {
            roots.filter(root => relevant(root, change)).forEach(root => void refreshRoot(root));
        });
    });

    const userId = document.querySelector<HTMLMetaElement>('meta[name="schema-user-id"]')?.content;
    if (userId) {
        schemaEcho.private(`App.Models.User.${userId}`).notification((notification: unknown) => {
            document.dispatchEvent(new CustomEvent('schema:notification', { detail: notification }));
            const badge = document.querySelector<HTMLElement>('[data-notification-unread]');
            if (badge) badge.textContent = String(Number(badge.textContent ?? 0) + 1);
        });
    }
}
