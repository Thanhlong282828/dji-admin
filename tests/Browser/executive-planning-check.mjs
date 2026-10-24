import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}
const baseUrl = process.env.PLANNING_BASE_URL ?? 'http://127.0.0.1:18097';
const companyId = process.env.PLANNING_COMPANY_ID;
if (!companyId) throw new Error('PLANNING_COMPANY_ID is required');
const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
const problems = [];
try {
    const desktop = await verify({ width: 1280, height: 800 }, true);
    const mobile = await verify({ width: 390, height: 844 }, false);
    if (problems.length) throw new Error(problems.join(' | '));
    console.log(JSON.stringify({ status: 'passed', database: process.env.DB_DATABASE, desktop, mobile, consoleProblems: 0, pageErrors: 0 }, null, 2));
} finally { await browser.close(); }

async function verify(viewport, navigate) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) problems.push(`${message.type()}:${message.text()}`); });
    page.on('pageerror', error => problems.push(`pageerror:${error.message}`));
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'domcontentloaded' });
    const response = await page.goto(`${baseUrl}/admin/planning?company_id=${companyId}&period=2026-08`, { waitUntil: 'networkidle' });
    if (response && response.status() !== 200) throw new Error(`planning status=${response.status()}`);
    const cockpit = page.locator('.executive-planning');
    for (const text of ['Executive Planning', 'Workforce plan', 'Financial plan', 'Budget vs Forecast Payroll', 'Recruitment Coverage', 'Assumptions & governance']) await cockpit.getByText(text, { exact: true }).first().waitFor();
    await page.getByRole('button', { name: 'Hướng dẫn sử dụng trang này' }).click();
    const help = page.locator('[data-admin-help-dialog]');
    await help.getByText('Management cockpit đối chiếu Budget', { exact: false }).waitFor();
    await help.getByText('cockpit chỉ tính read-only', { exact: false }).waitFor();
    await page.keyboard.press('Escape');
    if (navigate) {
        const overview = page.locator('[data-nav-group="overview"]');
        if (await overview.getAttribute('data-route-active') !== 'true') throw new Error('overview group not active');
        await overview.getByRole('link', { name: 'Workforce Forecasting', exact: true }).click();
        await page.waitForLoadState('domcontentloaded');
        await page.locator('[data-nav-group="overview"]').getByRole('link', { name: 'Executive Planning', exact: true }).click();
        await page.waitForLoadState('networkidle');
        if (await page.locator('[data-nav-group="overview"]').getAttribute('data-route-active') !== 'true') throw new Error('planning overview group not restored');
        const exportResponse = await page.request.get(`${baseUrl}/admin/planning/export?company_id=${companyId}&period=2026-08`);
        if (exportResponse.status() !== 200 || !exportResponse.headers()['content-type']?.includes('text/csv')) throw new Error(`planning export failed status=${exportResponse.status()} type=${exportResponse.headers()['content-type']}`);
    }
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow !== 0) throw new Error(`overflow=${overflow}`);
    await context.close();
    return { viewport, status: 200, overflow, help: 'specific', sidebar: navigate ? 'forecast-planning stable' : 'rendered', export: navigate ? 'pass' : 'not-run' };
}
