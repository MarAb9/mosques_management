// Global variables
let lastRequestedMosqueId = null;
let mosqueDetailsAbortController = null;
let statusChart = null;
let fridayChart = null;
let communityChart = null;

// Initialize everything when DOM is loaded
// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('searchForm')) {
        return;
    }

    initializeTooltips();
    setupMosqueDetailsModal();
    setupBulkSelection();
    setupSearch();
    setupDelegatedActions();
    setupListUxControls();

    if (typeof Chart !== 'undefined') {
        setupQuickStats();
    } else {
        console.error('Chart.js not loaded!');
    }

    // Initialize Select2 with null check
    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 === 'function') {
        $('.select2').select2({
            width: '100%'
        });
    } else {
        console.error('Select2 not loaded!');
    }

    // Live search setup with null check
    const liveSearch = document.getElementById('liveSearch');
    if (liveSearch) {
        let searchTimeout;

        liveSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.trim();

            if (searchTerm.length === 0) {
                // Restore the complete result set through AJAX. Submitting the
                // form here caused a full page refresh whenever the user
                // deleted the search text.
                searchTimeout = setTimeout(() => fetchLiveSearchResults(''), 300);
                return;
            }

            showLoadingIndicator();
            searchTimeout = setTimeout(() => fetchLiveSearchResults(searchTerm), 500);
        });
    }

    // Form submission handler with null check
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', handleSearchSubmit);
    }

    // Community filter change event with null checks
    const communityFilter = document.querySelector('select[name="community"]');
    const statusFilter = document.querySelector('select[name="status"]');
    const fridayFilter = document.querySelector('select[name="friday_prayer"]');
    const guideImamFilter = document.querySelector('select[name="guide_imam"]');

    [communityFilter, statusFilter, fridayFilter, guideImamFilter].forEach(filter => {
        if (filter) {
            filter.addEventListener('change', function() {
                document.getElementById('filterChangedHint')?.classList.remove('d-none');
            });
        }
    });
});


function setupDelegatedActions() {
    if (document.documentElement.dataset.mosqueDelegatedActionsInitialized === 'true') {
        return;
    }
    document.documentElement.dataset.mosqueDelegatedActionsInitialized = 'true';

    document.addEventListener('submit', function(event) {
        const form = event.target.closest('.js-confirm-submit');
        if (!form) return;

        const message = form.dataset.confirm || 'هل أنت متأكد من الحذف؟';
        if (!confirm(message)) {
            event.preventDefault();
        }
    });

    document.addEventListener('click', function(event) {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const printButton = target.closest('.js-print-mosque-details');
        if (printButton) {
            event.preventDefault();
            printMosqueDetails();
            return;
        }

        const retrySearchButton = target.closest('.js-retry-live-search');
        if (retrySearchButton) {
            event.preventDefault();
            retryLiveSearch();
            return;
        }

        const pageLink = target.closest('[data-live-search-page]');
        if (pageLink) {
            event.preventDefault();
            const page = Number.parseInt(pageLink.dataset.liveSearchPage || '', 10);
            if (Number.isFinite(page) && page > 0) {
                loadLiveSearchPage(page);
            }
            return;
        }

        const retryDetailsButton = target.closest('.js-retry-last-request');
        if (retryDetailsButton) {
            event.preventDefault();
            retryLastRequest();
            return;
        }

        const tabButton = target.closest('[data-tab-title]');
        if (tabButton) {
            updateTabTitle(tabButton.dataset.tabTitle || '');
        }
    });
}


function setupListUxControls() {
    const body = document.body;
    const densityToggle = document.getElementById('densityToggle');
    const compact = localStorage.getItem('mosques.tableDensity') === 'compact';
    body.classList.toggle('table-density-compact', compact);
    densityToggle?.setAttribute('aria-pressed', compact ? 'true' : 'false');

    if (densityToggle && densityToggle.dataset.densityInitialized !== 'true') {
        densityToggle.dataset.densityInitialized = 'true';
        densityToggle.addEventListener('click', function() {
            body.classList.toggle('table-density-compact');
            const isCompact = body.classList.contains('table-density-compact');
            localStorage.setItem('mosques.tableDensity', isCompact ? 'compact' : 'comfortable');
            densityToggle.setAttribute('aria-pressed', isCompact ? 'true' : 'false');
        });
    }

    document.querySelectorAll('.js-column-toggle').forEach(input => {
        if (input.dataset.columnToggleInitialized === 'true') return;
        input.dataset.columnToggleInitialized = 'true';
        input.addEventListener('change', function() {
            const columnName = String(input.value || '');
            localStorage.setItem(`mosques.column.${columnName}`, input.checked ? 'visible' : 'hidden');
            applyColumnVisibility(columnName, input.checked);
        });
    });

    applySavedColumnVisibility();
}

function applyColumnVisibility(columnName, visible) {
    if (!columnName) return;
    document.querySelectorAll('.app-table [data-column]').forEach(cell => {
        if (cell.dataset.column === columnName) {
            cell.classList.toggle('column-hidden', !visible);
        }
    });
}

function applySavedColumnVisibility() {
    document.querySelectorAll('.js-column-toggle').forEach(input => {
        const columnName = String(input.value || '');
        const saved = localStorage.getItem(`mosques.column.${columnName}`);
        const visible = saved === 'visible' || (saved !== 'hidden' && input.checked);
        input.checked = visible;
        applyColumnVisibility(columnName, visible);
    });
}

function getDirectoryColumnCount() {
    return document.querySelectorAll('.directory-table thead th').length || 1;
}

// Quick stats modal
function setupQuickStats() {
    const quickStatsButton = document.getElementById('quickStatsButton');
    const quickStatsModal = document.getElementById('quickStatsModal');
    if (!quickStatsButton || !quickStatsModal || quickStatsModal.dataset.statsInitialized === 'true') {
        return;
    }
    quickStatsModal.dataset.statsInitialized = 'true';

    quickStatsModal.addEventListener('shown.bs.modal', function() {
        fetchStatsData();
    });
}

