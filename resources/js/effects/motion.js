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
}
