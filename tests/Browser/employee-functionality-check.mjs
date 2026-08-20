import { mkdir, rm, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.EMPLOYEE_BROWSER_BASE_URL ?? 'http://127.0.0.1:18091';
const companyId = process.env.EMPLOYEE_BROWSER_COMPANY_ID;
if (!companyId || !/^\d+$/.test(companyId)) throw new Error('EMPLOYEE_BROWSER_COMPANY_ID is required');
const chromePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const artifactDirectory = 'test-results/employee-functionality';
await mkdir(artifactDirectory, { recursive: true });
const firstAvatar = `${artifactDirectory}/first.png`;
const secondAvatar = `${artifactDirectory}/second.png`;
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFElEQVR4nGP8z8DAwMDAxMDAwMAAAAwBAQDJ/pLvAAAAAElFTkSuQmCC', 'base64');
await writeFile(firstAvatar, png);
await writeFile(secondAvatar, png);

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const evidence = { desktop: null, mobile: null, employeeId: null, consoleProblems: [] };
try {
    evidence.desktop = await desktopFlow();
    evidence.mobile = await mobileFlow(evidence.employeeId);
    if (evidence.consoleProblems.length) throw new Error(evidence.consoleProblems.join(' | '));
    console.log(JSON.stringify({ status: 'passed', database: process.env.DB_DATABASE, evidence }, null, 2));
} finally {
    await browser.close();
    await rm(firstAvatar, { force: true });
    await rm(secondAvatar, { force: true });
}

function monitor(page, label) {
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) evidence.consoleProblems.push(`${label}:console.${message.type()}:${message.text()}`); });
    page.on('pageerror', error => evidence.consoleProblems.push(`${label}:pageerror:${error.message}`));
    page.on('response', response => { if (response.status() >= 500) evidence.consoleProblems.push(`${label}:http.${response.status()}:${response.url()}`); });
}

async function login(page) {
    const response = await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    if (!response?.ok()) throw new Error(`fixture login failed: ${response?.status()}`);
}

async function assertNoOverflow(page, label) {
    const layout = await page.evaluate(() => ({ clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth }));
    if (layout.clientWidth !== layout.scrollWidth) throw new Error(`${label}: overflow ${JSON.stringify(layout)}`);
}

async function assertFormColor(page) {
    const colors = await page.locator('input:not([type=checkbox]):not([type=file]), select, textarea').evaluateAll(elements => [...new Set(elements.map(element => getComputedStyle(element).color))]);
    const allowed = new Set(['rgb(108, 113, 128)', 'rgb(244, 240, 229)']);
    if (colors.some(color => !allowed.has(color))) throw new Error(`form colors mismatch: ${JSON.stringify(colors)}`);
    return colors;
}

async function selectEmployeeCore(form, { unit, position, manager = null }) {
    await form.locator('[name=organization_unit_id]').selectOption({ label: unit });
    await form.locator('[name=job_position_id]').selectOption({ label: position });
    if (manager) await form.locator('[name=manager_id]').selectOption({ label: manager });
}

