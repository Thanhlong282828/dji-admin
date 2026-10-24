import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}
const baseUrl = process.env.COMMERCE_C5_BASE_URL ?? 'http://127.0.0.1:18150';
const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });

try {
    const results = [];
    for (const viewport of [{ width: 1280, height: 800 }, { width: 390, height: 844 }]) results.push(await verify(viewport));
    console.log(JSON.stringify({ status: 'passed', engine: 'Google Chrome', results }, null, 2));
} finally {
    await browser.close();
}

async function verify(viewport) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    const consoleProblems = []; const pageErrors = [];
    page.on('console', message => { if (['warning', 'error'].includes(message.type())) consoleProblems.push(message.text()); });
    page.on('pageerror', error => pageErrors.push(error.message));
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    const routes = ['/products?q=Aurora', '/stores/c5-browser-store', '/products/c5-aurora-pro', '/cart', '/wishlist', '/admin/products', '/admin/product-categories', '/admin/seller/inventory'];
    const statuses = {};
    for (const route of routes) {
        const response = await page.goto(baseUrl + route, { waitUntil: 'networkidle' });
        statuses[route] = response?.status();
        if (!response?.ok()) throw new Error(`${route}: HTTP ${response?.status()}`);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (overflow !== 0) throw new Error(`${route}: document overflow ${overflow}`);
    }
    await page.goto(`${baseUrl}/products/c5-aurora-pro`, { waitUntil: 'networkidle' });
    await page.locator('[data-variant-selector]').selectOption({ index: 1 });
    const price = await page.locator('[data-variant-price]').innerText();
    const stock = await page.locator('[data-variant-stock]').innerText();
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Thêm vào giỏ' }).click()]);
    await page.goto(`${baseUrl}/cart`, { waitUntil: 'networkidle' });
    if (!await page.getByText('Laptop Aurora Pro 14').count()) throw new Error('Cart item missing');
    await page.goto(`${baseUrl}/products/c5-aurora-pro`, { waitUntil: 'networkidle' });
    if ((await page.locator('.wishlist-detail').getAttribute('aria-pressed')) !== 'true') {
        await Promise.all([page.waitForNavigation(), page.locator('.wishlist-detail').click()]);
    }
    await page.goto(`${baseUrl}/wishlist`, { waitUntil: 'networkidle' });
    if (!await page.getByText('Laptop Aurora Pro 14').count()) throw new Error('Wishlist item missing');
    if (consoleProblems.length || pageErrors.length) throw new Error(JSON.stringify({ consoleProblems, pageErrors }));
    await context.close();
    return { viewport, statuses, variantPrice: price, variantStock: stock, cart: 'PASS', wishlist: 'PASS', overflow: 0, console: 0, pageErrors: 0 };
}