function fetchStatsData() {
    // Show loading state
    document.getElementById('statusChart').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    document.getElementById('fridayChart').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    document.getElementById('communityChart').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';

    // Fetch data from server
    fetch('ajax/get_mosque_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                initStatusChart(data.statusStats);
                initFridayChart(data.fridayStats);
                initCommunityChart(data.communityStats);
            } else {
                showStatsError(data.message || 'حدث خطأ في جلب البيانات');
            }
        })
        .catch(error => {
            console.error('Error fetching stats:', error);
            showStatsError('حدث خطأ في الاتصال بالخادم');
        });
}

function initStatusChart(statusData) {
    const ctx = document.getElementById('statusChart').getContext('2d');

    // Destroy existing chart if it exists
    if (statusChart) {
        statusChart.destroy();
    }

    // Prepare data for chart
    const labels = [];
    const dataValues = [];
    const backgroundColors = [
        'rgba(28, 200, 138, 0.7)',  // Green for open
        'rgba(231, 74, 59, 0.7)',   // Red for closed
        'rgba(246, 194, 62, 0.7)'   // Yellow for unlicensed
    ];

    statusData.forEach(item => {
        labels.push(item.status);
        dataValues.push(item.count);
    });

    statusChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'عدد المساجد',
                data: dataValues,
                backgroundColor: backgroundColors,
                borderColor: backgroundColors.map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `عدد المساجد: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

function initFridayChart(fridayData) {
    const ctx = document.getElementById('fridayChart').getContext('2d');

    // Destroy existing chart if it exists
    if (fridayChart) {
        fridayChart.destroy();
    }
    // Prepare data for chart
    const labels = ['مساجد الجمعة', 'مساجد بدون جمعة'];
    const dataValues = [
        fridayData.find(item => item.friday_prayer === 'نعم')?.count || 0,
        fridayData.find(item => item.friday_prayer === 'لا')?.count || 0
    ];

    fridayChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: dataValues,
                backgroundColor: [
                    'rgba(54, 185, 204, 0.7)',
                    'rgba(108, 117, 125, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 185, 204, 1)',
                    'rgba(108, 117, 125, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function initCommunityChart(communityData) {
    const ctx = document.getElementById('communityChart').getContext('2d');
    if(communityChart) {
        communityChart.destroy();
    }
    // Sort communities by count (descending) and limit to top 10
    communityData.sort((a, b) => b.count - a.count);
    const topCommunities = communityData.slice(0, 10);

    // Prepare data for chart
    const labels = topCommunities.map(item => item.community || 'غير محدد');
    const dataValues = topCommunities.map(item => item.count);

    // Generate distinct colors for each community
    const backgroundColors = generateDistinctColors(topCommunities.length);

    communityChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'عدد المساجد',
                data: dataValues,
                backgroundColor: backgroundColors,
                borderColor: backgroundColors.map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y', // Horizontal bar chart
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `عدد المساجد: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Helper function to generate distinct colors
function generateDistinctColors(count) {
    const colors = [];
    const hueStep = 360 / count;

    for (let i = 0; i < count; i++) {
        const hue = i * hueStep;
        colors.push(`hsla(${hue}, 70%, 50%, 0.7)`);
    }

    return colors;
}

function showStatsError(message) {
    // Destroy any existing charts
    if (statusChart) {
        statusChart.destroy();
        statusChart = null;
    }
    if (fridayChart) {
        fridayChart.destroy();
        fridayChart = null;
    }
    if (communityChart) {
        communityChart.destroy();
        communityChart = null;
    }

    // Show error messages
    document.getElementById('statusChart').innerHTML = `
        <div class="alert alert-danger text-center py-2">
            <i class="fas fa-exclamation-triangle me-2"></i>${message}
        </div>`;
    document.getElementById('fridayChart').innerHTML = `
        <div class="alert alert-danger text-center py-2">
            <i class="fas fa-exclamation-triangle me-2"></i>${message}
        </div>`;
    document.getElementById('communityChart').innerHTML = `
        <div class="alert alert-danger text-center py-2">
            <i class="fas fa-exclamation-triangle me-2"></i>${message}
        </div>`;
}

// Search functionality
function handleSearchSubmit(event) {
    const liveSearch = document.getElementById('liveSearch');
    const searchTerm = liveSearch ? liveSearch.value.trim() : '';
    const hasFilter = ['community', 'status', 'friday_prayer', 'guide_imam']
        .some(name => (document.querySelector(`[name="${name}"]`)?.value || '') !== '');

    if (searchTerm.length === 0 || hasFilter) {
        return true;
    }

    event.preventDefault();
    showLoadingIndicator();
    fetchLiveSearchResults(searchTerm);
    return false;
}

// Add this function to mosque.js
function renderLocationCell(row) {
    if (row.latitude && row.longitude) {
        const mosqueName = escapeHtml(row.mosque_name || 'المسجد');
        return `
        <button type="button" class="row-action view-on-map"
                data-lat="${escapeHtml(row.latitude)}"
                data-lng="${escapeHtml(row.longitude)}"
                data-mosque="${mosqueName}"
                title="عرض على الخريطة"
                aria-label="عرض ${mosqueName} على الخريطة">
            <i class="fas fa-map-location-dot" aria-hidden="true"></i>
        </button>`;
    }
    return '<span class="text-muted">—</span>';
}

// Helper function to escape HTML
function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Function to setup map button handlers
function setupMapButtons() {
    document.querySelectorAll('.view-on-map').forEach(button => {
        if (button.dataset.mapButtonInitialized === 'true') return;
        button.dataset.mapButtonInitialized = 'true';
        button.addEventListener('click', function() {
            const lat = this.getAttribute('data-lat');
            const lng = this.getAttribute('data-lng');
            const mosqueName = this.getAttribute('data-mosque');

            // Open in new tab with Google Maps
            const url = `https://www.google.com/maps?q=${lat},${lng}&z=15`;
            window.open(url, '_blank', 'noopener,noreferrer');
        });
    });
}
function printMosqueDetails() {
    // Clone the modal content to avoid affecting the original
    const printContent = document.getElementById('modal-body-content').cloneNode(true);

    // Create a print window
    const printWindow = window.open('', '', 'width=900,height=650');
    printWindow.document.open();

    // Get the image source if exists
    const imgElement = printContent.querySelector('img');
    const imgSrc = imgElement ? imgElement.src : '';

    // Helper function to get text content by label
    const getTextByLabel = (label, tabId = null) => {
        // If tabId is provided, look within that tab
        const context = tabId ? printContent.querySelector(`#${tabId}`) : printContent;

        // Find all dt elements in the context
        const dtElements = context.querySelectorAll('dt');
        for (const dt of dtElements) {
            if (dt.textContent.trim().startsWith(label)) {
                const dd = dt.nextElementSibling;
                return dd ? dd.textContent.trim() : '';
            }
        }
        return '';
    };

    // Get status badge class
    const getStatusBadgeClass = (status) => {
        switch(status) {
            case 'مفتوح': return 'bg-success';
            case 'مغلق': return 'bg-danger';
            default: return 'bg-warning';
        }
    };

    // Get status text
    const statusElement = printContent.querySelector('.card-body .badge');
    const statusText = statusElement ? statusElement.textContent.trim() : 'غير محدد';

    // Get mosque data
    const mosqueName = getTextByLabel('اسم المسجد') || 'غير محدد';
    const nationalCode = getTextByLabel('الرمز الوطني') || 'غير محدد';
    const address = getTextByLabel('العنوان') || 'غير محدد';
    const constructionYear = getTextByLabel('سنة البناء') || 'غير محدد';
    const community = getTextByLabel('الجماعة') || 'غير محدد';
    const administrativeAttachment = getTextByLabel('الملحقة الإدارية') || '';
    const fundingSource = getTextByLabel('جهة الإنفاق') || 'غير محدد';

    // Get staff info from each tab
    const imamName = getTextByLabel('الاسم', 'imam-tab-pane') || 'غير محدد';
    const imamRegistration = getTextByLabel('ر.ب.ت.و', 'imam-tab-pane') || 'غير محدد';
    const imamPhone = getTextByLabel('الهاتف', 'imam-tab-pane') || 'غير محدد';
    const guideImam = getTextByLabel('الإمام المرشد', 'imam-tab-pane') || 'غير محدد';

    const preacherName = getTextByLabel('الاسم', 'preacher-tab-pane') || 'غير محدد';
    const preacherRegistration = getTextByLabel('ر.ب.ت.و', 'preacher-tab-pane') || 'غير محدد';
    const preacherPhone = getTextByLabel('الهاتف', 'preacher-tab-pane') || 'غير محدد';

    const muezzinName = getTextByLabel('الاسم', 'muezzin-tab-pane') || 'غير محدد';
    const muezzinRegistration = getTextByLabel('ر.ب.ت.و', 'muezzin-tab-pane') || 'غير محدد';
    const muezzinPhone = getTextByLabel('الهاتف', 'muezzin-tab-pane') || 'غير محدد';

    // Get services info
    const services = [];
    const serviceLabels = ['صلاة الجمعة', 'تحفيظ القرآن الكريم', 'محو الأمية', 'الوعظ والإرشاد'];
    serviceLabels.forEach(label => {
        services.push({
            label: label,
            value: getTextByLabel(label) || 'لا'
        });
    });

    // Get notes
    const notesElement = printContent.querySelector('.card-body p.mb-0');
    const notes = notesElement ? notesElement.textContent.trim() : 'لا توجد ملاحظات';
    // Add print styles with professional design
    printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl">
        <head>
            <title>تفاصيل المسجد - ${document.querySelector('#mosqueDetailsModal .modal-title').textContent}</title>
            <meta charset="UTF-8">
            <link rel="stylesheet" href="${new URL('assets/dist/app.min.css', window.location.href).href}">
        </head>
        <body>
            <div class="print-header">
                <img src="assets/images/logo.png" class="logo" alt="Logo">
                <h1>${document.querySelector('#mosqueDetailsModal .modal-title').textContent}</h1>
                <p class="subtitle">نظام إدارة المساجد - وزارة الأوقاف والشؤون الإسلامية</p>
                <p class="subtitle">تاريخ الطباعة: ${new Date().toLocaleDateString('fr-EG', { year: 'numeric', month: 'numeric', day: 'numeric' })}</p>
            </div>

            <div class="print-watermark no-print">مسجد</div>

            <div id="print-content"></div>

            <div class="footer no-print">
                <p>هذا المستند تم إنشاؤه تلقائياً من نظام إدارة المساجد</p>
                <p>جميع الحقوق محفوظة &copy; ${new Date().getFullYear()}</p>
            </div>

            <div class="no-print print-actions">
                <button id="printDocument" type="button" class="btn btn-primary">
                    <i class="fas fa-print"></i> طباعة
                </button>
                <button id="closePrintDocument" type="button" class="btn btn-secondary">
                    <i class="fas fa-times"></i> إغلاق
                </button>
            </div>
        </body>
        </html>
    `);

    // Create the content HTML
    const contentHTML = `
        ${imgSrc ? `
        <div class="text-center">
            <img src="${imgSrc}" class="mosque-image" alt="صورة المسجد">
        </div>
        ` : `
        <div class="no-image">
            <i class="fas fa-image fa-3x mb-2"></i>
            <p>لا توجد صورة متاحة للمسجد</p>
        </div>
        `}

        <div class="section-title">المعلومات الأساسية</div>
        <table class="info-table">
            <tr>
                <th>اسم المسجد</th>
                <td>${mosqueName}</td>
            </tr>
            <tr>
                <th>الرمز الوطني</th>
                <td>${nationalCode}</td>
            </tr>
            <tr>
                <th>العنوان</th>
                <td>${address}</td>
            </tr>
            <tr>
                <th>سنة البناء</th>
                <td>${constructionYear}</td>
            </tr>
            <tr>
                <th>الحالة</th>
                <td><span class="badge ${getStatusBadgeClass(statusText)}">${statusText}</span></td>
            </tr>
        </table>

        <div class="section-title">المعلومات الإدارية</div>
        <table class="info-table">
            <tr>
                <th>الجماعة</th>
                <td>${community}</td>
            </tr>
            ${administrativeAttachment ? `
            <tr>
                <th>الملحقة الإدارية</th>
                <td>${administrativeAttachment}</td>
            </tr>
            ` : ''}
            <tr>
                <th>جهة الإنفاق</th>
                <td>${fundingSource}</td>
            </tr>
        </table>
        <br><br><br><br><br><br>
        <div class="section-title">طاقم المسجد</div>

        <div class="staff-section">
            <div class="staff-title">الإمام</div>
            <table class="info-table">
                <tr>
                    <th>الاسم</th>
                    <td>${imamName}</td>
                </tr>
                <tr>
                    <th>ر.ب.ت.و</th>
                    <td>${imamRegistration}</td>
                </tr>
                <tr>
                    <th>الهاتف</th>
                    <td>${imamPhone}</td>
                </tr>
                <tr>
                    <th>الإمام المرشد</th>
                    <td>${guideImam}</td>
                </tr>
            </table>
        </div>

        <div class="staff-section">
            <div class="staff-title">الخطيب</div>
            <table class="info-table">
                <tr>
                    <th>الاسم</th>
                    <td>${preacherName}</td>
                </tr>
                <tr>
                    <th>ر.ب.ت.و</th>
                    <td>${preacherRegistration}</td>
                </tr>
                <tr>
                    <th>الهاتف</th>
                    <td>${preacherPhone}</td>
                </tr>
            </table>
        </div>

        <div class="staff-section">
            <div class="staff-title">المؤذن</div>
            <table class="info-table">
                <tr>
                    <th>الاسم</th>
                    <td>${muezzinName}</td>
                </tr>
                <tr>
                    <th>ر.ب.ت.و</th>
                    <td>${muezzinRegistration}</td>
                </tr>
                <tr>
                    <th>الهاتف</th>
                    <td>${muezzinPhone}</td>
                </tr>
            </table>
        </div>

        <div class="section-title">الخدمات</div>
        <table class="info-table">
            ${services.map(service => `
                <tr>
                    <th>${service.label}</th>
                    <td>${service.value}</td>
                </tr>
            `).join('')}
        </table>

        <div class="section-title">ملاحظات</div>
        <table class="info-table">
            <tr>
                <td colspan="2">${notes}</td>
            </tr>
        </table>
    `;

    // Add the content to print
    const printContainer = printWindow.document.getElementById('print-content');
    printContainer.innerHTML = contentHTML;
    printWindow.document.getElementById('printDocument')?.addEventListener('click', () => printWindow.print());
    printWindow.document.getElementById('closePrintDocument')?.addEventListener('click', () => printWindow.close());
    printWindow.document.close();

    // Focus the print window
    printWindow.focus();

    // Auto-print after a short delay
    setTimeout(() => {
        printWindow.print();
    }, 500);
}

function fetchLiveSearchResults(searchTerm, page = 1) {
    let url = `ajax/search_mosques.php?q=${encodeURIComponent(searchTerm)}&page=${page}`;

    // Get all filter values
    const communityFilter = document.querySelector('select[name="community"]');
    const statusFilter = document.querySelector('select[name="status"]');
    const fridayFilter = document.querySelector('select[name="friday_prayer"]');
    const guideImamFilter = document.querySelector('select[name="guide_imam"]');

    // Add filters to URL if they have values
    if (communityFilter && communityFilter.value) {
        url += `&community=${encodeURIComponent(communityFilter.value)}`;
    }
    if (statusFilter && statusFilter.value) {
        url += `&status=${encodeURIComponent(statusFilter.value)}`;
    }
    if (fridayFilter && fridayFilter.value) {
        url += `&friday_prayer=${encodeURIComponent(fridayFilter.value)}`;
    }
    if (guideImamFilter && guideImamFilter.value) {
        // Extract just the name part (remove the count in parentheses)
        const guideName = guideImamFilter.value.replace(/\s*\(\d+\)$/, '');
        url += `&guide_imam=${encodeURIComponent(guideName)}`;
    }

    showLoadingIndicator();

    fetch(url, {
        headers: { 'Accept': 'application/json' }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateTableWithResults(data.data);
            updatePaginationForLiveSearch(data.total, data.page, data.pages);
        } else {
            showSearchError(data.message || 'حدث خطأ أثناء البحث');
        }
    })
    .catch(error => {
        console.error('Error during live search:', error);
        showSearchError('حدث خطأ في الاتصال بالخادم');
    });
}

function showLoadingIndicator() {
    const tbody = document.querySelector('.directory-table tbody');
    if (!tbody) return;
    tbody.setAttribute('aria-busy', 'true');
    tbody.innerHTML = `
        <tr>
            <td colspan="${getDirectoryColumnCount()}" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">جاري التحميل...</span>
                </div>
            </td>
        </tr>`;
    updateBulkSelectionUI();
}

function updateTableWithResults(results) {
    const tbody = document.querySelector('.directory-table tbody');
    if (!tbody) return;
    tbody.setAttribute('aria-busy', 'false');

    if (!Array.isArray(results) || results.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="${getDirectoryColumnCount()}" class="text-center py-4 text-muted">
                    <i class="fas fa-search me-2" aria-hidden="true"></i>لا توجد نتائج مطابقة لبحثك
                </td>
            </tr>`;
        updateMobileCardsWithResults([]);
        updateBulkSelectionUI();
        return;
    }

    const canEdit = document.body.dataset.canEdit === 'true';
    const canDelete = document.body.dataset.canDelete === 'true';
    const csrfToken = escapeHtml(document.querySelector('meta[name="csrf-token"]')?.content || '');
    let html = '';

    results.forEach((row, index) => {
        const registrationValue = String(row.registration_number ?? '');
        const registrationNumber = escapeHtml(registrationValue);
        const registrationQuery = encodeURIComponent(registrationValue);
        const mosqueName = escapeHtml(row.mosque_name || '—');
        const address = escapeHtml(row.address || '—');
        const nationalCode = escapeHtml(row.national_code || '—');
        const fridayPrayer = escapeHtml(row.friday_prayer || '—');
        const status = escapeHtml(row.status || 'غير محدد');
        const imamName = escapeHtml(row.imam_name || '—');
        const guideImam = escapeHtml(row.guide_imam_display || row.guide_imam || '—');
        const community = escapeHtml(row.community || '—');
        const constructionYear = /^\d{4}/.test(String(row.construction_date || ''))
            ? escapeHtml(String(row.construction_date).slice(0, 4))
            : '—';
        const statusClass = row.status === 'مفتوح'
            ? 'text-success bg-success-subtle'
            : (row.status === 'مغلق' ? 'text-danger bg-danger-subtle' : 'text-warning bg-warning-subtle');
        const checkboxId = `mosque-result-${index}`;

        const selectionCell = canDelete ? `
            <td data-column="selection">
                <label class="visually-hidden" for="${checkboxId}">تحديد ${mosqueName}</label>
                <input type="checkbox" id="${checkboxId}" name="selected_mosques[]" value="${registrationNumber}" class="form-check-input mosque-checkbox">
            </td>` : '';
        const editAction = canEdit ? `
            <a class="row-action" href="edit_mosque.php?id=${registrationQuery}" aria-label="تعديل بيانات ${mosqueName}" title="تعديل">
                <i class="fas fa-pen" aria-hidden="true"></i>
            </a>` : '';
        const deleteAction = canDelete ? `
            <form method="POST" action="delete_mosque.php" class="js-confirm-submit" data-confirm="هل أنت متأكد من حذف هذا المسجد؟">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="id" value="${registrationNumber}">
                <button type="submit" class="row-action row-action--danger" aria-label="حذف ${mosqueName}" title="حذف">
                    <i class="fas fa-trash" aria-hidden="true"></i>
                </button>
            </form>` : '';

        html += `
        <tr class="mosque-table-row">
            ${selectionCell}
            <td data-column="registration" class="text-muted">${registrationNumber}</td>
            <td data-column="name"><strong class="record-name"><i class="fas fa-mosque" aria-hidden="true"></i>${mosqueName}</strong></td>
            <td data-column="address"><span class="record-address">${address}</span></td>
            <td data-column="national"><span class="badge bg-light text-dark">${nationalCode}</span></td>
            <td data-column="friday">${fridayPrayer}</td>
            <td data-column="status"><span class="status-badge ${statusClass}">${status}</span></td>
            <td data-column="construction">${constructionYear}</td>
            <td data-column="imam">${imamName}</td>
            <td data-column="guide">${guideImam}</td>
            <td data-column="community">${community}</td>
            <td data-column="location">${renderLocationCell(row)}</td>
            <td data-column="actions">
                <div class="record-actions">
                    <button type="button" class="row-action view-mosque-btn" data-bs-toggle="modal" data-bs-target="#mosqueDetailsModal" data-mosque-id="${registrationNumber}" aria-label="عرض تفاصيل ${mosqueName}" title="عرض">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                    ${editAction}
                    ${deleteAction}
                </div>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
    updateMobileCardsWithResults(results);
    applySavedColumnVisibility();
    initializeTooltips();
    setupMosqueDetailsModal();
    setupBulkSelection();
    setupMapButtons();
}

function updateMobileCardsWithResults(results) {
    const container = document.querySelector('.mosque-mobile-cards');
    if (!container) return;
    if (!Array.isArray(results) || results.length === 0) {
        container.innerHTML = '<div class="mobile-empty-state"><i class="fas fa-search" aria-hidden="true"></i><span>لا توجد نتائج مطابقة</span></div>';
        return;
    }

    const canEdit = document.body.dataset.canEdit === 'true';
    const canDelete = document.body.dataset.canDelete === 'true';
    const csrfToken = escapeHtml(document.querySelector('meta[name="csrf-token"]')?.content || '');
    container.innerHTML = results.map((row) => {
        const registrationValue = String(row.registration_number ?? '');
        const registrationNumber = escapeHtml(registrationValue);
        const registrationQuery = encodeURIComponent(registrationValue);
        const mosqueName = escapeHtml(row.mosque_name || '—');
        const nationalCode = escapeHtml(row.national_code || '—');
        const address = escapeHtml(row.address || 'العنوان غير محدد');
        const community = escapeHtml(row.community || '—');
        const imamName = escapeHtml(row.imam_name || '—');
        const status = escapeHtml(row.status || 'غير محدد');
        const statusClass = row.status === 'مفتوح'
            ? 'text-success bg-success-subtle'
            : (row.status === 'مغلق' ? 'text-danger bg-danger-subtle' : 'text-warning bg-warning-subtle');
        const editAction = canEdit ? `<a class="btn btn-sm btn-primary" href="edit_mosque.php?id=${registrationQuery}"><i class="fas fa-pen me-1" aria-hidden="true"></i>تعديل</a>` : '';
        const deleteAction = canDelete ? `
            <form method="POST" action="delete_mosque.php" class="js-confirm-submit" data-confirm="هل أنت متأكد من حذف هذا المسجد؟">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="id" value="${registrationNumber}">
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1" aria-hidden="true"></i>حذف</button>
            </form>` : '';

        return `<article class="mosque-mobile-card">
            <div class="mosque-mobile-card__header">
                <div><h2>${mosqueName}</h2><p>${address}</p></div>
                <div class="mosque-mobile-card__badges"><span class="badge bg-light text-dark">${nationalCode}</span><span class="status-badge ${statusClass}">${status}</span></div>
            </div>
            <dl><div><dt>الجماعة</dt><dd>${community}</dd></div><div><dt>الإمام</dt><dd>${imamName}</dd></div></dl>
            <div class="record-actions">
                <button type="button" class="btn btn-sm btn-outline-primary view-mosque-btn" data-bs-toggle="modal" data-bs-target="#mosqueDetailsModal" data-mosque-id="${registrationNumber}"><i class="fas fa-eye me-1" aria-hidden="true"></i>عرض</button>
                ${editAction}${deleteAction}
            </div>
        </article>`;
    }).join('');
}

function showSearchError(message) {
    const tbody = document.querySelector('.directory-table tbody');
    if (!tbody) return;
    tbody.setAttribute('aria-busy', 'false');
    tbody.innerHTML = `
        <tr>
            <td colspan="${getDirectoryColumnCount()}" class="text-center py-4 text-danger">
                <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>${escapeHtml(message)}
                <button class="btn btn-sm btn-outline-danger ms-2 js-retry-live-search">
                    <i class="fas fa-sync-alt me-1" aria-hidden="true"></i>إعادة المحاولة
                </button>
            </td>
        </tr>`;
    updateBulkSelectionUI();
}

function retryLiveSearch() {
    const searchTerm = document.getElementById('liveSearch').value.trim();
    if (searchTerm) {
        showLoadingIndicator();
        fetchLiveSearchResults(searchTerm);
    }
}

// Pagination
function updatePaginationForLiveSearch(total, currentPage, totalPages) {
    const paginationContainer = document.querySelector('.pagination');

    // If no pagination container exists, create one
    if (!paginationContainer && totalPages > 1) {
        const tableResponsive = document.querySelector('.table-responsive');
        if (tableResponsive) {
            const newPagination = document.createElement('div');
            newPagination.className = 'mt-4';
            newPagination.innerHTML = createPaginationHTML(currentPage, totalPages);
            tableResponsive.insertAdjacentElement('afterend', newPagination);
        }
        return;
    }

    // If pagination exists but we don't need it anymore
    if (paginationContainer && totalPages <= 1) {
        paginationContainer.remove();
        return;
    }

    // Update existing pagination
    if (paginationContainer) {
        paginationContainer.innerHTML = createPaginationHTML(currentPage, totalPages);
    }
}

function createPaginationHTML(currentPage, totalPages) {
    let queryString = '';
    const searchTerm = document.getElementById('liveSearch').value.trim();
    const communityFilter = document.querySelector('select[name="community"]');

    if (searchTerm) queryString += `&q=${encodeURIComponent(searchTerm)}`;
    if (communityFilter && communityFilter.value) queryString += `&community=${encodeURIComponent(communityFilter.value)}`;

    let html = `<nav aria-label="Page navigation"><ul class="pagination justify-content-center">`;

    if (currentPage > 1) {
        html += `<li class="page-item">
            <a class="page-link" href="#" data-live-search-page="${currentPage - 1}" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>`;
    }

    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" data-live-search-page="1">1</a></li>`;
        if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${currentPage === i ? 'active' : ''}">
            <a class="page-link" href="#" data-live-search-page="${i}">${i}</a>
        </li>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" data-live-search-page="${totalPages}">${totalPages}</a></li>`;
    }

    if (currentPage < totalPages) {
        html += `<li class="page-item">
            <a class="page-link" href="#" data-live-search-page="${currentPage + 1}" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>`;
    }

    html += `</ul></nav>`;
    return html;
}

function loadLiveSearchPage(page) {
    const searchTerm = document.getElementById('liveSearch').value.trim();
    showLoadingIndicator();
    fetchLiveSearchResults(searchTerm, page);
    document.querySelector('.table-responsive').scrollIntoView({ behavior: 'smooth' });
}

// Mosque details modal
function setupMosqueDetailsModal() {
    const modalElement = document.getElementById('mosqueDetailsModal');
    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modalElement, {
        backdrop: 'static',
        keyboard: false
    });

    // Clear content when modal is hidden
    if (modalElement.dataset.detailsModalInitialized !== 'true') {
        modalElement.dataset.detailsModalInitialized = 'true';
        modalElement.addEventListener('hidden.bs.modal', function() {
            const modalBody = document.getElementById('modal-body-content');
            if (modalBody) modalBody.replaceChildren();
            if (mosqueDetailsAbortController) {
                mosqueDetailsAbortController.abort();
            }
        });
    }

    document.querySelectorAll('.view-mosque-btn').forEach(btn => {
        if (btn.dataset.detailsButtonInitialized === 'true') return;
        btn.dataset.detailsButtonInitialized = 'true';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const mosqueId = this.getAttribute('data-mosque-id');
            loadMosqueDetails(mosqueId);
        });
    });
}

