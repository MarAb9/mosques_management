export function initConfirmations() {
    const logoutButton = document.getElementById('logoutButton');
    const logoutForm = document.getElementById('logoutForm');

    logoutButton?.addEventListener('click', async () => {
        if (!logoutForm) return;
        if (window.Swal) {
            const result = await window.Swal.fire({
                title: 'تأكيد تسجيل الخروج',
                text: 'هل تريد إنهاء الجلسة الحالية؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'تسجيل الخروج',
                cancelButtonText: 'البقاء',
                reverseButtons: true,
                confirmButtonColor: '#a83e3e',
                cancelButtonColor: '#51605a',
            });
            if (result.isConfirmed) logoutForm.submit();
        } else if (window.confirm('هل تريد تسجيل الخروج؟')) {
            logoutForm.submit();
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('.js-confirm-submit');
        if (!form || form.dataset.confirmed === 'true') return;
        event.preventDefault();
        const message = form.getAttribute('data-confirm') || 'هل تريد المتابعة؟';
        if (window.Swal) {
            window.Swal.fire({
                title: 'تأكيد العملية',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'تأكيد',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
                confirmButtonColor: '#a83e3e',
            }).then((result) => {
                if (result.isConfirmed) { form.dataset.confirmed = 'true'; form.requestSubmit(); }
            });
        } else if (window.confirm(message)) {
            form.dataset.confirmed = 'true';
            form.requestSubmit();
        }
    });
}

export function initTooltips(root = document) {
    if (!window.bootstrap?.Tooltip) return;
    root.querySelectorAll('[data-bs-toggle="tooltip"], [data-bs-tooltip="tooltip"]').forEach((element) => {
        window.bootstrap.Tooltip.getOrCreateInstance(element);
    });
}
