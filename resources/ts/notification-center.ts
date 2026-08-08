const badge = document.querySelector<HTMLElement>('[data-notification-unread]');
if (badge) {
    const companyId = new URLSearchParams(window.location.search).get('company_id');
    if (companyId) {
        const refresh = async (): Promise<void> => {
            const response = await fetch(`/admin/notifications/unread-count?company_id=${encodeURIComponent(companyId)}`, { headers: { Accept: 'application/json' } });
            if (response.ok) {
                const data = await response.json() as { unread?: number };
                badge.textContent = String(data.unread ?? 0);
            }
        };
        void refresh();
        document.addEventListener('schema:notification', () => void refresh());
    }
}
