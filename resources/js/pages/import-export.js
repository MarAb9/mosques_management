document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('import_file');
    const zone = document.querySelector('[data-upload-zone]');
    const label = document.querySelector('[data-upload-name]');
    if (!input || !zone) return;

    const update = () => {
        const file = input.files?.[0];
        if (label) label.textContent = file ? file.name : 'اختر ملف Excel أو اسحبه إلى هنا';
        zone.classList.toggle('has-file', Boolean(file));
    };
    input.addEventListener('change', update);
    ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (event) => { event.preventDefault(); zone.classList.add('is-dragging'); }));
    ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, (event) => { event.preventDefault(); zone.classList.remove('is-dragging'); }));
    zone.addEventListener('drop', (event) => {
        if (!event.dataTransfer?.files.length) return;
        const transfer = new DataTransfer();
        transfer.items.add(event.dataTransfer.files[0]);
        input.files = transfer.files;
        update();
    });
});
