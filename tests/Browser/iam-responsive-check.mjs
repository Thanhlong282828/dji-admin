import { access, mkdir } from 'node:fs/promises';
import process from 'node:process';
import { chromium } from 'playwright-core';

const required = {
    APP_ENV: 'testing',
    DB_CONNECTION: 'mysql',
    DB_DATABASE: 'schema_admin_testing',
};

for (const [key, expected] of Object.entries(required)) {
    if (process.env[key] !== expected) {
        throw new Error(`${key} must equal ${expected}`);
    }
}

const baseUrl = process.env.IAM_BROWSER_BASE_URL ?? 'http://127.0.0.1:8013';
const chromePath = process.env.IAM_CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const companyId = positiveInteger('IAM_COMPANY_ID');
const requestId = positiveInteger('IAM_REQUEST_ID');
const subjectUserId = positiveInteger('IAM_SUBJECT_USER_ID');
const reviewId = positiveInteger('IAM_REVIEW_ID');
const evidenceDirectory = process.env.IAM_EVIDENCE_DIR ?? 'storage/logs/iam-browser-evidence';

await access(chromePath);
await mkdir(evidenceDirectory, { recursive: true });

const routes = [
    ['dashboard', `/admin/iam?company_id=${companyId}`],
    ['requests', `/admin/iam/requests?company_id=${companyId}`],
    ['request-detail', `/admin/iam/requests/${requestId}?company_id=${companyId}`],
    ['access-360', `/admin/iam/users/${subjectUserId}/access-360?company_id=${companyId}`],
    ['privileged', `/admin/iam/privileged?company_id=${companyId}`],
    ['sod', `/admin/iam/sod?company_id=${companyId}`],
    ['reviews', `/admin/iam/reviews?company_id=${companyId}`],
    ['review-detail', `/admin/iam/reviews/${reviewId}?company_id=${companyId}`],
];

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const evidence = [];

try {
    evidence.push(...await verifyViewport(browser, 'desktop', 1280, 800));
    evidence.push(...await verifyViewport(browser, 'mobile', 390, 844));
} finally {
    await browser.close();
}

console.log(JSON.stringify({ status: 'passed', chromePath, evidence }, null, 2));

async function verifyViewport(browserInstance, label, width, height) {
    const context = await browserInstance.newContext({ viewport: { width, height } });
    const page = await context.newPage();
    const runtimeProblems = [];
    page.on('console', message => {
        if (message.type() === 'error' || message.type() === 'warning') {
            runtimeProblems.push(`console.${message.type()}: ${message.text()}`);
        }
    });
    page.on('pageerror', error => runtimeProblems.push(`pageerror: ${error.message}`));

    await page.goto(`${baseUrl}/admin/iam/_fixtures/login`, { waitUntil: 'networkidle' });
    if (!page.url().includes('/admin/iam')) {
        throw new Error(`${label}: fixture login did not reach IAM`);
    }

    const results = [];
    for (const [name, path] of routes) {
        runtimeProblems.length = 0;
        const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle' });
        if (!response?.ok()) {
            throw new Error(`${label}/${name}: HTTP ${response?.status() ?? 'unknown'}`);
        }

        const metrics = await page.evaluate(({ expectedWidth, expectedHeight }) => {
            const root = document.documentElement;
            const tables = [...document.querySelectorAll('.iam-table')];
            const actionGroups = [...document.querySelectorAll('.iam-actions, .iam-nav')];
            const cards = [...document.querySelectorAll('.iam-card')];
            const viewportSafe = element => {
                const rect = element.getBoundingClientRect();
                return rect.left >= -0.5 && rect.right <= expectedWidth + 0.5;
            };

            return {
                innerWidth: window.innerWidth,
                innerHeight: window.innerHeight,
                clientWidth: root.clientWidth,
                scrollWidth: root.scrollWidth,
                hasMain: Boolean(document.querySelector('main.iam')),
                tableInnerScroll: tables.every(container => container.scrollWidth >= container.clientWidth && viewportSafe(container)),
                actionsWrappedSafely: actionGroups.every(viewportSafe),
                cardsResponsive: cards.every(viewportSafe),
                sidebarSafe: [...document.querySelectorAll('aside, [data-sidebar], .sidebar')].every(viewportSafe),
                expectedHeight,
            };
        }, { expectedWidth: width, expectedHeight: height });

        assertEqual(metrics.innerWidth, width, `${label}/${name}: innerWidth`);
        assertEqual(metrics.innerHeight, height, `${label}/${name}: innerHeight`);
        assertEqual(metrics.clientWidth, width, `${label}/${name}: clientWidth`);
        assertEqual(metrics.scrollWidth, metrics.clientWidth, `${label}/${name}: document overflow`);
        assertTrue(metrics.hasMain, `${label}/${name}: IAM main missing`);
        assertTrue(metrics.tableInnerScroll, `${label}/${name}: table escaped its scroll container`);
        assertTrue(metrics.actionsWrappedSafely, `${label}/${name}: action group overflow`);
        assertTrue(metrics.cardsResponsive, `${label}/${name}: card overflow`);
        assertTrue(metrics.sidebarSafe, `${label}/${name}: sidebar overflow`);
        if (runtimeProblems.length > 0) {
            throw new Error(`${label}/${name}: ${runtimeProblems.join(' | ')}`);
        }

        await page.screenshot({ path: `${evidenceDirectory}/${label}-${name}.png`, fullPage: true });
        results.push({ label, name, path, ...metrics, consoleProblems: 0 });
    }

    await context.close();
    return results;
}

function positiveInteger(name) {
    const value = Number(process.env[name]);
    if (!Number.isInteger(value) || value <= 0) {
        throw new Error(`${name} must be a positive integer`);
    }
    return value;
}

function assertEqual(actual, expected, message) {
    if (actual !== expected) {
        throw new Error(`${message}: expected ${expected}, got ${actual}`);
    }
}

function assertTrue(value, message) {
    if (!value) {
        throw new Error(message);
    }
}