function loadMosqueDetails(mosqueId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById('mosqueDetailsModal'));
    lastRequestedMosqueId = mosqueId;
    const modalBody = document.getElementById('modal-body-content');

    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2">جاري تحميل بيانات المسجد</p>
        </div>`;

    if (mosqueDetailsAbortController) {
        mosqueDetailsAbortController.abort();
    }

    mosqueDetailsAbortController = new AbortController();

    fetch(`ajax/get_mosque_details.php?id=${encodeURIComponent(String(mosqueId))}&_=${Date.now()}`, {
        signal: mosqueDetailsAbortController.signal,
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            modalBody.innerHTML = formatMosqueDetails(data.data);
            initializeModalTabs();
        } else {
            showModalError(data.message || 'حدث خطأ أثناء جلب بيانات المسجد');
        }
    })
    .catch(error => {
        if (error.name !== 'AbortError') {
            console.error('Fetch error:', error);
            showModalError('حدث خطأ في الاتصال بالخادم. الرجاء التحقق من اتصال الشبكة والمحاولة مرة أخرى.');
        }
    });

    modal.show();
}

function formatMosqueDetails(mosque) {
    const rawImagePath = String(mosque.main_image || '');
    const safeMosque = {};
    Object.entries(mosque).forEach(([key, value]) => {
        safeMosque[key] = (typeof value === 'string' || typeof value === 'number')
            ? escapeHtml(value)
            : value;
    });
    safeMosque.main_image = /^uploads\/mosques\/[a-zA-Z0-9._-]+\.(?:jpe?g|png)$/i.test(rawImagePath)
        ? escapeHtml(rawImagePath)
        : '';
    mosque = safeMosque;

    const fallback = 'غير محدد';
    const value = (input) => input || fallback;
    const formatPhone = (phone) => phone ? String(phone).replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1-$2-$3-$4-$5') : '';
    const copyButton = (input, label = 'نسخ') => input ? `
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2 js-copy-value" data-copy-value="${input}" aria-label="${label}">
            <i class="fas fa-copy" aria-hidden="true"></i>
        </button>` : '';
    const copyable = (input, label = 'نسخ') => input ? `<span>${input}</span>${copyButton(input, label)}` : fallback;
    const detailRow = (label, content) => `
        <dt class="col-sm-4">${label}:</dt>
        <dd class="col-sm-8">${content}</dd>`;
    const statusClass = (status) => String(status || '').includes('مغلق') ? 'bg-danger' : (String(status || '').includes('مفتوح') ? 'bg-success' : 'bg-warning');
    const yesNo = (input) => input === 'نعم' ? '<i class="fas fa-check-circle text-success me-2"></i>نعم' : '<i class="fas fa-times-circle text-secondary me-2"></i>لا';
    const constructionYear = mosque.construction_year || fallback;
    const hasCoordinates = mosque.latitude && mosque.longitude;
    const mapLink = hasCoordinates
        ? `<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q=${mosque.latitude},${mosque.longitude}&z=17"><i class="fas fa-map-location-dot me-1"></i>فتح في خرائط Google</a>`
        : fallback;

    const imageDisplay = mosque.main_image ? `
        <div class="text-center mb-4">
            <img src="${mosque.main_image}" class="img-fluid rounded mosque-detail-image" alt="صورة المسجد">
        </div>` : '<div class="alert alert-info text-center">لا توجد صورة متاحة للمسجد</div>';

    const adminRows = mosque.admin_type === 'pashalik'
        ? detailRow('الباشوية', value(mosque.pashalik))
        : mosque.admin_type === 'circle'
            ? detailRow('الدائرة', value(mosque.circle)) + detailRow('القيادة', value(mosque.leadership))
            : '';

    return `
    <div class="row print-container">
        <div class="col-12">${imageDisplay}</div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>المعلومات الأساسية</h6></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        ${detailRow('اسم المسجد', value(mosque.mosque_name))}
                        ${detailRow('الرمز الوطني', copyable(mosque.national_code, 'نسخ الرمز الوطني'))}
                        ${detailRow('العنوان', copyable(mosque.address, 'نسخ العنوان'))}
                        ${detailRow('الموقع', mapLink)}
                        ${detailRow('سنة البناء', constructionYear)}
                        ${detailRow('الحالة', `<span class="badge ${statusClass(mosque.status)}">${value(mosque.status)}</span>`)}
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-building me-2"></i>المعلومات الإدارية</h6></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        ${adminRows}
                        ${detailRow('الجماعة', value(mosque.community))}
                        ${mosque.administrative_attachment ? detailRow('الملحقة الإدارية', value(mosque.administrative_attachment)) : ''}
                        ${detailRow('جهة الإنفاق', value(mosque.funding_source))}
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-users me-2"></i>طاقم المسجد</h6></div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="staffTabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="imam-tab" data-bs-toggle="tab" data-bs-target="#imam-tab-pane" type="button">الإمام</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="preacher-tab" data-bs-toggle="tab" data-bs-target="#preacher-tab-pane" type="button">الخطيب</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="muezzin-tab" data-bs-toggle="tab" data-bs-target="#muezzin-tab-pane" type="button">المؤذن</button></li>
                    </ul>
                    <div class="tab-content p-3 border border-top-0" id="staffTabsContent">
                        <div class="tab-pane fade show active" id="imam-tab-pane" role="tabpanel">
                            <dl class="row mb-0">
                                ${detailRow('الاسم', value(mosque.imam_name))}
                                ${detailRow('ر.ب.ت.و', copyable(mosque.imam_registration, 'نسخ رقم الإمام'))}
                                ${detailRow('الهاتف', copyable(formatPhone(mosque.imam_phone), 'نسخ هاتف الإمام'))}
                                ${detailRow('الإمام المرشد', value(mosque.guide_imam))}
                            </dl>
                        </div>
                        <div class="tab-pane fade" id="preacher-tab-pane" role="tabpanel">
                            <dl class="row mb-0">
                                ${detailRow('الاسم', value(mosque.preacher_name))}
                                ${detailRow('ر.ب.ت.و', copyable(mosque.preacher_registration, 'نسخ رقم الخطيب'))}
                                ${detailRow('الهاتف', copyable(formatPhone(mosque.preacher_phone), 'نسخ هاتف الخطيب'))}
                            </dl>
                        </div>
                        <div class="tab-pane fade" id="muezzin-tab-pane" role="tabpanel">
                            <dl class="row mb-0">
                                ${detailRow('الاسم', value(mosque.muezzin_name))}
                                ${detailRow('ر.ب.ت.و', copyable(mosque.muezzin_registration, 'نسخ رقم المؤذن'))}
                                ${detailRow('الهاتف', copyable(formatPhone(mosque.muezzin_phone), 'نسخ هاتف المؤذن'))}
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-tasks me-2"></i>الخدمات</h6></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        ${detailRow('صلاة الجمعة', yesNo(mosque.friday_prayer))}
                        ${detailRow('تحفيظ القرآن الكريم', yesNo(mosque.quran_memorization))}
                        ${detailRow('محو الأمية', yesNo(mosque.literacy_program))}
                        ${detailRow('الوعظ والإرشاد', yesNo(mosque.guidance_program))}
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card mt-2">
                <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>ملاحظات</h6></div>
                <div class="card-body"><p class="mb-0">${mosque.notes || 'لا توجد ملاحظات'}</p></div>
            </div>
        </div>
    </div>`;
}
function initializeModalTabs() {
    const tabElms = document.querySelectorAll('#staffTabs button[data-bs-toggle="tab"]');
    tabElms.forEach(tabElm => new bootstrap.Tab(tabElm));
}

