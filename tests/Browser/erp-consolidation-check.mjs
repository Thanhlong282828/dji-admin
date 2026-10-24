import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.ERP_BASE_URL ?? 'http://127.0.0.1:18161';
const companyId = process.env.ERP_COMPANY_ID;
if (!companyId || !/^\d+$/.test(companyId)) throw new Error('ERP_COMPANY_ID is required');
const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
const report = { desktop: [], mobile: [], console: 0, pageErrors: 0, overflow: 0 };

try {
    for (const [label, viewport] of [['desktop', { width: 1280, height: 800 }], ['mobile', { width: 390, height: 844 }]]) {
        const context = await browser.newContext({ viewport });
        const problems = [];
        const loginPage = await context.newPage();
        const login = await loginPage.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
        if (!login?.ok()) throw new Error(`fixture login HTTP ${login?.status()}`);
        await loginPage.close();

        for (const [name, path, text] of [
            ['dashboard', `/dashboard?company_id=${companyId}`, 'Vận hành chi nhánh'],
            ['attendance', `/admin/attendance?company_id=${companyId}`, 'Chấm công'],
            ['employeePortal', '/employee-portal', 'ERP Browser Employee'],
            ['branchBonus', `/admin/branch-bonuses?company_id=${companyId}`, 'Thưởng chi nhánh'],
            ['branchFinance', `/admin/finance/branch-performance?company_id=${companyId}`, 'Hiệu quả tài chính chi nhánh'],
        ]) {
            const page = await context.newPage();
            page.on('console', message => { if (['warning', 'error'].includes(message.type())) problems.push(`console.${message.type()}: ${message.text()}`); });
            page.on('pageerror', error => problems.push(`pageerror: ${error.message}`));
            const response = await gotoStable(page, baseUrl + path);
            if (response !== null && !response.ok()) throw new Error(`${label}/${name}: HTTP ${response.status()}`);
            await page.locator('main').getByText(text, { exact: false }).first().waitFor();
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            if (overflow !== 0) throw new Error(`${label}/${name}: overflow ${overflow}`);
            const help = page.locator('[data-admin-help-open]');
            if (await help.count() !== 1) throw new Error(`${label}/${name}: contextual help missing`);
            await help.click();
            if (!await page.locator('[data-admin-help-dialog][open]').count()) throw new Error(`${label}/${name}: help dialog did not open`);
            await page.keyboard.press('Escape');
            report[label].push(name);
            await page.close();
        }
        if (problems.length) throw new Error(`${label}: ${problems.join(' | ')}`);
        await context.close();
    }
    console.log(JSON.stringify({ status: 'passed', ...report }, null, 2));
} finally {
    await browser.close();
}

async function gotoStable(page, url) {
    try {
        return await page.goto(url, { waitUntil: 'domcontentloaded' });
    } catch (error) {
        if (!String(error).includes('ERR_ABORTED')) throw error;
        await page.waitForLoadState('domcontentloaded');
        return null;
    }
}
