export function initAccessibility() {
    document.querySelectorAll('img:not([alt])').forEach((image) => image.setAttribute('alt', ''));

    const main = document.getElementById('main-content');
    document.querySelector('.skip-link')?.addEventListener('click', () => {
        requestAnimationFrame(() => main?.focus({ preventScroll: true }));
    });

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.getAttribute('data-copy') || '';
            try {
                await navigator.clipboard.writeText(value);
                button.setAttribute('data-copy-state', 'done');
                setTimeout(() => button.removeAttribute('data-copy-state'), 1600);
            } catch (_) {}
        });
    });
}
