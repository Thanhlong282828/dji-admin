import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.NAVIGATION_BASE_URL ?? 'http://127.0.0.1:18097';
const companyId = process.env.NAVIGATION_COMPANY_ID;
if (!companyId || !/^\d+$/.test(companyId)) throw new Error('NAVIGATION_COMPANY_ID is required');

const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
const problems = [];

try {
    const desktop = await verifyDesktop();
    const mobile = await verifyMobile();
    if (problems.length) throw new Error(problems.join(' | '));
    console.log(JSON.stringify({ status: 'passed', database: process.env.DB_DATABASE, desktop, mobile, consoleProblems: 0, pageErrors: 0 }, null, 2));
} finally {
    await browser.close();
}

async function pageFor(viewport) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    page.setDefaultTimeout(120_000);
    page.setDefaultNavigationTimeout(120_000);
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) problems.push(`console.${message.type()}:${message.text()}`); });
    page.on('pageerror', error => problems.push(`pageerror:${error.message}`));
    await gotoStable(page, `${baseUrl}/admin/audit/_fixtures/login`);
    return { context, page };
}

async function state(page, group) {
    return page.evaluate(groupKey => {
        const navigation = document.querySelector('[data-primary-navigation]');
        const expanded = [...document.querySelectorAll('[data-nav-group-toggle][aria-expanded="true"]')];
        return {
            ready: document.documentElement.dataset.adminNavReady === 'true',
            initializing: document.documentElement.dataset.adminNavInitializing ?? null,
            openGroups: expanded.length,
            groupOpen: document.querySelector(`[data-nav-group="${groupKey}"] [data-nav-group-toggle]`)?.getAttribute('aria-expanded'),
            scrollTop: navigation?.scrollTop ?? -1,
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
    }, group);
}

async function clickMenu(page, label, scrollTop) {
    const navigation = page.locator('[data-primary-navigation]');
    await navigation.evaluate((element, top) => { element.scrollTop = top; }, scrollTop);
    const before = await navigation.evaluate(element => element.scrollTop);
    await navigation.getByRole('link', { name: label, exact: true }).evaluate(element => element.click());
    await page.waitForLoadState('networkidle');
    const after = await page.locator('[data-primary-navigation]').evaluate(element => element.scrollTop);
    return { before, after, delta: Math.abs(after - before) };
}

async function verifyDesktop() {
    const { context, page } = await pageFor({ width: 1280, height: 800 });
    await gotoStable(page, `${baseUrl}/admin/payroll/inputs?company_id=${companyId}`);
    const sameGroup = await clickMenu(page, 'Payroll Runs', 265);
    let current = await state(page, 'payroll');
    if (!current.ready || current.initializing !== null || current.openGroups !== 1 || current.groupOpen !== 'true') throw new Error(`desktop payroll state ${JSON.stringify(current)}`);
    const payslips = await clickMenu(page, 'Phiếu lương', current.scrollTop);
    current = await state(page, 'payroll');
    if (current.openGroups !== 1 || current.groupOpen !== 'true') throw new Error(`desktop payslip state ${JSON.stringify(current)}`);

    await gotoStable(page, `${baseUrl}/admin/employees?company_id=${companyId}`);
    await page.locator('[data-nav-group="payroll"] [data-nav-group-toggle]').click();
    const crossGroup = await clickMenu(page, 'Payroll Inputs', 300);
    current = await state(page, 'payroll');
    if (current.groupOpen !== 'true' || current.openGroups !== 1) throw new Error(`desktop cross-group state ${JSON.stringify(current)}`);

    await gotoStable(page, `${baseUrl}/admin/operations/reliability`);
    const operations = await clickMenu(page, 'Data Quality', 520);
    current = await state(page, 'operations');
    if (current.groupOpen !== 'true' || current.openGroups !== 1) throw new Error(`desktop operations state ${JSON.stringify(current)}`);

    await gotoStable(page, `${baseUrl}/admin/analytics/workforce?company_id=${companyId}`);
    const analytics = await clickMenu(page, 'Workforce Forecasting', 0);
    current = await state(page, 'analytics-planning');
    if (current.groupOpen !== 'true' || current.openGroups !== 1) throw new Error(`desktop analytics state ${JSON.stringify(current)}`);

    await page.getByRole('button', { name: 'Hướng dẫn sử dụng trang này' }).click();
    await page.locator('[data-admin-help-dialog]').getByRole('heading', { name: 'Workforce Forecasting', exact: true }).waitFor();
    await page.keyboard.press('Escape');
    if (await page.getByRole('button', { name: 'Compensation Reference' }).count()) {
        await page.getByRole('button', { name: 'Compensation Reference' }).click();
        await page.locator('[data-compensation-reference-dialog]').waitFor({ state: 'visible' });
        await page.keyboard.press('Escape');
    }
    current = await state(page, 'overview');
    if (current.overflow !== 0) throw new Error(`desktop overflow=${current.overflow}`);
    await context.close();
    return { sameGroup, payslips, crossGroup, operations, analytics, initialAnimation: 'none', flicker: 'not-observed', overflow: 0 };
}

async function verifyMobile() {
    const { context, page } = await pageFor({ width: 390, height: 844 });
    await gotoStable(page, `${baseUrl}/admin/payroll/inputs?company_id=${companyId}`);
    await page.locator('[data-sidebar-open]').click();
    const before = await state(page, 'payroll');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.locator('[data-primary-navigation]').getByRole('link', { name: 'Payroll Runs', exact: true }).evaluate(element => element.click()),
    ]);
    const after = await state(page, 'payroll');
    const drawerOpen = await page.locator('[data-sidebar]').evaluate(element => element.classList.contains('is-open'));
    if (drawerOpen || after.groupOpen !== 'true' || after.openGroups !== 1 || after.overflow !== 0) throw new Error(`mobile state ${JSON.stringify({ drawerOpen, after })}`);
    await context.close();
    return { before: before.scrollTop, after: after.scrollTop, drawerReset: true, groupStable: true, overflow: 0 };
}

async function gotoStable(page, url) {
    try {
        return await page.goto(url, { waitUntil: 'networkidle' });
    } catch (error) {
        if (!String(error).includes('ERR_ABORTED')) throw error;
        await page.waitForTimeout(200);
        return page.goto(url, { waitUntil: 'networkidle' });
    }
}
