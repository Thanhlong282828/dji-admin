import { access, mkdir } from 'node:fs/promises';
import process from 'node:process';
import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.OPERATIONS_BROWSER_BASE_URL ?? 'http://127.0.0.1:18082';
const chromePath = process.env.OPERATIONS_CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const evidenceDirectory = process.env.OPERATIONS_EVIDENCE_DIR ?? 'storage/logs/operations-browser-evidence';
await access(chromePath); await mkdir(evidenceDirectory, { recursive: true });
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const evidence = [];
try { evidence.push(await verify('desktop', 1280, 800)); evidence.push(await verify('mobile', 390, 844)); } finally { await browser.close(); }
console.log(JSON.stringify({ status: 'passed', evidence }, null, 2));

async function verify(label, width, height) {
    const context = await browser.newContext({ viewport: { width, height } }); const page = await context.newPage(); const problems = [];
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) problems.push(`console.${message.type()}: ${message.text()}`); });
    page.on('pageerror', error => problems.push(`pageerror: ${error.message}`));
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    const response = await page.goto(`${baseUrl}/admin/operations/reliability`, { waitUntil: 'networkidle' });
    if (!response?.ok()) throw new Error(`${label}: HTTP ${response?.status()}`);
    const metrics = await page.evaluate(() => ({ innerWidth, innerHeight, clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth, dashboard: Boolean(document.querySelector('main.sre')) }));
    for (const [actual, expected, name] of [[metrics.innerWidth, width, 'width'], [metrics.innerHeight, height, 'height'], [metrics.clientWidth, width, 'client'], [metrics.scrollWidth, metrics.clientWidth, 'overflow']]) if (actual !== expected) throw new Error(`${label}/${name}: ${actual} !== ${expected}`);
    if (!metrics.dashboard || problems.length) throw new Error(`${label}: ${problems.join(' | ') || 'dashboard missing'}`);
    await page.screenshot({ path: `${evidenceDirectory}/${label}.png`, fullPage: true }); await context.close();
    return { label, ...metrics, consoleProblems: 0 };
}
