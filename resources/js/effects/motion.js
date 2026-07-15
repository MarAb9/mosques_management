export function initMotion() {
    const revealItems = document.querySelectorAll('.reveal, .scroll-animate');
    if (!revealItems.length) return;
    if (matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        revealItems.forEach((item) => item.classList.add('is-visible', 'animated'));
        return;
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible', 'animated');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -5% 0px', threshold: .08 });
    revealItems.forEach((item) => observer.observe(item));

    if (!matchMedia('(pointer: fine)').matches) return;
    document.querySelectorAll('[data-depth-card]').forEach((card) => {
        card.addEventListener('pointermove', (event) => {
            const rect = card.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width - .5) * 2;
            const y = ((event.clientY - rect.top) / rect.height - .5) * 2;
            card.style.transform = `perspective(800px) rotateX(${-y * 1.7}deg) rotateY(${x * 1.7}deg) translateY(-2px)`;
        });
        card.addEventListener('pointerleave', () => card.style.removeProperty('transform'));
    });
}
