document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href="backup.php"]');
    if (!link || link.dataset.backupConfirmed === 'true') return;
    event.preventDefault();
    const proceed = () => {
        link.dataset.backupConfirmed = 'true';
        window.location.assign(link.href);
    };
    if (!window.Swal) {
        if (window.confirm('سيتم تنزيل نسخة JSON من بيانات التطبيق. هل تريد المتابعة؟')) proceed();
        return;
    }
    window.Swal.fire({
        title: 'تنزيل نسخة احتياطية',
        html: '<p>سيُنشئ النظام ملف JSON يحتوي بيانات التطبيق المصرح بها.</p><small>احتفظ بالملف في مكان آمن ولا تشاركه خارج الجهة المخولة.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'تنزيل النسخة',
        cancelButtonText: 'إلغاء',
        reverseButtons: true,
        confirmButtonColor: '#17614f',
    }).then((result) => { if (result.isConfirmed) proceed(); });
});
