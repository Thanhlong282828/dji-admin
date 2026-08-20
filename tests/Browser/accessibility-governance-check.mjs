import { access, mkdir } from 'node:fs/promises';
import process from 'node:process';
import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.ACCESSIBILITY_BROWSER_BASE_URL ?? 'http://127.0.0.1:18087';
const chromePath = process.env.ACCESSIBILITY_CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const evidenceDirectory = process.env.ACCESSIBILITY_EVIDENCE_DIR ?? 'storage/logs/phase3f17-browser-evidence';
const companyId = process.env.ACCESSIBILITY_COMPANY_ID;
if (!companyId || !/^\d+$/.test(companyId)) throw new Error('ACCESSIBILITY_COMPANY_ID must be a fixture company identifier');
await access(chromePath);
await mkdir(evidenceDirectory, { recursive: true });
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
try {
    const evidence = [];
    evidence.push(await verify('desktop', 1280, 800));
    evidence.push(await verify('mobile', 390, 844));
    console.log(JSON.stringify({ status: 'passed', formalWcagCertification: false, evidence }, null, 2));
} finally {
    await browser.close();
}

async function verify(label, width, height) {
    const context = await browser.newContext({ viewport: { width, height } });
    const page = await context.newPage();
    const problems = [];
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) problems.push(`console.${message.type()}: ${message.text()}`); });
    page.on('pageerror', error => problems.push(`pageerror: ${error.message}`));
    page.on('response', response => { if (response.status() >= 400) problems.push(`http.${response.status()}: ${response.url()}`); });
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });
    const primaryNavigationLinks = await page.locator('.primary-navigation .nav-item').evaluateAll(links => links.map(link => ({ url: link.href, label: link.querySelector(':scope > span')?.textContent?.trim() ?? '' })));
    const primaryNavigationResults = [];
    for (const link of primaryNavigationLinks) {
        const navigationUrl = new URL(link.url);
        if (navigationUrl.searchParams.has('company_id')) navigationUrl.searchParams.set('company_id', companyId);
        const url = navigationUrl.toString();
        const response = await gotoWithRetry(page, url);
        const layout = await page.evaluate(() => ({ clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth }));
        if (response?.status() !== 200 || layout.scrollWidth !== layout.clientWidth) throw new Error(`${label}/primary-navigation: ${JSON.stringify({ url, status: response?.status(), ...layout })}`);
        const helpTrigger = page.locator('[data-admin-help-open]');
        let helpEvidence = 'specific';
        if (await helpTrigger.count()) {
            await helpTrigger.click();
            const helpDialog = page.locator('[data-admin-help-dialog]');
            const helpTitle = (await helpDialog.locator('#admin-help-title').textContent())?.trim();
            if (helpTitle !== link.label || await helpDialog.getByText('Trang chi tiết chưa có guide riêng', { exact: false }).count()) throw new Error(`${label}/help-context: ${link.label} resolved to ${helpTitle}`);
            const menuFlow = helpDialog.locator('.admin-menu-flow');
            if (await menuFlow.count() !== 1 || await menuFlow.locator('[aria-current="page"]').count() !== 1) throw new Error(`${label}/help-menu-flow: ${link.label} has no unique current node`);
            await page.keyboard.press('Escape');
        } else if (!new URL(url).pathname.endsWith('/api/documentation')) {
            throw new Error(`${label}/help-context: ${link.label} has no contextual help trigger at ${page.url()} title=${await page.title()}`);
        } else {
            helpEvidence = 'protected-standalone-swagger';
        }
        primaryNavigationResults.push({ url: new URL(url).pathname, label: link.label, status: response.status(), help: helpEvidence, overflow: 0 });
    }
    const tenant = `?company_id=${companyId}`;
    const pages = ['/dashboard', `/admin/employees${tenant}`, `/admin/payroll/runs${tenant}`, `/admin/workflows${tenant}`, `/admin/iam${tenant}`, `/admin/integrations${tenant}`, `/admin/audit${tenant}`, '/admin/operations/reliability', '/admin/operations/observability', '/admin/operations/performance', '/admin/operations/architecture', '/admin/operations/test-quality', '/admin/operations/api-contracts', '/admin/operations/data-quality', '/admin/operations/releases', '/admin/operations/configuration', '/admin/operations/secrets', '/admin/operations/data-protection', '/admin/operations/continuity', '/admin/operations/security-assurance'];
    for (const path of pages) {
        const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle' });
        if (!response?.ok()) throw new Error(`${label}/${path}: HTTP ${response?.status()}`);
        const metrics = await page.evaluate(() => ({ innerWidth, innerHeight, clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth, duplicateIds: [...document.querySelectorAll('[id]')].map(node => node.id).filter((id, index, ids) => ids.indexOf(id) !== index), unlabeledButtons: [...document.querySelectorAll('button')].filter(button => !(button.textContent?.trim() || button.getAttribute('aria-label') || button.getAttribute('title'))).length }));
        if (metrics.innerWidth !== width || metrics.innerHeight !== height || metrics.clientWidth !== width || metrics.scrollWidth !== width || metrics.duplicateIds.length || metrics.unlabeledButtons) throw new Error(`${label}/${path}: ${JSON.stringify(metrics)}`);
    }

    const dashboardResponse = await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });
    if (!dashboardResponse?.ok()) throw new Error(`${label}: dashboard keyboard-check HTTP ${dashboardResponse?.status()} at ${page.url()}`);
    await page.evaluate(() => localStorage.removeItem('schema.admin.navigation.open-groups'));
    await page.reload({ waitUntil: 'networkidle' });
    if (page.url() !== `${baseUrl}/dashboard`) throw new Error(`${label}: dashboard keyboard-check redirected to ${page.url()}`);
    if (await page.locator('.skip-link').count() !== 1) throw new Error(`${label}: dashboard has no admin skip link; title=${await page.title()} body=${(await page.locator('body').innerText()).slice(0, 160)}`);
    await page.keyboard.press('Tab');
    if (!(await page.locator('.skip-link').evaluate(element => document.activeElement === element))) throw new Error(`${label}: skip link is not first keyboard focus target`);
    await page.waitForTimeout(300);
    const focusEvidence = await page.locator('.skip-link').evaluate(element => ({ top: element.getBoundingClientRect().top, outline: getComputedStyle(element).outlineStyle, width: getComputedStyle(element).outlineWidth }));
    if (focusEvidence.top < 0 || focusEvidence.outline === 'none' || focusEvidence.width === '0px') throw new Error(`${label}: skip link/focus indicator unavailable ${JSON.stringify(focusEvidence)}`);

    let initialNavigationEvidence = null;
    if (label === 'desktop') {
        const expandedBefore = await page.locator('[data-nav-group-toggle][aria-expanded="true"]').count();
        const totalLinks = await page.locator('.primary-navigation .nav-item').count();
        const initialVisibleLinks = await page.locator('.primary-navigation .nav-item:visible').count();
        initialNavigationEvidence = { totalLinks, visibleLinks: initialVisibleLinks, expandedGroups: expandedBefore };
        if (expandedBefore !== 1 || initialVisibleLinks !== 1) throw new Error(`desktop: initial expanded=${expandedBefore}, visible links=${initialVisibleLinks}`);
        const operations = page.locator('[data-nav-group="operations"] [data-nav-group-toggle]');
        const operationsItems = page.locator('#nav-group-operations');
        if (await operationsItems.isVisible() || await operationsItems.locator('.nav-item:visible').count() !== 0) throw new Error('desktop: closed Operations children are visible');
        await operations.focus();
        await page.keyboard.press('Enter');
        const visibleOperationsAfterEnter = await operationsItems.locator('.nav-item:visible').count();
        if (await operations.getAttribute('aria-expanded') !== 'true' || !(await operationsItems.isVisible()) || visibleOperationsAfterEnter !== 15) throw new Error(`desktop: Enter exposed ${visibleOperationsAfterEnter} Operations children instead of 15`);
        if (await page.locator('[data-nav-group-toggle][aria-expanded="true"]').count() !== 1) throw new Error('desktop: accordion left multiple groups expanded');
        await operations.focus();
        await page.keyboard.press('Enter');
        if (await operationsItems.isVisible() || await operationsItems.locator('.nav-item:visible').count() !== 0) throw new Error('desktop: second Enter did not collapse Operations');
        await operations.focus();
        await page.keyboard.press('Tab');
        if (await operationsItems.evaluate(element => element.contains(document.activeElement))) throw new Error('desktop: collapsed child remained tabbable');
        await operations.focus();
        await page.keyboard.press('Space');
        if (!(await operationsItems.isVisible()) || await operationsItems.locator('.nav-item:visible').count() !== 15) throw new Error('desktop: Space did not expand Operations');
        const search = page.locator('[data-nav-search]');
        await search.fill('security');
        if (!(await page.getByRole('link', { name: 'Security Assurance' }).isVisible())) throw new Error('desktop: search did not expose permitted Security Assurance item');
        await search.fill('');
        if (!(await operationsItems.isVisible()) || await operationsItems.locator('.nav-item:visible').count() !== 15 || await page.locator('[data-nav-group-toggle][aria-expanded="true"]').count() !== 1) throw new Error('desktop: clearing search did not restore accordion state');
        await page.getByRole('link', { name: 'Security Assurance' }).click();
        await page.waitForLoadState('networkidle');
        await page.reload({ waitUntil: 'networkidle' });
        if (await page.locator('[data-nav-group="operations"] [data-nav-group-toggle]').getAttribute('aria-expanded') !== 'true') throw new Error('desktop: active Operations group did not reopen');
        if ((await page.locator('[data-nav-group="operations"] .nav-item.active').textContent())?.trim() !== 'Security Assurance') throw new Error('desktop: active security item missing');
    } else {
        initialNavigationEvidence = { totalLinks: await page.locator('.primary-navigation .nav-item').count(), visibleLinks: await page.locator('.primary-navigation .nav-item:visible').count(), expandedGroups: await page.locator('[data-nav-group-toggle][aria-expanded="true"]').count() };
        if (initialNavigationEvidence.visibleLinks !== 0) throw new Error(`mobile: closed drawer exposed ${initialNavigationEvidence.visibleLinks} links`);
        const menu = page.locator('[data-sidebar-open]');
        await menu.click();
        if (await menu.getAttribute('aria-expanded') !== 'true' || !(await page.locator('[data-sidebar]').evaluate(element => element.classList.contains('is-open')))) throw new Error('mobile: drawer did not open');
        await page.keyboard.press('Escape');
        if (await menu.getAttribute('aria-expanded') !== 'false' || !(await menu.evaluate(element => document.activeElement === element))) throw new Error('mobile: Escape did not close/restore focus');
        await menu.click();
        const operations = page.locator('[data-nav-group="operations"] [data-nav-group-toggle]');
        const operationsItems = page.locator('#nav-group-operations');
        if (await operationsItems.isVisible()) throw new Error('mobile: Operations children visible before tap');
        await operations.click();
        if (!(await operationsItems.isVisible()) || await operationsItems.locator('.nav-item:visible').count() !== 15) throw new Error('mobile: Operations did not show 15 children');
        await operations.click();
        if (await operationsItems.isVisible() || await operationsItems.locator('.nav-item:visible').count() !== 0) throw new Error('mobile: second tap did not collapse Operations');
        await operations.click();
        await page.getByRole('link', { name: 'Security Assurance' }).click();
        await page.waitForLoadState('networkidle');
        if (await page.locator('[data-sidebar]').evaluate(element => element.classList.contains('is-open'))) throw new Error('mobile: drawer remained open after navigation');
    }

    await page.goto(`${baseUrl}/dashboard`, { waitUntil: 'networkidle' });
    if (label === 'mobile') await page.locator('[data-sidebar-open]').click();
    await page.locator('[data-nav-group="operations"] [data-nav-group-toggle]').click();
    const documentationLink = page.getByRole('link', { name: 'API Documentation' });
    if (!(await documentationLink.isVisible())) throw new Error(`${label}: API Documentation link is not visible for the authorized fixture user`);
    await documentationLink.click();
    await page.waitForLoadState('networkidle');
    if (!page.url().endsWith('/api/documentation') || !(await page.locator('#swagger-ui').isVisible())) throw new Error(`${label}: Swagger UI navigation failed at ${page.url()}`);
    const specResponse = await context.request.get(`${baseUrl}/docs`);
    if (specResponse.status() !== 200 || !(specResponse.headers()['content-type'] ?? '').includes('application/json')) throw new Error(`${label}: protected OpenAPI specification failed (${specResponse.status()})`);
    await page.goBack({ waitUntil: 'networkidle' });
    if (!page.url().endsWith('/dashboard')) throw new Error(`${label}: admin back navigation failed`);
    if (problems.length) throw new Error(`${label}: ${problems.join(' | ')}`);
    const navigationEvidence = { totalLinks: await page.locator('.primary-navigation .nav-item').count(), visibleLinks: await page.locator('.primary-navigation .nav-item:visible').count(), expandedGroups: await page.locator('[data-nav-group-toggle][aria-expanded="true"]').count() };
    await page.screenshot({ path: `${evidenceDirectory}/${label}.png`, fullPage: true });
    await context.close();
    return { label, width, height, pages: pages.length, primaryNavigation: primaryNavigationResults, overflow: 0, consoleProblems: 0, duplicateIds: 0, keyboard: 'passed', focus: 'passed', sidebar: 'passed', initialNavigationEvidence, navigationEvidence };
}

async function gotoWithRetry(page, url) {
    try {
        return await page.goto(url, { waitUntil: 'networkidle' });
    } catch (error) {
        if (!String(error).includes('ERR_ABORTED')) throw error;
        await page.waitForTimeout(250);
        return page.goto(url, { waitUntil: 'networkidle' });
    }
}
