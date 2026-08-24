import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}
const baseUrl = process.env.FORECAST_BASE_URL ?? 'http://127.0.0.1:18096';
const companyId = process.env.FORECAST_COMPANY_ID;
if (!companyId) throw new Error('FORECAST_COMPANY_ID is required');
const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
const problems = [];
try {
    const desktop = await verify({ width: 1280, height: 800 }, true);
    const mobile = await verify({ width: 390, height: 844 }, false);
    if (problems.length) throw new Error(problems.join(' | '));
    console.log(JSON.stringify({ status: 'passed', database: process.env.DB_DATABASE, desktop, mobile, consoleProblems: problems.length }, null, 2));
} finally { await browser.close(); }

async function verify(viewport, interact) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) problems.push(`${message.type()}:${message.text()}`); });
    page.on('pageerror', error => problems.push(`pageerror:${error.message}`));
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'domcontentloaded' });
    const response = await page.goto(`${baseUrl}/admin/analytics/forecasting?company_id=${companyId}`, { waitUntil: 'networkidle' });
    if (!response || response.status() !== 200) throw new Error(`forecast status=${response?.status()}`);
    for (const text of ['Planning Summary', 'Headcount Forecast by Position', 'Scenario Builder', 'Assumptions', 'Data Quality Warnings']) await page.getByText(text, { exact: true }).waitFor();
    if (interact) {
        await page.locator('select[name="scenario[0][position_id]"]').selectOption({ index: 1 });
        await page.locator('input[name="scenario[0][quantity]"]').fill('3');
        await page.getByRole('button', { name: 'Compare Scenario' }).click();
        await page.waitForLoadState('domcontentloaded');
        await page.getByRole('heading', { name: 'Scenario A', exact: true }).waitFor();
        await page.getByRole('button', { name: 'Hướng dẫn sử dụng trang này' }).click();
        const dialog = page.locator('[data-admin-help-dialog]');
        await dialog.getByText('mô hình deterministic', { exact: false }).waitFor();
        await dialog.getByText('không Save', { exact: false }).waitFor();
        await page.keyboard.press('Escape');
    }
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow !== 0) throw new Error(`overflow=${overflow}`);
    await context.close();
    return { viewport, status: 200, overflow, scenario: interact ? 'compared' : 'rendered' };
}
