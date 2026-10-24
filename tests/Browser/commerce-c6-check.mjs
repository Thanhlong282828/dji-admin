import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}
const baseUrl = process.env.COMMERCE_C6_BASE_URL ?? 'http://127.0.0.1:18160';
const chromePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const report = { engine: 'Google Chrome', desktop: {}, mobile: {}, console: 0, pageErrors: 0, overflow: 0 };

try {
    const desktop = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await guardedPage(desktop);
    await login(page);
    await ok(page, '/addresses');
    const form = page.locator('form.address-form').last();
    await form.locator('[name=label]').fill('Nhà riêng C6');
    await form.locator('[name=recipient_name]').fill('Nguyễn Buyer C6');
    await form.locator('[name=phone]').fill('0901234567');
    await form.locator('[name=province]').fill('TP. Hồ Chí Minh');
    await form.locator('[name=district]').fill('Quận 1');
    await form.locator('[name=ward]').fill('Bến Nghé');
    await form.locator('[name=address_line]').fill('123 Đường Browser C6');
    await Promise.all([page.waitForNavigation(), form.getByRole('button', { name: 'Lưu địa chỉ' }).click()]);
    await page.getByText('Nhà riêng C6').first().waitFor();
    await ok(page, '/cart');
    if (await page.getByText('Laptop Aurora C6 KEEP').count() !== 1) throw new Error('Unselected cart fixture missing');
    await Promise.all([page.waitForNavigation(), page.getByRole('link', { name: 'Tiếp tục thanh toán' }).click()]);
    if (await page.locator('.checkout-card').filter({ hasText: 'Cửa hàng C6 Alpha' }).count() !== 1) throw new Error('Alpha checkout group missing');
    if (await page.locator('.checkout-card').filter({ hasText: 'Cửa hàng C6 Beta' }).count() !== 1) throw new Error('Beta checkout group missing');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Đặt hàng' }).click()]);
    await page.getByRole('heading', { name: 'Đặt hàng thành công' }).waitFor();
    if (await page.locator('.order-success article').count() !== 2) throw new Error('Checkout did not split into two store orders');
    await Promise.all([page.waitForNavigation(), page.getByRole('main').getByRole('link', { name: 'Đơn mua' }).click()]);
    if (await page.locator('.order-card').count() !== 2) throw new Error('Buyer order history count mismatch');
    await Promise.all([page.waitForNavigation(), page.getByRole('link', { name: 'Xem đơn' }).first().click()]);
    await page.getByText('Tiến trình').waitFor();
    await ok(page, '/cart');
    if (await page.getByText('Laptop Aurora C6 KEEP').count() !== 1) throw new Error('Unselected cart item was removed');
    await ok(page, '/admin/seller/orders');
    if (await page.locator('main table tbody tr').count() !== 2) throw new Error('Seller order list count mismatch');
    await Promise.all([page.waitForNavigation(), page.getByRole('link', { name: 'Xem' }).first().click()]);
    await page.getByText('Giao hàng').waitFor();
    report.desktop = { address: 'PASS', checkoutGroups: 2, orders: 2, seller: 'PASS', unselectedCartPreserved: 'PASS' };
    await desktop.close();

    const mobile = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const mobilePage = await guardedPage(mobile);
    await login(mobilePage);
    for (const path of ['/addresses', '/cart', '/orders', '/admin/seller/orders']) await ok(mobilePage, path);
    await ok(mobilePage, '/orders');
    await Promise.all([mobilePage.waitForNavigation(), mobilePage.getByRole('link', { name: 'Xem đơn' }).first().click()]);
    await mobilePage.getByText('Tiến trình').waitFor();
    report.mobile = { viewport: '390x844', address: 'PASS', cart: 'PASS', orders: 'PASS', seller: 'PASS' };
    await mobile.close();
    console.log(JSON.stringify({ status: 'passed', ...report }, null, 2));
} finally {
    await browser.close();
}

async function guardedPage(context) {
    const page = await context.newPage();
    page.on('console', message => {
        if (['warning', 'error'].includes(message.type())) throw new Error(`console.${message.type()}: ${message.text()}`);
    });
    page.on('pageerror', error => { throw error; });
    return page;
}

async function login(page) {
    const response = await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'networkidle' });
    if (!response?.ok()) throw new Error(`fixture login HTTP ${response?.status()}`);
}

async function ok(page, path) {
    const response = await page.goto(baseUrl + path, { waitUntil: 'networkidle' });
    if (!response?.ok()) throw new Error(`${path}: HTTP ${response?.status()}`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow !== 0) throw new Error(`${path}: document overflow ${overflow}`);
}