function showModalError(message) {
    const safeMessage = escapeHtml(message);
    document.getElementById('modal-body-content').innerHTML = `
        <div class="alert alert-danger d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
            <div>
                <h5 class="alert-heading mb-2">خطأ في تحميل البيانات</h5>
                <p class="mb-3">${safeMessage}</p>
                <button class="btn btn-sm btn-outline-danger js-retry-last-request">
                    <i class="fas fa-sync-alt me-1"></i>إعادة المحاولة
                </button>
            </div>
        </div>`;
}

function retryLastRequest() {
    if (lastRequestedMosqueId) {
        loadMosqueDetails(lastRequestedMosqueId);
    }
}

// Bulk selection
function updateBulkSelectionUI() {
    const selectAll = document.getElementById('selectAll');
    const deleteBtn = document.getElementById('deleteSelected');
    const selectedCount = document.getElementById('selectedCount');
    const bulkSelectionBar = document.getElementById('bulkSelectionBar');
    const checkboxes = [...document.querySelectorAll('.mosque-checkbox')];
    const checked = checkboxes.filter(checkbox => checkbox.checked);
    const count = checked.length;

    if (selectedCount) selectedCount.textContent = String(count);
    if (deleteBtn) deleteBtn.disabled = count === 0;
    if (bulkSelectionBar) bulkSelectionBar.hidden = count === 0;
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && count === checkboxes.length;
        selectAll.indeterminate = count > 0 && count < checkboxes.length;
    }
    checkboxes.forEach(checkbox => {
        checkbox.closest('tr')?.classList.toggle('is-selected', checkbox.checked);
    });
}

