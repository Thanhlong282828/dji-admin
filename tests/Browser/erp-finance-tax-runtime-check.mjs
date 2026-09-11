import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.ERP_BASE_URL ?? 'http://127.0.0.1:18161';
const companyId = process.env.ERP_COMPANY_ID;
if (!companyId || !/^\d+$/.test(companyId)) throw new Error('ERP_COMPANY_ID is required');

const browser = await chromium.launch({
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: true,
});
const report = { desktop: [], mobile: [], consoleErrors: 0, consoleWarnings: 0, pageErrors: 0, overflow: 0 };

try {
    for (const [label, viewport] of [['desktop', { width: 1280, height: 800 }], ['mobile', { width: 390, height: 844 }]]) {
        const context = await browser.newContext({ viewport });
        const loginPage = await context.newPage();
        const login = await loginPage.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
        if (!login?.ok()) throw new Error(`fixture login HTTP ${login?.status()}`);
        await loginPage.close();

        for (const [name, path, expectedText] of [
            ['financeDashboard', `/admin/finance/branch-performance?company_id=${companyId}`, 'Hiệu quả tài chính chi nhánh'],
            ['payrollReconciliation', `/admin/finance/payroll-reconciliation?company_id=${companyId}`, 'Đối soát Payroll và Tài chính'],
            ['financialStatements', `/admin/finance/financial-statements?company_id=${companyId}`, 'Sổ cái & Báo cáo tài chính'],
            ['financeExpenses', `/admin/finance/operations/expenses?company_id=${companyId}`, 'Expense workflow'],
            ['financeAssets', `/admin/finance/operations/assets?company_id=${companyId}`, 'Asset register'],
            ['financeBudget', `/admin/finance/operations/budget?company_id=${companyId}&year=2026`, 'Budget · Commitment · Actual'],
            ['treasury', `/admin/finance/treasury?company_id=${companyId}`, 'Cash & Bank Control'],
            ['periodClose', `/admin/finance/period-close?company_id=${companyId}`, 'Accounting Period Close'],
            ['approvalInbox', `/admin/approvals?company_id=${companyId}`, 'Approval Inbox'],
            ['accountingJournals', `/admin/payroll/accounting/journals?company_id=${companyId}`, 'Bút toán'],
            ['taxDashboard', `/admin/finance/tax-dashboard?company_id=${companyId}`, 'Thuế và nghĩa vụ tài chính'],
            ['insurance', `/admin/payroll/insurance/policies?company_id=${companyId}`, 'Insurance Policies'],
            ['governmentFiling', `/admin/payroll/government-filings?company_id=${companyId}`, 'Government Filings'],
            ['vendors', `/admin/procurement/vendors?company_id=${companyId}`, 'Vendor master'],
            ['purchaseRequests', `/admin/procurement/requests?company_id=${companyId}`, 'Purchase Requests'],
            ['purchaseOrders', `/admin/procurement/orders?company_id=${companyId}`, 'Purchase Orders'],
            ['goodsReceipts', `/admin/procurement/receipts?company_id=${companyId}`, 'Goods Receipts'],
            ['vendorBills', `/admin/procurement/payables?company_id=${companyId}`, 'AP Outstanding & Aging'],
            ['inventoryValue', `/admin/seller/inventory?company_id=${companyId}`, 'Tồn kho Variant / SKU'],
            ['projectOverview', `/admin/projects/overview?company_id=${companyId}`, 'Tổng quan dự án'],
            ['projects', `/admin/projects?company_id=${companyId}`, 'Tổng quan dự án'],
            ['maintenanceOverview', `/admin/maintenance/overview?company_id=${companyId}`, 'Tổng quan bảo trì'],
            ['maintenanceRequests', `/admin/maintenance/requests?company_id=${companyId}`, 'Yêu cầu bảo trì'],
            ['maintenanceWorkOrders', `/admin/maintenance/work-orders?company_id=${companyId}`, 'Lệnh công việc'],
            ['maintenancePlans', `/admin/maintenance/preventive-plans?company_id=${companyId}`, 'Bảo trì định kỳ'],
        ]) {
            const page = await context.newPage();
            page.on('console', message => {
                if (message.type() === 'error') report.consoleErrors++;
                if (message.type() === 'warning') report.consoleWarnings++;
            });
            page.on('pageerror', () => report.pageErrors++);
            const response = await gotoStable(page, baseUrl + path);
            if (response !== null && !response.ok()) throw new Error(`${label}/${name}: HTTP ${response.status()}`);
            const expected = page.locator('main').getByText(expectedText, { exact: false }).first();
            if (!await expected.count()) {
                throw new Error(`${label}/${name}: expected text missing at ${page.url()}; body=${(await page.locator('body').innerText()).slice(0, 300)}`);
            }
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            if (overflow !== 0) {
                report.overflow++;
                throw new Error(`${label}/${name}: document overflow ${overflow}`);
            }
            const help = page.locator('[data-admin-help-open]');
            if (await help.count() !== 1) throw new Error(`${label}/${name}: contextual help missing`);
            await help.click();
            if (!await page.locator('[data-admin-help-dialog][open]').count()) throw new Error(`${label}/${name}: contextual help did not open`);
            await page.keyboard.press('Escape');
            report[label].push(name);
            await page.close();
        }
        await context.close();
    }

    if (report.consoleErrors || report.consoleWarnings || report.pageErrors || report.overflow) {
        throw new Error(`runtime issues: ${JSON.stringify(report)}`);
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