async function desktopFlow() {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await context.newPage();
    monitor(page, 'desktop');
    await login(page);
    await page.goto(`${baseUrl}/admin/job-positions/create?company_id=${companyId}`, { waitUntil: 'networkidle' });
    const positionForm = page.locator('form').filter({ has: page.locator('[name=career_level]') });
    await positionForm.locator('[name=code]').fill('BROWSER-POS');
    await positionForm.locator('[name=title]').fill('Browser Position');
    await positionForm.locator('[name=organization_unit_id]').selectOption({ label: 'Phòng Engineering' });
    await positionForm.locator('[name=career_level]').selectOption('1');
    await positionForm.locator('[name=approved_headcount]').fill('1');
    await positionForm.locator('[name=budgeted_cost]').fill('0');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), positionForm.getByRole('button', { name: 'Lưu vị trí' }).click()]);
    const positionRow = page.locator('tr').filter({ hasText: 'BROWSER-POS' });
    await positionRow.getByText('Level 1', { exact: true }).waitFor();
    const positionHref = await positionRow.getByRole('link', { name: 'Xem' }).getAttribute('href');
    const positionId = positionHref?.match(/job-positions\/(\d+)/)?.[1];
    if (!positionId) throw new Error('created Job Position id unavailable');
    await page.goto(`${baseUrl}/admin/job-positions/${positionId}/edit?company_id=${companyId}`, { waitUntil: 'networkidle' });
    const positionEdit = page.locator('form').filter({ has: page.locator('[name=career_level]') });
    await positionEdit.locator('[name=career_level]').selectOption('6');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), positionEdit.getByRole('button', { name: 'Lưu vị trí' }).click()]);
    if (await positionEdit.locator('[name=career_level]').inputValue() !== '6') throw new Error('Job Position Level edit did not persist');
    await page.goto(`${baseUrl}/admin/employees/create?company_id=${companyId}`, { waitUntil: 'networkidle' });
    const create = page.locator('form').filter({ has: page.locator('[name=employee_code]') });
    await create.locator('[name=employee_code]').fill('BROWSER-EMP-001');
    await create.locator('[name=full_name]').fill('Nguyễn Trình Duyệt');
    await create.locator('[name=date_of_birth]').fill('1994-05-12');
    await create.locator('[name=gender]').selectOption('male');
    await create.locator('[name=work_email]').fill('browser.employee@schema.test');
    await create.locator('[name=join_date]').fill('2026-08-17');
    await create.locator('[name=employment_status]').selectOption('active');
    await selectEmployeeCore(create, { unit: 'Phòng Engineering', position: 'Backend Developer — Phòng Engineering — Level 3', manager: 'Nguyễn Quản Lý — Engineering Manager — Phòng Engineering' });
    await create.locator('[name=avatar]').setInputFiles(firstAvatar);
    const createPreview = create.locator('[data-avatar-preview]');
    await createPreview.waitFor({ state: 'visible' });
    if (!(await createPreview.getAttribute('src'))?.startsWith('blob:')) throw new Error('create avatar preview is not a local blob');
    await create.locator('[name=create_account][type=checkbox]').check();
    await create.locator('[name=account_email]').fill('browser.employee.login@schema.test');
    await create.locator('[name=account_password]').fill('BrowserEmployee123!');
    await create.locator('[name=account_password_confirmation]').fill('BrowserEmployee123!');
    await create.locator('[name=account_role]').selectOption({ label: 'Employee' });
    await create.locator('[name=account_access_enabled][type=checkbox]').check();
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), create.getByRole('button', { name: 'Lưu hồ sơ' }).click()]);
    if (page.url().includes('/create')) throw new Error(`employee create failed: ${(await page.locator('body').innerText()).slice(0, 500)}`);
    const match = page.url().match(/\/admin\/employees\/(\d+)/);
    if (!match) throw new Error(`employee id unavailable: ${page.url()}`);
    evidence.employeeId = Number(match[1]);

    await page.goto(`${baseUrl}/admin/employees?company_id=${companyId}&search=BROWSER-EMP-001`, { waitUntil: 'networkidle' });
    let row = page.locator('tr').filter({ hasText: 'BROWSER-EMP-001' });
    await row.getByText('Phòng Engineering').waitFor();
    await row.getByText('Backend Developer').first().waitFor();
    await row.getByText('Employee', { exact: true }).waitFor();
    const listAvatar = row.locator('img.avatar');
    await listAvatar.waitFor({ state: 'visible' });
    if (!(await listAvatar.evaluate(image => image.complete && image.naturalWidth > 0))) throw new Error('employee list avatar is broken');

    await page.goto(`${baseUrl}/admin/employee-lifecycle/${evidence.employeeId}?company_id=${companyId}`, { waitUntil: 'networkidle' });
    await page.getByRole('heading', { name: 'Thăng chức' }).waitFor();
    await page.getByRole('heading', { name: 'Giáng chức' }).waitFor();
    const promotion = page.locator('section.panel').filter({ has: page.getByRole('heading', { name: 'Thăng chức' }) }).locator('[data-career-form]');
    await promotion.locator('[data-career-unit]').selectOption({ label: 'Phòng Engineering' });
    if (await promotion.locator('[data-career-position] option:not([value=""]):not([disabled])').count() < 2) throw new Error('promotion does not expose multiple higher levels');
    await promotion.locator('[data-career-position]').selectOption({ label: 'Engineering Lead — Phòng Engineering — Level 5' });
    await promotion.locator('[name=effective_at]').fill('2026-08-18T09:00');
    await promotion.locator('[name=reason]').fill('Browser promotion evidence');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), promotion.getByRole('button', { name: 'Thăng chức' }).click()]);

    await page.goto(`${baseUrl}/admin/employees?company_id=${companyId}&search=BROWSER-EMP-001`, { waitUntil: 'networkidle' });
    row = page.locator('tr').filter({ hasText: 'BROWSER-EMP-001' });
    await row.getByText('Engineering Lead').first().waitFor();

    await page.goto(`${baseUrl}/admin/employee-lifecycle/${evidence.employeeId}?company_id=${companyId}`, { waitUntil: 'networkidle' });
    const demotion = page.locator('section.panel').filter({ has: page.getByRole('heading', { name: 'Giáng chức' }) }).locator('[data-career-form]');
    await demotion.locator('[data-career-unit]').selectOption({ label: 'Phòng Engineering' });
    if (await demotion.locator('[data-career-position] option:not([value=""]):not([disabled])').count() < 2) throw new Error('demotion does not expose multiple lower levels');
    await demotion.locator('[data-career-position]').selectOption({ label: 'Junior Developer — Phòng Engineering — Level 1' });
    await demotion.locator('[name=effective_at]').fill('2026-08-19T09:00');
    await demotion.locator('[name=reason]').fill('Browser demotion evidence');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), demotion.getByRole('button', { name: 'Giáng chức' }).click()]);

    await page.goto(`${baseUrl}/admin/employees?company_id=${companyId}&search=BROWSER-EMP-001`, { waitUntil: 'networkidle' });
    row = page.locator('tr').filter({ hasText: 'BROWSER-EMP-001' });
    await row.getByText('Junior Developer').first().waitFor();
    await row.getByRole('link', { name: 'Sửa' }).click();
    await page.waitForLoadState('networkidle');
    const edit = page.locator('form').filter({ has: page.locator('[name=employee_code]') });
    const existingPreviewSource = await edit.locator('[data-avatar-preview]').getAttribute('src');
    if (!existingPreviewSource || existingPreviewSource.startsWith('blob:')) throw new Error('existing avatar preview unavailable');
    await selectEmployeeCore(edit, { unit: 'Phòng Tài chính', position: 'Accountant — Phòng Tài chính — Level 3', manager: 'Nguyễn Quản Lý — Engineering Manager — Phòng Engineering' });
    await edit.locator('[name=avatar]').setInputFiles(secondAvatar);
    const replacementPreviewSource = await edit.locator('[data-avatar-preview]').getAttribute('src');
    if (!replacementPreviewSource?.startsWith('blob:') || replacementPreviewSource === existingPreviewSource) throw new Error('replacement avatar preview did not change');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), edit.getByRole('button', { name: 'Lưu hồ sơ' }).click()]);
    await page.goto(`${baseUrl}/admin/employees?company_id=${companyId}&search=BROWSER-EMP-001`, { waitUntil: 'networkidle' });
    row = page.locator('tr').filter({ hasText: 'BROWSER-EMP-001' });
    await row.getByText('Phòng Tài chính').waitFor();
    await row.getByText('Accountant').first().waitFor();

    await page.goto(`${baseUrl}/admin/employees/${evidence.employeeId}?company_id=${companyId}`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Gỡ avatar' }).click();
    await page.waitForLoadState('networkidle');
    if (await page.getByRole('button', { name: 'Gỡ avatar' }).count()) throw new Error('avatar cleanup did not clear owned reference');
    const colors = await assertFormColor(page);
    await assertNoOverflow(page, 'desktop');
    await page.screenshot({ path: `${artifactDirectory}/desktop.png`, fullPage: true });
    await context.close();
    return { positionCreateEdit: 'levels-1-through-6-pass', create: 'pass', edit: 'pass', promotion: 'pass', demotion: 'pass', avatarPreview: 'create-existing-replacement-pass', avatar: 'upload-replace-remove-pass', listAvatar: 'real-image-pass', colors, overflow: 0 };
}

async function mobileFlow(employeeId) {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    monitor(page, 'mobile');
    await login(page);
    await page.goto(`${baseUrl}/admin/employees/${employeeId}/edit?company_id=${companyId}`, { waitUntil: 'networkidle' });
    const colors = await assertFormColor(page);
    await assertNoOverflow(page, 'mobile-edit');
    await page.goto(`${baseUrl}/admin/employee-lifecycle/${employeeId}?company_id=${companyId}`, { waitUntil: 'networkidle' });
    await page.getByRole('heading', { name: 'Thăng chức' }).waitFor();
    await page.getByRole('heading', { name: 'Giáng chức' }).waitFor();
    await assertNoOverflow(page, 'mobile-lifecycle');
    await page.screenshot({ path: `${artifactDirectory}/mobile.png`, fullPage: true });
    await context.close();
    return { edit: 'pass', lifecycle: 'pass', colors, overflow: 0 };
}
