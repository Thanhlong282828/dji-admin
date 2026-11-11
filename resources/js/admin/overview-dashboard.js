const focusableSelector = 'a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])';

export function bootOverviewDashboard() {
    document.querySelectorAll('[data-executive-command-center]').forEach((dashboard) => {
        if (!(dashboard instanceof HTMLElement) || dashboard.dataset.executiveBound === 'true') return;
        dashboard.dataset.executiveBound = 'true';

        const dialog = dashboard.querySelector('[data-executive-onboarding]');
        const sheet = dialog?.querySelector('.executive-onboarding__sheet');
        const steps = dialog ? Array.from(dialog.querySelectorAll('[data-executive-onboarding-step]')) : [];
        const currentLabel = dialog?.querySelector('[data-executive-onboarding-current]');
        const progress = dialog?.querySelector('[data-executive-onboarding-progress]');
        const previous = dialog?.querySelector('[data-executive-onboarding-prev]');
        const next = dialog?.querySelector('[data-executive-onboarding-next]');
        let current = 0;
        let trigger = null;

        if (!(dialog instanceof HTMLElement) || !(sheet instanceof HTMLElement) || steps.length === 0) return;

        const render = () => {
            steps.forEach((step, index) => { step.hidden = index !== current; });
            if (currentLabel) currentLabel.textContent = String(current + 1);
            if (progress instanceof HTMLElement) progress.style.setProperty('--onboarding-progress', `${((current + 1) / steps.length) * 100}%`);
            if (previous instanceof HTMLButtonElement) previous.disabled = current === 0;
            if (next instanceof HTMLButtonElement) next.textContent = current === steps.length - 1 ? 'Hoàn tất' : 'Tiếp tục';
        };
        const close = () => {
            dialog.hidden = true;
            document.documentElement.style.removeProperty('overflow');
            if (trigger instanceof HTMLElement) trigger.focus();
        };
        const open = (button) => {
            trigger = button;
            current = 0;
            render();
            dialog.hidden = false;
            document.documentElement.style.overflow = 'hidden';
            requestAnimationFrame(() => sheet.focus());
        };

        dashboard.querySelectorAll('[data-executive-onboarding-open]').forEach((button) => button.addEventListener('click', () => open(button)));
        dialog.querySelectorAll('[data-executive-onboarding-close]').forEach((button) => button.addEventListener('click', close));
        previous?.addEventListener('click', () => { current = Math.max(0, current - 1); render(); });
        next?.addEventListener('click', () => {
            if (current === steps.length - 1) { window.localStorage.setItem('schema.executive-onboarding.completed', 'true'); close(); return; }
            current += 1;
            render();
        });
        dialog.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { close(); return; }
            if (event.key !== 'Tab') return;
            const focusable = Array.from(dialog.querySelectorAll(focusableSelector)).filter((element) => !element.hasAttribute('disabled') && element.getClientRects().length > 0);
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    });

    bootCommandAnalytics();
    bootWorkspaceLauncher();
    bootCommandSections();
}

function bootCommandSections() {
    document.querySelectorAll('[data-command-section]').forEach((section) => {
        const toggle = section.querySelector('[data-command-section-toggle]');
        const body = section.querySelector('[data-command-section-body]');
        if (!(toggle instanceof HTMLButtonElement) || !(body instanceof HTMLElement) || section.dataset.collapseBound === 'true') return;
        section.dataset.collapseBound = 'true';
        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            toggle.firstChild.textContent = expanded ? 'Mở rộng ' : 'Thu gọn ';
            body.hidden = expanded;
        });
    });
}

function bootWorkspaceLauncher() {
    document.querySelectorAll('[data-erp-ecosystem]').forEach((launcher) => {
        const input = launcher.querySelector('[data-erp-launcher-search]');
        const count = launcher.querySelector('[data-erp-launcher-count]');
        const empty = launcher.querySelector('[data-erp-launcher-empty]');
        const items = Array.from(launcher.querySelectorAll('[data-erp-launcher-item]'));
        if (!(input instanceof HTMLInputElement) || launcher.dataset.searchBound === 'true') return;
        launcher.dataset.searchBound = 'true';
        const render = () => {
            const query = input.value.trim().toLocaleLowerCase('vi');
            let visible = 0;
            items.forEach((item) => {
                const match = query === '' || (item.getAttribute('data-search') || '').includes(query);
                item.hidden = !match;
                if (match) visible += 1;
            });
            if (count) count.textContent = `${visible} ứng dụng`;
            if (empty instanceof HTMLElement) empty.hidden = visible !== 0;
        };
        input.addEventListener('input', render);
        render();
    });
}

