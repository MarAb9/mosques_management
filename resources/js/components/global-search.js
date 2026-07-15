const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const uniqueRows = (rows) => {
    const seen = new Set();
    return rows.filter((row) => {
        const key = String(row.registration_number ?? row.national_code ?? row.mosque_name ?? '');
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
};

export function initGlobalSearch() {
    const trigger = document.getElementById('globalSearchToggle');
    const dialog = document.getElementById('globalSearchDialog');
    const closeButton = document.getElementById('globalSearchClose');
    const form = document.getElementById('globalSearchForm');
    const input = document.getElementById('globalSearchInput');
    const results = document.getElementById('globalSearchResults');
    const status = document.getElementById('globalSearchStatus');
    if (!trigger || !(dialog instanceof HTMLDialogElement) || !form || !input || !results || !status) return;

    let restoreFocus = null;
    let timer = null;
    let requestController = null;

    const resetResults = () => {
        results.replaceChildren();
        status.textContent = 'اكتب حرفين على الأقل لعرض النتائج.';
    };

    const close = () => {
        requestController?.abort();
        if (dialog.open) dialog.close();
        trigger.setAttribute('aria-expanded', 'false');
    };

    const open = () => {
        restoreFocus = document.activeElement;
        if (!dialog.open) dialog.showModal();
        trigger.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(() => input.focus());
    };

    const renderRows = (rows, query) => {
        results.replaceChildren();
        if (!rows.length) {
            status.textContent = 'لا توجد نتائج مطابقة.';
            return;
        }

        status.textContent = `${rows.length} نتائج مقترحة.`;
        rows.slice(0, 6).forEach((row) => {
            const item = document.createElement('li');
            const href = `mosques.php?query=${encodeURIComponent(row.mosque_name || query)}`;
            item.innerHTML = `
                <a class="global-search__result" href="${href}">
                    <span class="global-search__result-icon" aria-hidden="true"><i class="fas fa-mosque"></i></span>
                    <span><strong>${escapeHtml(row.mosque_name || 'مسجد')}</strong><small>${escapeHtml([row.national_code, row.community, row.address].filter(Boolean).join(' · '))}</small></span>
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </a>`;
            results.append(item);
        });
    };

    const fetchResults = async (query) => {
        requestController?.abort();
        requestController = new AbortController();
        status.textContent = 'جارٍ البحث…';
        results.replaceChildren();

        const searchUrl = new URL('ajax/search_mosques.php', document.baseURI);
        searchUrl.searchParams.set('q', query);
        searchUrl.searchParams.set('page', '1');
        const communityUrl = new URL(searchUrl);
        communityUrl.searchParams.set('q', '');
        communityUrl.searchParams.set('community', query);

        try {
            const responses = await Promise.all([
                fetch(searchUrl, { headers: { Accept: 'application/json' }, signal: requestController.signal }),
                fetch(communityUrl, { headers: { Accept: 'application/json' }, signal: requestController.signal }),
            ]);
            if (responses.some((response) => !response.ok)) throw new Error('Search request failed');
            const payloads = await Promise.all(responses.map((response) => response.json()));
            const rows = uniqueRows(payloads.flatMap((payload) => payload.success && Array.isArray(payload.data) ? payload.data : []));
            renderRows(rows, query);
        } catch (error) {
            if (error.name === 'AbortError') return;
            status.textContent = 'تعذر إتمام البحث. أعد المحاولة.';
        }
    };

    trigger.addEventListener('click', open);
    closeButton?.addEventListener('click', close);
    dialog.addEventListener('close', () => {
        trigger.setAttribute('aria-expanded', 'false');
        input.value = '';
        resetResults();
        if (restoreFocus instanceof HTMLElement) restoreFocus.focus();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...dialog.querySelectorAll(focusableSelector)].filter((element) => !element.hidden);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        const query = input.value.trim();
        if (query.length < 2) {
            requestController?.abort();
            resetResults();
            return;
        }
        timer = window.setTimeout(() => fetchResults(query), 220);
    });
    form.addEventListener('submit', (event) => {
        if (!input.value.trim()) event.preventDefault();
    });
}