function setupBulkSelection() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.mosque-checkbox');

    if (!selectAll) {
        updateBulkSelectionUI();
        return;
    }

    if (selectAll.dataset.bulkSelectionInitialized !== 'true') {
        selectAll.dataset.bulkSelectionInitialized = 'true';
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.mosque-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkSelectionUI();
        });
    }

    checkboxes.forEach(checkbox => {
        if (checkbox.dataset.bulkSelectionInitialized === 'true') return;
        checkbox.dataset.bulkSelectionInitialized = 'true';
        checkbox.addEventListener('change', updateBulkSelectionUI);
    });

    updateBulkSelectionUI();
}

// Initialize tooltips
function initializeTooltips() {
    if (typeof bootstrap === 'undefined') return;
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl));
}

// Tab title update
function updateTabTitle(title) {
    const tabTitle = document.getElementById('currentTabTitle');
    if (tabTitle) tabTitle.textContent = title;
}

// Setup search form
function setupSearch() {
    const clearSearch = document.getElementById('clearSearch');
    const liveSearch = document.getElementById('liveSearch');

    if (!clearSearch || !liveSearch || liveSearch.dataset.searchControlsInitialized === 'true') {
        return;
    }

    liveSearch.dataset.searchControlsInitialized = 'true';

    clearSearch.addEventListener('click', function() {
        liveSearch.value = '';
        clearSearch.classList.add('d-none');
        fetchLiveSearchResults('');
        liveSearch.focus();
    });

    liveSearch.addEventListener('input', function() {
        clearSearch.classList.toggle('d-none', this.value.trim().length === 0);
    });
}