const chartGold = ['#fff2a8', '#ffd54a', '#e6aa16', '#b97808', '#75500d', '#f8c94b', '#c9972c', '#8f6a20', '#fff0bf', '#d89c00', '#9f7418', '#6d521d'];
const readPayload = (root, name) => { try { return JSON.parse(root.dataset[name] || 'null'); } catch { return null; } };
const escapeHtml = (value) => { const element = document.createElement('span'); element.textContent = String(value ?? ''); return element.innerHTML; };

function setupCanvas(canvas) {
    const ratio = Math.max(1, window.devicePixelRatio || 1), width = Math.max(280, canvas.clientWidth || Number(canvas.getAttribute('width')) || 600), height = Math.max(220, canvas.clientHeight || Number(canvas.getAttribute('height')) || 280);
    canvas.width = Math.round(width * ratio); canvas.height = Math.round(height * ratio);
    const context = canvas.getContext('2d'); context.setTransform(ratio, 0, 0, ratio, 0, 0);
    return { context, width, height };
}

function drawDonut(canvas, payload, legend) {
    const { context, width, height } = setupCanvas(canvas), items = payload?.items || [], total = items.reduce((sum, item) => sum + Number(item.value_minor || 0), 0); if (total <= 0) return;
    const cx = width / 2, cy = height / 2, radius = Math.min(width, height) * .34; let angle = -Math.PI / 2;
    items.forEach((item, index) => { const next = angle + (Number(item.value_minor) / total) * Math.PI * 2; context.beginPath(); context.arc(cx, cy, radius, angle, next); context.arc(cx, cy, radius * .61, next, angle, true); context.closePath(); context.fillStyle = chartGold[index % chartGold.length]; context.fill(); angle = next; });
    if (legend) legend.innerHTML = items.map((item, index) => `<span><i style="--legend:${chartGold[index % chartGold.length]}"></i><b>${escapeHtml(item.label)}</b><em>${Math.round((Number(item.value_minor) / total) * 1000) / 10}%</em></span>`).join('');
}

function drawSeries(canvas, payload, kind) {
    const { context, width, height } = setupCanvas(canvas), series = payload?.series || []; if (!series.length) return;
    const pad = { left: 36, right: 14, top: 20, bottom: 40 }, values = series.map(item => Number(item.value_minor || 0)), max = Math.max(1, ...values), plotW = width - pad.left - pad.right, plotH = height - pad.top - pad.bottom;
    context.strokeStyle = 'rgba(255,215,90,.14)'; context.lineWidth = 1; for (let row = 0; row <= 4; row += 1) { const y = pad.top + plotH * row / 4; context.beginPath(); context.moveTo(pad.left, y); context.lineTo(width - pad.right, y); context.stroke(); }
    context.font = '11px Inter, sans-serif'; context.textAlign = 'center'; context.fillStyle = '#9da3af';
    series.forEach((item, index) => { const x = pad.left + plotW * (index + .5) / series.length; context.fillText(String(item.period || ''), x, height - 15); if (kind === 'bar') { const barW = Math.min(54, plotW / series.length * .55), barH = values[index] / max * plotH, gradient = context.createLinearGradient(0, pad.top + plotH - barH, 0, pad.top + plotH); gradient.addColorStop(0, '#fff0a0'); gradient.addColorStop(.45, '#f4bd27'); gradient.addColorStop(1, '#8a5700'); context.fillStyle = gradient; context.fillRect(x - barW / 2, pad.top + plotH - barH, barW, barH); } });
    if (kind !== 'line') return;
    const points = series.map((item, index) => ({ x: pad.left + plotW * (index + .5) / series.length, y: pad.top + plotH - values[index] / max * plotH }));
    const gradient = context.createLinearGradient(0, pad.top, 0, pad.top + plotH); gradient.addColorStop(0, 'rgba(255,210,63,.38)'); gradient.addColorStop(1, 'rgba(255,210,63,0)'); context.beginPath(); points.forEach((point, index) => index ? context.lineTo(point.x, point.y) : context.moveTo(point.x, point.y)); context.lineTo(points.at(-1).x, pad.top + plotH); context.lineTo(points[0].x, pad.top + plotH); context.closePath(); context.fillStyle = gradient; context.fill(); context.beginPath(); points.forEach((point, index) => index ? context.lineTo(point.x, point.y) : context.moveTo(point.x, point.y)); context.strokeStyle = '#ffd84f'; context.lineWidth = 3; context.stroke();
}

