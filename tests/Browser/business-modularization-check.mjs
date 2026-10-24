import { chromium } from 'playwright-core';

for (const [key, expected] of Object.entries({ APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'schema_admin_browser_testing' })) {
    if (process.env[key] !== expected) throw new Error(`${key} must equal ${expected}`);
}

const baseUrl = process.env.BUSINESS_BASE_URL ?? 'http://127.0.0.1:18161';
const companyId = process.env.BUSINESS_COMPANY_ID;
const branchId = process.env.BUSINESS_BRANCH_ID;
const visitId = process.env.BUSINESS_VISIT_ID;
const customerId = process.env.BUSINESS_CUSTOMER_ID;
const receiptId = process.env.BUSINESS_RECEIPT_ID;
const openVisitId = process.env.BUSINESS_OPEN_VISIT_ID;
if (![companyId, branchId, visitId, customerId, receiptId].every(value => /^\d+$/.test(value ?? ''))) throw new Error('BUSINESS_COMPANY_ID, BUSINESS_BRANCH_ID, BUSINESS_VISIT_ID, BUSINESS_CUSTOMER_ID and BUSINESS_RECEIPT_ID are required');

const browser = await chromium.launch({
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: true,
});
const report = { desktop: [], mobile: [], geometry: [], consoleErrors: 0, consoleWarnings: 0, pageErrors: 0, overflow: 0, brokenMedia: 0 };

const pages = [
    ['dashboard', `/dashboard?company_id=${companyId}`, 'Nhịp vận hành doanh nghiệp'],
    ['companies', '/admin/companies', 'Công ty'],
    ['company', `/admin/companies/${companyId}`, 'Tài khoản Seller'],
    ['branches', `/admin/branches?company_id=${companyId}`, 'Chi nhánh'],
    ['branch', `/admin/branches/${branchId}?company_id=${companyId}`, 'Chi nhánh C6 Browser'],
    ['sellerGovernance', `/admin/commerce/sellers?company_id=${companyId}`, 'Seller Governance'],
    ['products', `/admin/products?company_id=${companyId}`, 'Product Catalog'],
    ['categories', `/admin/product-categories?company_id=${companyId}`, 'Product Categories'],
    ['warehouse', `/admin/branch-commerce/warehouse?company_id=${companyId}&branch_id=${branchId}`, 'Kho hàng sản phẩm theo chi nhánh'],
    ['sellerInventory', `/admin/seller/inventory?company_id=${companyId}`, 'Tồn kho Variant / SKU'],
    ['inventoryMovements', `/admin/seller/inventory/movements?company_id=${companyId}`, 'Vận hành & lịch sử kho'],
    ['posDashboard', `/admin/branch-commerce?company_id=${companyId}`, 'Tổng quan bán hàng tại quầy'],
    ['customers', `/admin/branch-commerce/customers?company_id=${companyId}`, 'Tệp khách hàng công ty'],
    ['customer', `/admin/branch-commerce/customers/${customerId}?company_id=${companyId}`, 'Khách POS C6 Browser'],
    ['services', `/admin/branch-commerce/services?company_id=${companyId}`, 'Dịch vụ công ty cha'],
    ['checkins', `/admin/branch-commerce/checkins?company_id=${companyId}`, 'Check-in khách hàng'],
    ['checkout', `/admin/branch-commerce/checkout/${visitId}?company_id=${companyId}`, 'Đã thanh toán'],
    ['sales', `/admin/branch-commerce/sales?company_id=${companyId}&branch_id=${branchId}`, 'Doanh số chi nhánh'],
    ['receipts', `/admin/branch-commerce/receipts?company_id=${companyId}&branch_id=${branchId}`, 'Lịch sử phiếu thu'],
    ['receipt', `/admin/branch-commerce/receipts/${receiptId}?company_id=${companyId}`, 'Chi tiết phiếu thu POS'],
    ['salesHistory', `/admin/commerce/sales-history?company_id=${companyId}`, 'Lịch sử đơn hàng đã bán'],
    ['finance', `/admin/finance/branch-performance?company_id=${companyId}`, 'Hiệu quả tài chính chi nhánh'],
    ['publicProducts', '/products', 'Sản phẩm'],
    ['publicProduct', '/products/c6-browser-product-a', 'Laptop Aurora C6 A'],
];
if (/^\d+$/.test(openVisitId ?? '')) pages.splice(16, 0, ['checkoutSelector', `/admin/branch-commerce/checkout/${openVisitId}?company_id=${companyId}`, 'Thêm sản phẩm từ kho']);

