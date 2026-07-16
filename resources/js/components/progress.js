export function initProgress(root = document) {
    root.querySelectorAll('[data-progress]').forEach((meter) => {
        const value = Math.min(100, Math.max(0, Number(meter.getAttribute('data-progress')) || 0));
        meter.setAttribute('aria-valuenow', String(value));
        meter.setAttribute('data-progress', String(value));

        if (typeof HTMLProgressElement !== 'undefined' && meter instanceof HTMLProgressElement) {
            meter.max = 100;
            meter.value = value;
        }
    });

    root.querySelectorAll('[data-count-up]').forEach((element) => {
        const target = Number(element.getAttribute('data-count-up')) || 0;
        const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduced || target <= 0) { element.textContent = target.toLocaleString('ar-MA'); return; }
        const startedAt = performance.now();
        const duration = 650;
        const update = (now) => {
            const progress = Math.min(1, (now - startedAt) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = Math.round(target * eased).toLocaleString('ar-MA');
            if (progress < 1) requestAnimationFrame(update);
        };
        requestAnimationFrame(update);
    });
}
