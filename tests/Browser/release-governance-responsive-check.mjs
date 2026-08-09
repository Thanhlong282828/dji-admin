import { access, mkdir } from 'node:fs/promises';
import process from 'node:process';
import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.RELEASE_BROWSER_BASE_URL ?? 'http://127.0.0.1:18085';
const chromePath = process.env.RELEASE_CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const evidenceDirectory = process.env.RELEASE_EVIDENCE_DIR ?? 'storage/logs/release-browser-evidence';
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
console.log(JSON.stringify({ status: 'passed', evidence }, null, 2));

async function verify(label, width, height) {
    const context = await browser.newContext({ viewport: { width, height } });
    const page = await context.newPage();
    const problems = [];
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) problems.push(`console.${message.type()}: ${message.text()}`); });
    page.on('pageerror', error => problems.push(`pageerror: ${error.message}`));
    page.on('response', response => { if (response.status() >= 400) problems.push(`http.${response.status()}: ${response.url()}`); });
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    const response = await page.goto(`${baseUrl}/admin/operations/releases`, { waitUntil: 'networkidle' });
    if (!response?.ok()) throw new Error(`${label}: HTTP ${response?.status()}`);
    const metrics = await page.evaluate(() => ({ innerWidth, innerHeight, clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth, dashboard: Boolean(document.querySelector('main.rel')) }));
    if (metrics.innerWidth !== width || metrics.innerHeight !== height || metrics.clientWidth !== width || metrics.scrollWidth !== metrics.clientWidth || !metrics.dashboard) throw new Error(`${label}: ${JSON.stringify(metrics)}`);
    if (problems.length) throw new Error(`${label}: ${problems.join(' | ')}`);
    await page.screenshot({ path: `${evidenceDirectory}/${label}.png`, fullPage: true });
    await context.close();
    return { label, width, height, documentOverflow: 0, consoleProblems: 0 };
}