try {
    for (const [label, viewport] of [['desktop', { width: 1280, height: 800 }], ['mobile', { width: 390, height: 844 }]]) {
        const context = await browser.newContext({ viewport });
        const page = await context.newPage();
        page.setDefaultTimeout(10_000);
        page.on('console', message => {
            if (message.type() === 'error') report.consoleErrors++;
            if (message.type() === 'warning') report.consoleWarnings++;
        });
        page.on('pageerror', () => report.pageErrors++);
        const login = await page.goto(`${baseUrl}/admin/audit/_fixtures/login`, { waitUntil: 'domcontentloaded' });
        if (!login?.ok()) throw new Error(`fixture login HTTP ${login?.status()}`);

        for (const [name, path, expectedText] of pages) {
            const response = await page.goto(baseUrl + path, { waitUntil: 'domcontentloaded' });
            if (!response?.ok()) throw new Error(`${label}/${name}: HTTP ${response?.status()}`);
            await page.locator('main').getByText(expectedText, { exact: false }).first().waitFor();
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            if (overflow !== 0) {
                report.overflow++;
                throw new Error(`${label}/${name}: document overflow ${overflow}`);
            }
            if (path.startsWith('/admin/') && await page.locator('[data-admin-help-open]').count() !== 1) throw new Error(`${label}/${name}: contextual help missing`);
            if (name === 'publicProduct') {
                const media = page.locator('[data-gallery-main]');
                if (await media.count() !== 1 || ! await media.evaluate(image => image.complete && image.naturalWidth > 0)) throw new Error(`${label}/${name}: product media did not load`);
                if (await page.getByText('BROKEN_MEDIA', { exact: true }).count()) throw new Error(`${label}/${name}: broken media fallback rendered`);
            }
            const geometrySelector = ({ products: '.product-table-media', warehouse: '.table-avatar', sellerInventory: '.table-avatar', services: '.product-table-media', checkoutSelector: '.pos-product-preview', publicProducts: '.product-image', publicProduct: '.gallery-main' })[name];
            if (geometrySelector && await page.locator(geometrySelector).count()) {
                const mediaElement = page.locator(geometrySelector).first();
                await mediaElement.scrollIntoViewIfNeeded();
                await mediaElement.evaluate(async element => {
                    const image = element.matches('img') ? element : element.querySelector('img');
                    if (image instanceof HTMLImageElement && !image.complete) await new Promise(resolve => { image.addEventListener('load', resolve, { once: true }); image.addEventListener('error', resolve, { once: true }); });
                });
                const geometry = await mediaElement.evaluate(element => {
                    const box = element.getBoundingClientRect();
                    const image = element.matches('img') ? element : element.querySelector('img');
                    return { width: Math.round(box.width), height: Math.round(box.height), containerWidth: Math.round(element.parentElement?.getBoundingClientRect().width ?? box.width), loaded: !(image instanceof HTMLImageElement) || (image.complete && image.naturalWidth > 0) };
                });
                const compact = ['products', 'warehouse', 'sellerInventory', 'services'].includes(name);
                const selector = name === 'checkoutSelector';
                if (!geometry.loaded || (compact && (geometry.width > 80 || geometry.height > 80)) || (selector && (geometry.width > 96 || geometry.height > 96)) || geometry.width > geometry.containerWidth + 1) throw new Error(`${label}/${name}: invalid media geometry ${JSON.stringify(geometry)}`);
                report.geometry.push({ viewport: label, view: name, ...geometry });
            }
            const broken = await page.getByText('BROKEN_MEDIA', { exact: true }).count();
            report.brokenMedia += broken;
            if (broken) throw new Error(`${label}/${name}: BROKEN_MEDIA rendered`);
            report[label].push(name);
        }
        await context.close();
    }

    if (report.consoleErrors || report.consoleWarnings || report.pageErrors || report.overflow || report.brokenMedia) throw new Error(`runtime issues: ${JSON.stringify(report)}`);
    console.log(JSON.stringify({ status: 'passed', ...report }, null, 2));
} finally {
    await browser.close();
}
