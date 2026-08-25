import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}
const baseUrl = process.env.COMMERCE_BASE_URL ?? 'http://127.0.0.1:18140';
const companyId = process.env.COMMERCE_COMPANY_ID;
if (!companyId || !/^\d+$/.test(companyId)) throw new Error('COMMERCE_COMPANY_ID is required');
const image = { name: 'commerce.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFElEQVR42mNk+M/AwMDAxMDAwMAAAAwAAf6nB0QAAAAASUVORK5CYII=', 'base64') };
const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });

try {
    const results = [];
    for (const viewport of [{ width: 1280, height: 800 }, { width: 390, height: 844 }]) results.push(await run(viewport));
    console.log(JSON.stringify({ status: 'passed', results }, null, 2));
} finally { await browser.close(); }

async function run(viewport) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    const consoleProblems = []; const pageErrors = [];
    page.on('console', m => { if (['warning', 'error'].includes(m.type())) consoleProblems.push(m.text()); });
    page.on('pageerror', e => pageErrors.push(e.message));
    await page.goto(`${baseUrl}/admin/audit/_fixtures/login`);

    await page.goto(`${baseUrl}/admin/branches/create?company_id=${companyId}`);
    const suffix = `${viewport.width}-${Date.now()}`;
    await page.locator('[name=code]').fill(`E2E-${suffix}`);
    await page.locator('[name=name]').fill(`Chi nhánh E2E ${suffix}`);
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Lưu' }).click()]);
    await page.getByText(`Chi nhánh E2E ${suffix}`).waitFor();

    await page.goto(`${baseUrl}/articles/create?company_id=${companyId}`);
    await page.locator('[name=title]').fill(`Commerce media ${suffix}`);
    await page.locator('[name=excerpt]').fill('Bài viết kiểm thử browser cho unified media.');
    await page.locator('[name=body]').fill('Nội dung kiểm thử giao dịch media doanh nghiệp. '.repeat(5));
    await page.locator('[name=status]').selectOption('published');
    await page.locator('[name=published_at]').fill('2026-08-24T10:00');
    await page.locator('[name=cover_image]').setInputFiles(image);
    await page.locator('[name="gallery_images[]"]').setInputFiles([image, { ...image, name: 'gallery.png' }]);
    const previewSrc = await page.locator('#cover-preview').getAttribute('src');
    if (!previewSrc?.startsWith('blob:')) throw new Error('cover preview is not a blob URL');
    if (await page.locator('#gallery-preview img').count() !== 2) throw new Error('gallery preview count mismatch');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Lưu bài viết' }).click()]);
    const articleListText = await page.locator('body').innerText();
    if (!articleListText.includes(`Commerce media ${suffix}`)) throw new Error(`article redirect mismatch url=${page.url()} body=${articleListText.slice(0, 500)}`);

    await page.goto(`${baseUrl}/admin/seller/store`);
    await page.locator('[name=name]').fill(`Cửa hàng E2E ${suffix}`);
    await page.locator('[name=description]').fill('Store fixture thuộc chính Seller browser.');
    await page.locator('[name=logo]').setInputFiles(image);
    await page.locator('[name=banner]').setInputFiles(image);
    if (!(await page.locator('#logo-preview').getAttribute('src'))?.startsWith('blob:')) throw new Error('store preview missing');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Lưu cửa hàng' }).click()]);
    await page.goto(`${baseUrl}/stores`);
    await page.getByText(`Cửa hàng E2E ${suffix}`).waitFor();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow || consoleProblems.length || pageErrors.length) throw new Error(JSON.stringify({ overflow, consoleProblems, pageErrors }));
    await context.close();
    return { viewport, branch: 'PASS', articlePreview: 'PASS', gallery: 'PASS', storeMedia: 'PASS', publicStore: 'PASS', overflow, console: 0, pageErrors: 0 };
}
