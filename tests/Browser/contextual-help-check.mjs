import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}
const baseUrl = process.env.CONTEXT_HELP_BASE_URL ?? 'http://127.0.0.1:18094';
const companyId = process.env.CONTEXT_HELP_COMPANY_ID;
if (!companyId) throw new Error('CONTEXT_HELP_COMPANY_ID is required');

const guides = [
    ['/admin/employees', 'Nhân viên', 'current primary assignment'],
    ['/admin/employee-lifecycle', 'Vòng đời nhân sự', 'promotion/demotion'],
    ['/admin/organization-units', 'Đơn vị tổ chức', 'hierarchy'],
    ['/admin/attendance', 'Chấm công', 'check-in/out'],
    ['/admin/leave', 'Nghỉ phép', 'yêu cầu nghỉ phép'],
    ['/admin/recruitment/dashboard', 'Tổng quan tuyển dụng', 'Requisition'],
    ['/admin/onboarding/dashboard', 'Onboarding', 'checklist'],
    ['/admin/payroll/periods', 'Kỳ lương', 'kỳ lương'],
    ['/admin/payroll/inputs', 'Payroll Inputs', 'point-in-time snapshot'],
    ['/admin/payroll/runs', 'Payroll Runs', 'Calculate'],
    ['/admin/payroll/government-filings', 'Government Filing', 'Government filing artifact'],
    ['/admin/workflows', 'Workflow Center', 'Definition'],
    ['/admin/iam', 'IAM Governance', 'request/review'],
    ['/admin/integrations', 'Integrations', 'outbound'],
    ['/admin/audit', 'Audit Platform', 'append-only'],
    ['/admin/operations/data-quality', 'Data Quality', 'Đối soát read-only'],
    ['/admin/operations/data-lifecycle', 'Data Lifecycle', 'retention'],
    ['/admin/operations/reliability', 'Reliability', 'SLO'],
];

const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
const problems = [];
try {
    const desktop = await verify({ width: 1280, height: 800 });
    const mobile = await verify({ width: 390, height: 844 });
    if (problems.length) throw new Error(problems.join(' | '));
    console.log(JSON.stringify({ status: 'passed', database: process.env.DB_DATABASE, audited: guides.length + 1, desktop, mobile, problems }, null, 2));
} finally { await browser.close(); }

async function verify(viewport) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) problems.push(`${message.type()}:${message.text()}`); });
    page.on('pageerror', error => problems.push(`pageerror:${error.message}`));
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    let checked = 0;
    for (const [path, title, evidence] of guides) {
        const response = await page.goto(`${baseUrl}${path}?company_id=${companyId}`, { waitUntil: 'networkidle' });
        if (!response || response.status() !== 200) throw new Error(`${path} status=${response?.status()}`);
        const trigger = page.getByRole('button', { name: 'Hướng dẫn sử dụng trang này' });
        await trigger.click();
        const dialog = page.locator('[data-admin-help-dialog]');
        await dialog.getByRole('heading', { name: title, exact: true }).waitFor();
        for (const section of ['Tổng quan', 'Dữ liệu lấy từ đâu', 'Các chức năng', 'Cách thao tác', 'Quy tắc quan trọng', 'Sau khi lưu thì dữ liệu đi đâu', 'Quyền cần có', 'Lưu ý', 'Luồng hệ thống']) {
            await dialog.getByRole('heading', { name: section, exact: true }).waitFor();
        }
        await dialog.getByText(evidence, { exact: false }).first().waitFor();
        await page.keyboard.press('Escape');
        if (await dialog.evaluate(element => element.open)) throw new Error(`${path} dialog did not close`);
        if (!(await trigger.evaluate(element => element === document.activeElement))) throw new Error(`${path} focus not restored`);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (overflow !== 0) throw new Error(`${path} overflow=${overflow}`);
        checked++;
    }
    const swagger = await page.goto(`${baseUrl}/api/documentation`, { waitUntil: 'networkidle' });
    if (!swagger || swagger.status() !== 200) throw new Error(`Swagger status=${swagger?.status()}`);
    await context.close();
    return { viewport, checked, swagger: 200, contextMismatch: 0, overflow: 0 };
}
