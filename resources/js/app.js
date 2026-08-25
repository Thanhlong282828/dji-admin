

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
            if (files[0]) { const url = URL.createObjectURL(files[0]); objectUrls.push(url); target.src = url; target.hidden = false; }
            return;
        }
        target.replaceChildren(...files.map((file) => {
            const image = document.createElement('img');
            const url = URL.createObjectURL(file); objectUrls.push(url); image.src = url; image.alt = `Xem trước ${file.name}`; image.className = 'media-preview'; return image;
        }));
    });
    window.addEventListener('pagehide', () => objectUrls.forEach((url) => URL.revokeObjectURL(url)), { once: true });
});
