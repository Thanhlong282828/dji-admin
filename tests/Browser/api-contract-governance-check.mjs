import { access, mkdir, writeFile } from 'node:fs/promises';
import process from 'node:process';
import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.API_CONTRACT_BROWSER_BASE_URL ?? 'http://127.0.0.1:8017';
const chromePath = process.env.API_CONTRACT_CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const evidenceDirectory = process.env.API_CONTRACT_EVIDENCE_DIR ?? 'storage/logs/phase3f21-browser-evidence';
const outputPath = process.env.API_CONTRACT_EVIDENCE_JSON ?? 'storage/logs/phase3f21-browser-evidence.json';
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

const report = {
    status: 'passed',
    browser: 'Google Chrome',
    chromeVersion: '151.0.7922.77',
    playwrightCoreVersion: '1.62.1',
    executable: chromePath,
    baseUrl,
    database: 'schema_admin_browser_testing',
    fixtureCleanup: 'pending-external-cleanup',
    evidence,
};
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
    page.on('response', response => {
        if ([403, 404, 500].includes(response.status())) httpErrors.push({ status: response.status(), url: response.url() });
    });

    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    const pages = ['/dashboard', '/admin/operations/api-contracts', '/admin/operations/architecture', '/admin/operations/test-quality', '/admin/operations/performance', '/admin/operations/security-assurance'];
    const results = [];
    for (const path of pages) {
        const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle' });
        const metrics = await page.evaluate(() => ({ innerWidth, innerHeight, clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth }));
        if (response?.status() !== 200 || metrics.innerWidth !== width || metrics.innerHeight !== height || metrics.scrollWidth !== metrics.clientWidth) throw new Error(`${label}/${path}: ${JSON.stringify({ status: response?.status(), ...metrics })}`);
        results.push({ path, status: response.status(), overflow: metrics.scrollWidth - metrics.clientWidth });
    }

    const contractText = await page.goto(`${baseUrl}/admin/operations/api-contracts`, { waitUntil: 'networkidle' }).then(() => page.locator('body').innerText());
    for (const expected of ['actual operations', 'documented operations', 'breaking findings', 'sanitized checksum']) {
        if (!contractText.toLowerCase().includes(expected)) throw new Error(`${label}: API Contracts missing ${expected}`);
    }
    await page.screenshot({ path: `${evidenceDirectory}/api-contracts-${label}.png`, fullPage: true });

    if (label === 'mobile') {
        await page.locator('[data-sidebar-open]').click();
        await page.waitForTimeout(300);
    }
    const operations = page.locator('[data-nav-group="operations"] [data-nav-group-toggle]');
    const operationItems = page.locator('#nav-group-operations');
    if (await operations.getAttribute('aria-expanded') !== 'true') throw new Error(`${label}: active Operations group is not open`);
    const openChildren = await operationItems.locator('.nav-item:visible').count();
    if (openChildren !== 13 || !(await page.getByRole('link', { name: 'API Contracts', exact: true }).evaluate(element => element.classList.contains('active')))) throw new Error(`${label}: Operations active/count regression (${openChildren})`);
    for (const linkName of ['API Contracts', 'API Documentation', 'Architecture', 'Test Quality']) {
        if (!(await page.getByRole('link', { name: linkName, exact: true }).isVisible())) throw new Error(`${label}: ${linkName} is not visible in Operations`);
    }
    await operations.click();
    const closedChildren = await operationItems.locator('.nav-item:visible').count();
    if (closedChildren !== 0) throw new Error(`${label}: Operations closed children=${closedChildren}`);
    await operations.click();

    if (label === 'mobile') {
        await page.locator('[data-sidebar-close]').click();
        const opener = page.locator('[data-sidebar-open]');
        await opener.click();
        await page.keyboard.press('Escape');
        if (await opener.getAttribute('aria-expanded') !== 'false' || !(await opener.evaluate(element => document.activeElement === element))) throw new Error('mobile: Escape did not close drawer and restore focus');
        await opener.click();
    }

    const swaggerNavigation = page.waitForResponse(response => response.url() === `${baseUrl}/api/documentation`);
    await page.getByRole('link', { name: 'API Documentation', exact: true }).click();
    const swaggerResponse = await swaggerNavigation;
    await page.waitForLoadState('networkidle');
    if (swaggerResponse?.status() !== 200 || !(await page.locator('#swagger-ui').isVisible())) throw new Error(`${label}: Swagger UI did not load`);
    await page.waitForFunction(() => document.body.textContent?.includes('Schema Enterprise API') === true);
    await page.screenshot({ path: `${evidenceDirectory}/swagger-${label}.png`, fullPage: true });
    const specResponse = await context.request.get(`${baseUrl}/docs`);
    const spec = await specResponse.json();
    const documentedOperations = Object.values(spec.paths ?? {}).reduce((count, methods) => count + Object.keys(methods).filter(method => ['get', 'post', 'put', 'patch', 'delete'].includes(method)).length, 0);
    if (specResponse.status() !== 200 || spec.openapi !== '3.0.0' || Object.keys(spec.paths ?? {}).length !== 11 || documentedOperations !== 24) throw new Error(`${label}: OpenAPI contract mismatch`);

    if (consoleErrors.length || consoleWarnings.length || pageErrors.length || httpErrors.length) throw new Error(`${label}: ${JSON.stringify({ consoleErrors, consoleWarnings, pageErrors, httpErrors })}`);
    await context.close();

    return {
        viewport: { label, width, height, exact: true },
        pages: results,
        operations: { openChildren, closedChildren },
        swagger: { status: swaggerResponse.status(), rootVisible: true },
        openapi: { status: specResponse.status(), version: spec.openapi, paths: 11, operations: documentedOperations },
        consoleErrors,
        consoleWarnings,
        pageErrors,
        httpErrors,
    };
}