function handleMobileView() {
    const isMobile = window.innerWidth < 768;

    if (isMobile) {
        // mobile-specific classes
        document.body.classList.add('mobile-view');

        // Adjust search form
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.classList.add('mobile-optimized');
        }

        // Handle tooltips on mobile
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            return bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl, {
                trigger: 'click hover'
            });
        });
    } else {
        document.body.classList.remove('mobile-view');
    }
}


// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('searchForm')) {
        return;
    }

    setupMapButtons();
    handleMobileView();

        // Initialize on load and resize
    window.addEventListener('resize', handleMobileView);
        // Mobile menu toggle for actions
    document.querySelectorAll('.actions_dropdown').forEach(dropdown => {
        dropdown.addEventListener('click', function(e) {
            if (window.innerWidth < 768) {
                e.preventDefault();
                this.classList.toggle('active');
            }
        });
    });
        // Enhanced mobile search functionality

    const mobileSearch = document.getElementById('liveSearch');
    if (mobileSearch && mobileSearch.dataset.mobileSearchInitialized !== 'true') {
        mobileSearch.dataset.mobileSearchInitialized = 'true';
        mobileSearch.classList.add('mobile-search-input');
    }

});

function setupCopyValueButtons() {
    document.addEventListener('click', async function(event) {
        const button = event.target instanceof Element ? event.target.closest('.js-copy-value') : null;
        if (!button) return;
        const value = button.getAttribute('data-copy-value') || '';
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
            const original = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
            setTimeout(() => { button.innerHTML = original; }, 1200);
        } catch (error) {
            console.warn('Copy failed', error);
        }
    });
}

document.addEventListener('DOMContentLoaded', setupCopyValueButtons);


// Mosque directory: external bulk-action bootstrap (formerly embedded in the view).
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('deleteSelected')?.addEventListener('click', () => {
        const ids = [...document.querySelectorAll('.mosque-checkbox:checked')].map((checkbox) => checkbox.value);
        if (!ids.length) return;
        const submit = () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'bulk_delete_mosques.php';
            const token = document.createElement('input');
            token.type = 'hidden'; token.name = 'csrf_token'; token.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
            form.appendChild(token);
            ids.forEach((id) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'selected_mosques[]'; input.value = id; form.appendChild(input); });
            document.body.appendChild(form);
            form.submit();
        };
        const message = `هل أنت متأكد من حذف ${ids.length} مسجد(اً)؟`;
        if (window.Swal) {
            window.Swal.fire({ title: 'تأكيد الحذف', text: message, icon: 'warning', showCancelButton: true, confirmButtonText: 'حذف المحدد', cancelButtonText: 'إلغاء', reverseButtons: true, confirmButtonColor: '#a83e3e' }).then((result) => { if (result.isConfirmed) submit(); });
        } else if (window.confirm(message)) submit();
    });
});
