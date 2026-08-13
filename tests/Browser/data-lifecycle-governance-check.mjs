import { access, mkdir, writeFile } from 'node:fs/promises';
import process from 'node:process';
import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.DATA_LIFECYCLE_BROWSER_BASE_URL ?? 'http://127.0.0.1:8022';
const chromePath = process.env.DATA_LIFECYCLE_CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const evidenceDirectory = process.env.DATA_LIFECYCLE_EVIDENCE_DIR ?? 'storage/logs/phase3f22-browser-evidence';
const outputPath = process.env.DATA_LIFECYCLE_EVIDENCE_JSON ?? 'storage/logs/phase3f22-browser-evidence.json';
await access(chromePath);
await mkdir(evidenceDirectory, { recursive: true });

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const evidence = [];
try {
    evidence.push(await verify('desktop', 1280, 800));
    evidence.push(await verify('mobile', 390, 844));
} finally {
    await browser.close();
}

const report = { status: 'passed', browser: 'Google Chrome', baseUrl, database: 'schema_admin_browser_testing', fixtureCleanup: 'pending-external-cleanup', evidence };
await writeFile(outputPath, `${JSON.stringify(report, null, 2)}\n`);
console.log(JSON.stringify(report, null, 2));

async function verify(label, width, height) {
    const context = await browser.newContext({ viewport: { width, height } });
    const page = await context.newPage();
    const consoleErrors = [];
    const consoleWarnings = [];
    const pageErrors = [];
    const httpErrors = [];
    page.on('console', message => {
        if (message.type() === 'error') consoleErrors.push(message.text());
        if (message.type() === 'warning') consoleWarnings.push(message.text());
    });
    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('response', response => { if ([403, 404, 500].includes(response.status())) httpErrors.push({ status: response.status(), url: response.url() }); });

    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    const pages = ['/admin/operations/data-lifecycle', '/admin/operations/data-protection', '/admin/operations/continuity', '/admin/audit', '/admin/operations/architecture', '/admin/operations/api-contracts'];
    const results = [];
    for (const path of pages) {
        const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle' });
        const metrics = await page.evaluate(() => ({ innerWidth, innerHeight, clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth }));
        if (response?.status() !== 200 || metrics.innerWidth !== width || metrics.innerHeight !== height || metrics.scrollWidth !== metrics.clientWidth) throw new Error(`${label}/${path}: ${JSON.stringify({ status: response?.status(), ...metrics })}`);
        results.push({ path, status: response.status(), overflow: metrics.scrollWidth - metrics.clientWidth });
    }

    await page.goto(`${baseUrl}/admin/operations/data-lifecycle`, { waitUntil: 'networkidle' });
    for (const expected of ['Enterprise Data Lifecycle', 'Destructive switch', 'DISABLED']) {
        const bodyText = await page.locator('body').innerText();
        if (!bodyText.includes(expected)) throw new Error(`${label}: lifecycle dashboard missing ${expected} at ${page.url()}: ${bodyText.slice(0, 240)}`);
    }
    const operations = page.locator('[data-nav-group="operations"] [data-nav-group-toggle]');
    const operationsItems = page.locator('#nav-group-operations');
    if (label === 'mobile') {
        await page.locator('[data-sidebar-open]').click();
        await page.waitForTimeout(300);
    }
    if (await operations.getAttribute('aria-expanded') !== 'true') throw new Error(`${label}: active Operations group is not open`);
    const openChildren = await operationsItems.locator('.nav-item:visible').count();
    if (openChildren !== 14 || !(await page.getByRole('link', { name: 'Data Lifecycle', exact: true }).evaluate(element => element.classList.contains('active')))) throw new Error(`${label}: Operations active/count regression (${openChildren})`);
    await operations.click();
    const closedChildren = await operationsItems.locator('.nav-item:visible').count();
    if (closedChildren !== 0) throw new Error(`${label}: collapsed Operations children=${closedChildren}`);
    await operations.click();
    await page.screenshot({ path: `${evidenceDirectory}/data-lifecycle-${label}.png`, fullPage: true });

    if (consoleErrors.length || consoleWarnings.length || pageErrors.length || httpErrors.length) throw new Error(`${label}: ${JSON.stringify({ consoleErrors, consoleWarnings, pageErrors, httpErrors })}`);
    await context.close();
    return { viewport: { label, width, height, exact: true }, pages: results, operations: { openChildren, closedChildren }, consoleErrors, consoleWarnings, pageErrors, httpErrors };
}
