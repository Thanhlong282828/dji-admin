import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}
const baseUrl = process.env.COMPENSATION_BROWSER_BASE_URL ?? 'http://127.0.0.1:18092';
const companyId = process.env.COMPENSATION_BROWSER_COMPANY_ID;
const employeeId = process.env.COMPENSATION_BROWSER_EMPLOYEE_ID;
if (!companyId || !employeeId) throw new Error('Browser fixture identifiers are required');
const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
const problems = [];
try {
    const desktop = await verify({ width: 1280, height: 800 }, true);
    const mobile = await verify({ width: 390, height: 844 }, false);
    if (problems.length) throw new Error(problems.join(' | '));
    console.log(JSON.stringify({ status: 'passed', database: process.env.DB_DATABASE, desktop, mobile, problems }, null, 2));
} finally { await browser.close(); }

async function verify(viewport, desktop) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) problems.push(`${message.type()}:${message.text()}`); });
    page.on('pageerror', error => problems.push(`pageerror:${error.message}`));
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    await page.goto(`${baseUrl}/admin/employee-lifecycle?company_id=${companyId}`, { waitUntil: 'networkidle' });
    await page.getByText('25.000.000 ₫').waitFor();
    await page.getByText('31.000.000 ₫').waitFor();
    await page.goto(`${baseUrl}/admin/employee-lifecycle/${employeeId}?company_id=${companyId}`, { waitUntil: 'networkidle' });
    await page.getByRole('heading', { name: 'Compensation hiện tại' }).waitFor();
    await page.getByText('25.000.000 ₫').first().waitFor();
    const promotion = page.locator('section.panel').filter({ has: page.getByRole('heading', { name: 'Thăng chức' }) }).locator('[data-career-form]');
    await promotion.locator('[data-career-unit]').selectOption({ label: 'Phòng Engineering' });
    await promotion.locator('[data-career-position]').selectOption({ label: 'Engineering Lead — Phòng Engineering — Level 5' });
    await promotion.locator('[data-projected-compensation]').waitFor({ state: 'visible' });
    const demotion = page.locator('section.panel').filter({ has: page.getByRole('heading', { name: 'Giáng chức' }) }).locator('[data-career-form]');
    await demotion.locator('[data-career-unit]').selectOption({ label: 'Phòng Engineering' });
    await demotion.locator('[data-career-position]').selectOption({ label: 'Junior Developer — Phòng Engineering — Level 1' });
    await demotion.locator('[data-projected-compensation]').waitFor({ state: 'visible' });
    const moneyButton = page.getByRole('button', { name: 'Compensation Reference' });
    await moneyButton.click();
    const moneyDialog = page.locator('[data-compensation-reference-dialog]');
    await moneyDialog.getByText('Target Gross').first().waitFor();
    if (await moneyDialog.locator('tbody tr').count() < 5) throw new Error('Compensation reference rows missing');
    await page.keyboard.press('Escape');
    if (await moneyDialog.evaluate(dialog => dialog.open)) throw new Error('Compensation dialog did not close');
    if (!(await moneyButton.evaluate(button => button === document.activeElement))) throw new Error('Compensation focus was not restored');
    const helpButton = page.getByRole('button', { name: 'Hướng dẫn sử dụng trang này' });
    if (desktop && await helpButton.getAttribute('title') !== 'Hướng dẫn sử dụng trang này') throw new Error('Help hover tooltip missing');
    await helpButton.click();
    const helpDialog = page.locator('[data-admin-help-dialog]');
    await helpDialog.getByRole('heading', { name: 'Vòng đời nhân sự' }).waitFor();
    await helpDialog.getByText('EmployeeLifecycleController').waitFor();
    await page.keyboard.press('Escape');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow !== 0) throw new Error(`overflow=${overflow}`);
    await context.close();
    return { lifecycleTable: 'pass', current: 'pass', promotionProjection: 'pass', demotionProjection: 'pass', compensationDialog: 'pass', help: 'pass', keyboard: 'pass', overflow };
}
