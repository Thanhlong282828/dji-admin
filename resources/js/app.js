

import Alpine from 'alpinejs';
import '../ts/realtime/recruitment.ts';
import '../ts/realtime/workforce.ts';
import '../ts/schema.ts';
import '../ts/notification-center.ts';
import '../ts/admin-navigation.ts';
import '../ts/forecasting.ts';

window.Alpine = Alpine;

Alpine.start();

document.querySelectorAll('[data-company-branches]').forEach((section) => {
    const rows = section.querySelector('[data-company-branch-rows]');
    const template = section.querySelector('[data-company-branch-template]');
    const add = section.querySelector('[data-add-company-branch]');
    if (!(rows instanceof HTMLElement) || !(template instanceof HTMLTemplateElement) || !(add instanceof HTMLButtonElement)) return;
    const bindRemove = (root) => root.querySelectorAll('[data-remove-company-branch]').forEach((button) => button.addEventListener('click', () => button.closest('[data-company-branch-row]')?.remove(), { once: true }));
    bindRemove(rows);
    add.addEventListener('click', () => {
        const index = rows.querySelectorAll('[data-company-branch-row]').length;
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[data-name]').forEach((field) => field.setAttribute('name', `branches[${index}][${field.getAttribute('data-name')}]`));
        bindRemove(fragment);
        rows.append(fragment);
    });
});

document.querySelectorAll('[data-product-variants]').forEach((section) => {
    const rows = section.querySelector('[data-product-variant-rows]');
    const template = section.querySelector('[data-product-variant-template]');
    const add = section.querySelector('[data-add-product-variant]');
    if (!(rows instanceof HTMLElement) || !(template instanceof HTMLTemplateElement) || !(add instanceof HTMLButtonElement)) return;
    const bindRemove = (root) => root.querySelectorAll('[data-remove-product-variant]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement) || button.dataset.bound === 'true') return;
        button.dataset.bound = 'true';
        button.addEventListener('click', () => {
            if (rows.querySelectorAll('[data-product-variant-row]').length > 1) button.closest('[data-product-variant-row]')?.remove();
        });
    });
    bindRemove(rows);
    add.addEventListener('click', () => {
        if (rows.querySelectorAll('[data-product-variant-row]').length >= 50) return;
        const index = rows.querySelectorAll('[data-product-variant-row]').length;
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[data-name]').forEach((field) => field.setAttribute('name', `variants[${index}][${field.getAttribute('data-name')}]`));
        bindRemove(fragment);
        rows.append(fragment);
    });
});

