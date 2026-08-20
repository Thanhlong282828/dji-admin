import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}
const baseUrl = process.env.ANALYTICS_BASE_URL ?? 'http://127.0.0.1:18095';
const companyId = process.env.ANALYTICS_COMPANY_ID;
if (!companyId) throw new Error('ANALYTICS_COMPANY_ID is required');

const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
const problems = [];
try {
    const desktop = await verify({ width: 1280, height: 800 });
    const mobile = await verify({ width: 390, height: 844 });
    if (problems.length) throw new Error(problems.join(' | '));
    console.log(JSON.stringify({ status: 'passed', database: process.env.DB_DATABASE, desktop, mobile, problems }, null, 2));
} finally { await browser.close(); }

async function verify(viewport) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) problems.push(`${message.type()}:${message.text()}`); });
    page.on('pageerror', error => problems.push(`pageerror:${error.message}`));
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'domcontentloaded' });
    const response = await page.goto(`${baseUrl}/admin/analytics/workforce?company_id=${companyId}`, { waitUntil: 'domcontentloaded' });
    if (!response || response.status() !== 200) throw new Error(`analytics status=${response?.status()}`);
    for (const text of ['Executive Summary', 'Workforce', 'Attendance & Leave', 'Recruitment Funnel', 'Organization & Headcount', 'Payroll & Compensation', 'Workflow, IAM & Data Quality']) await page.getByText(text, { exact: true }).first().waitFor();
    await page.getByRole('button', { name: 'Hướng dẫn sử dụng trang này' }).click();
    const dialog = page.locator('[data-admin-help-dialog]');
    await dialog.getByText('EnterpriseAnalyticsService', { exact: false }).waitFor();
    await dialog.getByText('salary assignments', { exact: false }).waitFor();
    await page.keyboard.press('Escape');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow !== 0) throw new Error(`overflow=${overflow}`);
    await context.close();
    return { viewport, status: 200, overflow };
}
