import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.FINAL_AUDIT_BASE_URL ?? 'http://127.0.0.1:18140';
const companyId = process.env.FINAL_AUDIT_COMPANY_ID;
if (!companyId || !/^\d+$/.test(companyId)) throw new Error('FINAL_AUDIT_COMPANY_ID is required');
const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });

try {
    const desktop = await walk({ width: 1280, height: 800 }, true);
    const mobile = await walk({ width: 390, height: 844 }, false);
    console.log(JSON.stringify({ status: 'passed', desktop, mobile }, null, 2));
} finally {
    await browser.close();
}

async function walk(viewport, verifyDialogs) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    const consoleProblems = [];
    const pageErrors = [];
    page.setDefaultTimeout(10_000);
    page.on('console', message => {
        if (['warning', 'error'].includes(message.type())) consoleProblems.push(`${message.type()}:${message.text()}:${message.location().url}`);
    });
    page.on('pageerror', error => pageErrors.push(error.message));

    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'domcontentloaded' });
    await page.goto(`${baseUrl}/dashboard?company_id=${companyId}`, { waitUntil: 'domcontentloaded' });
    const expectedPrimaryDestinations = await page.locator('[data-primary-navigation]').evaluate(navigation => {
        const value = navigation.getAttribute('data-navigation-destination-count');
        if (value === null || !/^\d+$/.test(value)) throw new Error('canonical navigation destination count is unavailable');
        return Number.parseInt(value, 10);
    });
    const destinations = await page.locator('[data-primary-navigation] a.nav-item').evaluateAll(links => links.map(link => ({
        label: (link.textContent ?? '').trim().replace(/\s+/g, ' '),
        href: link.href,
    })));
    if (destinations.length !== expectedPrimaryDestinations) throw new Error(`primary destinations=${destinations.length}`);

    const failures = [];
    let maxOverflow = 0;
    let helpTriggers = 0;
    const missingHelp = [];
    for (const destination of destinations) {
        const response = await gotoStable(page, destination.href);
        const status = response?.status() ?? 0;
        if (status !== 200) failures.push(`${destination.label}:HTTP ${status}`);
        const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
        maxOverflow = Math.max(maxOverflow, overflow);
        if (overflow !== 0) failures.push(`${destination.label}:overflow ${overflow}`);
        if (await page.getByRole('button', { name: 'Hướng dẫn sử dụng trang này' }).count() > 0) helpTriggers += 1;
        else missingHelp.push(destination.label);
    }

    if (verifyDialogs) {
        await page.goto(`${baseUrl}/admin/operations/go-live?company_id=${companyId}`, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Hướng dẫn sử dụng trang này' }).click();
        await page.locator('[data-admin-help-dialog]').waitFor({ state: 'visible' });
        await page.keyboard.press('Escape');
        if (await page.getByRole('button', { name: 'Compensation Reference' }).count()) {
            await page.getByRole('button', { name: 'Compensation Reference' }).click();
            await page.locator('[data-compensation-reference-dialog]').waitFor({ state: 'visible' });
            await page.keyboard.press('Escape');
        }
    }

    await page.waitForTimeout(100);
    if (helpTriggers !== expectedPrimaryDestinations) failures.push(`help triggers=${helpTriggers}; missing=${missingHelp.join(',')}`);
    if (consoleProblems.length) failures.push(`console=${consoleProblems.join(' || ')}`);
    if (pageErrors.length) failures.push(`pageerrors=${pageErrors.join(' || ')}`);
    await context.close();
    if (failures.length) throw new Error(failures.join(' | '));

    return {
        viewport,
        primaryDestinations: destinations.length,
        http200: destinations.length,
        helpTriggers,
        unexpected403: 0,
        notFound404: 0,
        serverErrors500: 0,
        consoleWarningsErrors: 0,
        pageErrors: 0,
        maxOverflow,
    };
}

async function gotoStable(page, url) {
    try {
        return await page.goto(url, { waitUntil: 'domcontentloaded' });
    } catch (error) {
        if (!String(error).includes('ERR_ABORTED') && !String(error).includes('ERR_NETWORK_IO_SUSPENDED')) throw error;
        await page.waitForTimeout(100);
        return page.goto(url, { waitUntil: 'domcontentloaded' });
    }
}
