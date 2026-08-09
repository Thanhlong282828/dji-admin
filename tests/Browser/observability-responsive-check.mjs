import { access, mkdir } from 'node:fs/promises';
import process from 'node:process';
import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.OBSERVABILITY_BROWSER_BASE_URL ?? 'http://127.0.0.1:18083';
const chromePath = process.env.OBSERVABILITY_CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const evidenceDirectory = process.env.OBSERVABILITY_EVIDENCE_DIR ?? 'storage/logs/observability-browser-evidence';
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
    for (const path of ['/admin/operations/observability', '/admin/operations/reliability']) {
        const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle' });
        if (!response?.ok()) throw new Error(`${label}/${path}: HTTP ${response?.status()}`);
        const metrics = await page.evaluate(() => ({ innerWidth, innerHeight, clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth }));
        if (metrics.innerWidth !== width || metrics.innerHeight !== height || metrics.clientWidth !== width || metrics.scrollWidth !== metrics.clientWidth) {
            const offenders = await page.evaluate(() => [...document.querySelectorAll('*')].map(element => { const rectangle = element.getBoundingClientRect(); return { tag: element.tagName, className: typeof element.className === 'string' ? element.className : '', left: rectangle.left, right: rectangle.right, width: rectangle.width, scrollWidth: element.scrollWidth, text: element.textContent?.slice(0,120) }; }).filter(item => item.right > document.documentElement.clientWidth + 1 || item.left < -1).slice(0, 12));
            const bodyText = await page.locator('body').innerText();
            throw new Error(`${label}/${path}: ${JSON.stringify(metrics)} offenders=${JSON.stringify(offenders)} body=${bodyText.slice(0, 600)}`);
        }
    }
    if (problems.length) throw new Error(`${label}: ${problems.join(' | ')}`);
    await page.screenshot({ path: `${evidenceDirectory}/${label}.png`, fullPage: true });
    await context.close();
    return { label, width, height, documentOverflow: 0, consoleProblems: 0 };
}