document.querySelectorAll('[data-media-input]').forEach((input) => {
    if (!(input instanceof HTMLInputElement) || input.dataset.mediaPreviewBound === 'true') return;
    input.dataset.mediaPreviewBound = 'true';
    let objectUrls = [];
    input.addEventListener('change', () => {
        objectUrls.forEach((url) => URL.revokeObjectURL(url));
        objectUrls = [];
        const target = document.getElementById(input.dataset.previewTarget ?? '');
        if (!target) return;
        const files = Array.from(input.files ?? []).filter((file) => ['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
        if (target instanceof HTMLImageElement) {
            target.parentElement?.querySelector('[data-local-media-meta]')?.remove();
            target.parentElement?.querySelector('[data-local-media-remove]')?.remove();
            if (files[0]) {
                const file = files[0]; const url = URL.createObjectURL(file); objectUrls.push(url); target.src = url; target.hidden = false;
                const meta = document.createElement('small'); meta.dataset.localMediaMeta = 'true'; meta.textContent = `${file.name} · ${(file.size / 1048576).toFixed(2)} MB`; target.insertAdjacentElement('afterend', meta);
                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'secondary-button'; remove.dataset.localMediaRemove = 'true'; remove.textContent = 'Gỡ ảnh đã chọn'; remove.addEventListener('click', () => { input.value = ''; input.dispatchEvent(new Event('change')); }); meta.insertAdjacentElement('afterend', remove);
                target.addEventListener('load', () => { meta.textContent = `${file.name} · ${target.naturalWidth}×${target.naturalHeight} · ${(file.size / 1048576).toFixed(2)} MB`; }, { once: true });
            } else {
                target.removeAttribute('src'); target.hidden = true;
            }
            return;
        }
        target.replaceChildren(...files.map((file, index) => {
            const tile = document.createElement('span'); tile.className = 'media-tile';
            const image = document.createElement('img'); const meta = document.createElement('small');
            const url = URL.createObjectURL(file); objectUrls.push(url); image.src = url; image.alt = `Xem trước ${file.name}`; image.className = 'media-preview'; meta.textContent = `${file.name} · ${(file.size / 1048576).toFixed(2)} MB`;
            image.addEventListener('load', () => { meta.textContent = `${file.name} · ${image.naturalWidth}×${image.naturalHeight} · ${(file.size / 1048576).toFixed(2)} MB`; }, { once: true });
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button secondary'; remove.textContent = 'Gỡ'; remove.addEventListener('click', () => { const transfer = new DataTransfer(); Array.from(input.files ?? []).forEach((candidate, candidateIndex) => { if (candidateIndex !== index) transfer.items.add(candidate); }); input.files = transfer.files; input.dispatchEvent(new Event('change')); });
            tile.append(image, meta, remove); return tile;
        }));
    });
    window.addEventListener('pagehide', () => objectUrls.forEach((url) => URL.revokeObjectURL(url)), { once: true });
});

document.querySelectorAll('[data-gallery-thumb]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) return;
    button.addEventListener('click', () => {
        const main = document.querySelector('[data-gallery-main]');
        if (!(main instanceof HTMLImageElement) || !button.dataset.gallerySrc) return;
        main.src = button.dataset.gallerySrc;
        document.querySelectorAll('[data-gallery-thumb]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
    });
});

document.querySelectorAll('[data-commerce-quantity], [data-cart-quantity]').forEach((control) => {
    const input = control.querySelector('input[type="number"]');
    if (!(input instanceof HTMLInputElement)) return;
    control.querySelectorAll('[data-quantity-delta]').forEach((button) => button.addEventListener('click', () => {
        const delta = Number(button.getAttribute('data-quantity-delta'));
        const minimum = Number(input.min || 1);
        const maximum = Number(input.max || 99);
        input.value = String(Math.min(maximum, Math.max(minimum, Number(input.value || minimum) + delta)));
    }));
    control.querySelector('[data-qty-minus]')?.addEventListener('click', () => {
        input.value = String(Math.max(Number(input.min || 1), Number(input.value || 1) - 1));
    });
    control.querySelector('[data-qty-plus]')?.addEventListener('click', () => {
        input.value = String(Math.min(Number(input.max || 99), Number(input.value || 1) + 1));
    });
});

document.querySelectorAll('[data-product-purchase]').forEach((panel) => {
    const selector = panel.querySelector('[data-variant-selector]');
    const price = panel.querySelector('[data-variant-price]');
    const stock = panel.querySelector('[data-variant-stock]');
    const quantity = panel.querySelector('input[name="quantity"]');
    const submit = panel.querySelector('button[type="submit"]');
    if (!(selector instanceof HTMLSelectElement)) return;
    const synchronize = () => {
        const option = selector.selectedOptions[0];
        if (!option) return;
        const available = Number(option.dataset.available || 0);
        if (price) price.textContent = option.dataset.priceLabel || '';
        if (stock) stock.textContent = available > 0 ? `Còn ${available} sản phẩm` : 'Hết hàng';
        if (quantity instanceof HTMLInputElement) {
            quantity.max = String(Math.max(1, available));
            quantity.value = String(Math.min(Math.max(1, Number(quantity.value || 1)), Math.max(1, available)));
        }
        if (submit instanceof HTMLButtonElement) submit.disabled = available < 1 || option.dataset.active !== 'true';
    };
    selector.addEventListener('change', synchronize);
    synchronize();
});