function bindChartTooltip(canvas, payload, kind, tooltip) {
    const items = kind === 'inventory' ? (payload?.items || []) : (payload?.series || []);
    if (!(tooltip instanceof HTMLOutputElement) || items.length === 0 || canvas.dataset.tooltipBound === 'true') return;
    canvas.dataset.tooltipBound = 'true';
    let active = 0;
    const formatValue = (item) => Number(item.value_minor || 0).toLocaleString('vi-VN');
    const show = (index, x) => {
        active = Math.max(0, Math.min(items.length - 1, index));
        const item = items[active];
        tooltip.innerHTML = `<strong>${escapeHtml(item.label || item.period || '')}</strong><span>${formatValue(item)} ${escapeHtml(payload?.currency || '')}</span>`;
        tooltip.style.setProperty('--tooltip-x', `${Math.max(12, Math.min(canvas.clientWidth - 12, x))}px`);
        tooltip.hidden = false;
        canvas.setAttribute('aria-label', `${kind === 'inventory' ? 'Cơ cấu tồn kho' : 'Chuỗi thời gian'}: ${item.label || item.period}, ${formatValue(item)} ${payload?.currency || ''}`);
    };
    const selectFromPointer = (event) => {
        const rect = canvas.getBoundingClientRect();
        const x = Math.max(0, Math.min(rect.width, event.clientX - rect.left));
        const index = Math.min(items.length - 1, Math.floor((x / Math.max(1, rect.width)) * items.length));
        show(index, x);
    };
    canvas.addEventListener('mousemove', selectFromPointer);
    canvas.addEventListener('mouseleave', () => { tooltip.hidden = true; });
    canvas.addEventListener('focus', () => show(active, canvas.clientWidth * ((active + .5) / items.length)));
    canvas.addEventListener('blur', () => { tooltip.hidden = true; });
    canvas.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        const direction = event.key === 'ArrowRight' ? 1 : -1;
        const next = (active + direction + items.length) % items.length;
        show(next, canvas.clientWidth * ((next + .5) / items.length));
    });
}

function bootCommandAnalytics() {
    document.querySelectorAll('[data-command-analytics]').forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.chartBound === 'true') return; root.dataset.chartBound = 'true';
        const inventory = readPayload(root, 'inventory'), procurement = readPayload(root, 'procurement'), revenue = readPayload(root, 'revenue');
        const draw = () => { const donut = root.querySelector('[data-command-chart="inventory"]'), line = root.querySelector('[data-command-chart="procurement"]'), bars = root.querySelector('[data-command-chart="revenue"]'); if (donut instanceof HTMLCanvasElement) { drawDonut(donut, inventory, root.querySelector('[data-command-legend="inventory"]')); bindChartTooltip(donut, inventory, 'inventory', root.querySelector('[data-command-tooltip="inventory"]')); } if (line instanceof HTMLCanvasElement) { drawSeries(line, procurement, 'line'); bindChartTooltip(line, procurement, 'series', root.querySelector('[data-command-tooltip="procurement"]')); } if (bars instanceof HTMLCanvasElement) { drawSeries(bars, revenue, 'bar'); bindChartTooltip(bars, revenue, 'series', root.querySelector('[data-command-tooltip="revenue"]')); } };
        draw(); if ('ResizeObserver' in window) new ResizeObserver(draw).observe(root);
    });
}
