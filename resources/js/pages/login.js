document.addEventListener('DOMContentLoaded', () => {
    const password = document.getElementById('password');
    const toggle = document.getElementById('passwordToggle');
    toggle?.addEventListener('click', () => {
        if (!password) return;
        const visible = password.type === 'text';
        password.type = visible ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', String(!visible));
        toggle.setAttribute('aria-label', visible ? 'إظهار كلمة المرور' : 'إخفاء كلمة المرور');
        toggle.querySelector('i')?.classList.toggle('fa-eye-slash', !visible);
        toggle.querySelector('i')?.classList.toggle('fa-eye', visible);
    });
    document.querySelector('input[autofocus], #username')?.focus();
});
