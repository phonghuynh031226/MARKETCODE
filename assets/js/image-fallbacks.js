/* Preserve layout when an uploaded image or external image host is unavailable. */
(() => {
    'use strict';
    const source = document.currentScript?.src;
    if (!source) return;
    const avatar = new URL('../images/default-avatar.svg', source).href;
    const thumbnail = new URL('../images/default-thumbnail.svg', source).href;
    const attach = image => {
        if (!(image instanceof HTMLImageElement) || image.dataset.fallbackReady) return;
        image.dataset.fallbackReady = 'true';
        const fallback = /avatar|đại diện/i.test(image.alt) ? avatar : thumbnail;
        const replace = () => {
            if (image.src !== fallback) image.src = fallback;
        };
        image.addEventListener('error', replace, { once: true });
        if (image.complete && image.naturalWidth === 0 && image.getAttribute('src')) replace();
    };
    document.querySelectorAll('img').forEach(attach);
    new MutationObserver(records => {
        for (const record of records) for (const node of record.addedNodes) {
            if (!(node instanceof Element)) continue;
            if (node.matches('img')) attach(node);
            node.querySelectorAll('img').forEach(attach);
        }
    }).observe(document.body, { childList: true, subtree: true });
})();
