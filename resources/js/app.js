

import Alpine from 'alpinejs';
import '../ts/realtime/recruitment.ts';
import '../ts/realtime/workforce.ts';
import '../ts/schema.ts';
import '../ts/notification-center.ts';
import '../ts/admin-navigation.ts';
import '../ts/forecasting.ts';

window.Alpine = Alpine;

Alpine.start();

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
            if (files[0]) {
                const file = files[0]; const url = URL.createObjectURL(file); objectUrls.push(url); target.src = url; target.hidden = false;
                const meta = document.createElement('small'); meta.dataset.localMediaMeta = 'true'; meta.textContent = `${file.name} · ${(file.size / 1048576).toFixed(2)} MB`; target.insertAdjacentElement('afterend', meta);
                target.addEventListener('load', () => { meta.textContent = `${file.name} · ${target.naturalWidth}×${target.naturalHeight} · ${(file.size / 1048576).toFixed(2)} MB`; }, { once: true });
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
