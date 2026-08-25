import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}
const baseUrl = process.env.IDENTITY_BASE_URL ?? 'http://127.0.0.1:18141';
const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });

try {
    const results = [];
    for (const viewport of [{ width: 1280, height: 800 }, { width: 390, height: 844 }]) {
        const context = await browser.newContext({ viewport });
        const page = await context.newPage();
        const consoleProblems = []; const pageErrors = [];
        page.on('console', m => { if (['warning', 'error'].includes(m.type())) consoleProblems.push(m.text()); });
        page.on('pageerror', e => pageErrors.push(e.message));
        await page.goto(`${baseUrl}/admin/audit/_fixtures/login`);
        await page.goto(`${baseUrl}/account`);
        await page.getByRole('heading', { name: 'Tài khoản & bảo mật' }).waitFor();
        await page.locator('[name=phone]').fill(viewport.width === 1280 ? '0901234567' : '0901234568');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Gửi OTP' }).click()]);
        const body = await page.locator('body').innerText();
        const match = body.match(/Mã demo cục bộ:\s*(\d{6})/);
        if (!match) throw new Error('local OTP evidence missing');
        await page.locator('[name=code]').fill(match[1]);
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Xác minh' }).click()]);
        await page.getByText('Số điện thoại đã được xác minh.').waitFor();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (overflow || consoleProblems.length || pageErrors.length) throw new Error(JSON.stringify({ overflow, consoleProblems, pageErrors }));
        results.push({ viewport, account: 'PASS', phoneOtp: 'PASS', overflow, console: 0, pageErrors: 0 });
        await context.close();
    }
    console.log(JSON.stringify({ status: 'passed', results }, null, 2));
} finally { await browser.close(); }
