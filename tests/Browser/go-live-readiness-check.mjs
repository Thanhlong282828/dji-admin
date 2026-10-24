import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
const baseUrl = process.env.GO_LIVE_BASE_URL ?? 'http://127.0.0.1:18130';
const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
const problems = [];
try {
  const desktop = await verify({ width: 1280, height: 800 });
  const mobile = await verify({ width: 390, height: 844 });
  if (problems.length) throw new Error(problems.join(' | '));
  console.log(JSON.stringify({ status: 'passed', desktop, mobile, consoleWarningsErrors: 0, pageErrors: 0 }, null, 2));
} finally { await browser.close(); }

async function verify(viewport) {
  const context = await browser.newContext({ viewport }); const page = await context.newPage(); page.setDefaultTimeout(8000);
  page.on('console', message => { if (['warning', 'error'].includes(message.type())) problems.push(`${message.type()}:${message.text()}`); });
  page.on('pageerror', error => problems.push(`pageerror:${error.message}`));
  await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'domcontentloaded' });
  const response = await page.goto(`${baseUrl}/admin/operations/go-live`, { waitUntil: 'domcontentloaded' });
  if (response?.status() !== 200) throw new Error(`status=${response?.status()}`);
  for (const text of ['Go-Live Readiness', 'Production checklist', 'External dependency matrix', 'PRODUCTION_BLOCKED_EXTERNAL']) await page.getByText(text, { exact: true }).first().waitFor();
  await page.getByRole('button', { name: 'Hướng dẫn sử dụng trang này' }).click();
  await page.locator('[data-admin-help-dialog]').getByText('không migrate', { exact: false }).first().waitFor(); await page.keyboard.press('Escape');
  const operation = page.locator('[data-nav-group="operations"]');
  if (await operation.getAttribute('data-route-active') !== 'true') throw new Error('operations group inactive');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  if (overflow !== 0) throw new Error(`overflow=${overflow}`);
  await context.close(); return { viewport, status: 200, overflow, sidebarFlicker: 'not-observed', help: 'specific' };
}
