// Global variables
let lastRequestedMosqueId = null;
let mosqueDetailsAbortController = null;
let statusChart = null;
let fridayChart = null;
let communityChart = null;

// Initialize everything when DOM is loaded
// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
    setupMosqueDetailsModal();
    setupBulkSelection();
    setupSearch();

    if (typeof Chart !== 'undefined') {
        setupQuickStats();
    } else {
        console.error('Chart.js not loaded!');
    }
    
    // Initialize Select2 with null check
    if (typeof $.fn.select2 === 'function') {
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
                document.getElementById('searchForm').submit();
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
                const searchTerm = document.getElementById('liveSearch')?.value.trim();
                if (searchTerm) {
                    showLoadingIndicator();
                    fetchLiveSearchResults(searchTerm);
                } else {
                    // If no search term, submit the form normally to filter by selected filters
                    const searchForm = document.getElementById('searchForm');
                    if (searchForm) {
                        searchForm.submit();
                    }
                }
            });
        }
    });
});



// Quick stats modal
function setupQuickStats() {
    const quickStatsBtn = document.createElement('button');
    quickStatsBtn.className = 'btn btn-info rounded-circle p-3 position-fixed bottom-0 start-0 m-4 shadow-lg pulse-glow';
    quickStatsBtn.setAttribute('data-bs-toggle', 'modal');
    quickStatsBtn.setAttribute('data-bs-target', '#quickStatsModal');
    quickStatsBtn.innerHTML = '<i class="fas fa-chart-pie fs-4"></i>';
    quickStatsBtn.setAttribute('title', 'عرض الإحصائيات السريعة');
    document.body.appendChild(quickStatsBtn);
    
    const quickStatsModal = document.getElementById('quickStatsModal');
    quickStatsModal.addEventListener('shown.bs.modal', function() {
        // Initialize charts when modal is shown
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
    const searchTerm = liveSearch.value.trim();
    
    // Don't submit if search is empty (let the input handler handle it)
    if (searchTerm.length === 0) {
        event.preventDefault();
        return false;
    }
    
    event.preventDefault();
    showLoadingIndicator();
    fetchLiveSearchResults(searchTerm);
    return false;
}

// Add this function to mosque.js
function renderLocationCell(row) {
    if (row.latitude && row.longitude) {
        return `
        <button class="btn btn-sm btn-outline-primary view-on-map" 
                data-lat="${escapeHtml(row.latitude)}" 
                data-lng="${escapeHtml(row.longitude)}"
                data-mosque="${escapeHtml(row.mosque_name)}"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                title="عرض على الخريطة">
            <i class="fas fa-map-marked-alt"></i>
        </button>`;
    } else {
        return '<span class="text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="لم يتم تحديد الموقع">غير محدد</span>';
    }
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
        button.addEventListener('click', function() {
            const lat = this.getAttribute('data-lat');
            const lng = this.getAttribute('data-lng');
            const mosqueName = this.getAttribute('data-mosque');
            
            // Open in new tab with Google Maps
            const url = `https://www.google.com/maps?q=${lat},${lng}&z=15`;
            window.open(url, '_blank');
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
            <style>
                @page {
                    size: A4;
                    margin: 15mm 10mm;
                }
                body { 
                    font-family: 'Arial', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    color: #333;
                    direction: rtl;
                    background-color: #fff;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #2c3e50;
                }
                .print-header h1 {
                    color: #2c3e50;
                    margin: 0;
                    font-size: 24px;
                    font-weight: bold;
                }
                .print-header .subtitle {

                    color: #7f8c8d;
                    margin: 5px 0 0;
                    font-size: 16px;
                }
                .print-header .logo {
                    max-width: 300px;
                    height: 150px;

                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    page-break-inside: avoid;
                }
                .info-table th {
                    background-color: #f8f9fa;
                    padding: 10px 15px;
                    text-align: right;
                    border: 1px solid #ddd;
                    width: 30%;
                }
                .info-table td {
                    padding: 10px 15px;
                    border: 1px solid #ddd;
                }
                .section-title {
                    color: #2c3e50;
                    font-weight: bold;
                    font-size: 18px;
                    margin: 25px 0 15px;
                    padding-bottom: 5px;
                    border-bottom: 1px solid #eee;
                }
                .badge {
                    font-size: 90%;
                    padding: 4px 8px;
                    border-radius: 3px;
                }
                .text-success { color: #28a745 !important; }
                .text-danger { color: #dc3545 !important; }
                .text-warning { color: #fd7e14 !important; }
                .text-primary { color: #007bff !important; }
                .bg-success { background-color: #28a745 !important; }
                .bg-danger { background-color: #dc3545 !important; }
                .bg-warning { background-color: #fd7e14 !important; }
                .bg-info { background-color: #17a2b8 !important; }
                .bg-primary { background-color: #007bff !important; }
                .fa { margin-left: 5px; }
                .footer {
                    margin-top: 30px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    font-size: 12px;
                    color: #777;
                }
                .print-watermark {
                    position: fixed;
                    bottom: 20%;
                    left: 0;
                    right: 0;
                    text-align: center;
                    opacity: 0.1;
                    font-size: 80px;
                    color: #999;
                    transform: rotate(-30deg);
                    pointer-events: none;
                    z-index: -1;
                }
                .mosque-image {
                    max-width: 100%;
                    max-height: 300px;
                    display: block;
                    margin: 0 auto 20px;
                    border-radius: 4px;
                }
                .no-image {
                    text-align: center;
                    color: #6c757d;
                    padding: 20px;
                    border: 1px dashed #ddd;
                    margin-bottom: 20px;
                }
                .staff-section {
                    margin-bottom: 15px;
                }
                .staff-title {
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 5px;
                    padding-bottom: 3px;
                    border-bottom: 1px solid #eee;
                }
                @media print {
                    .no-print { 
                        display: none !important; 
                    }
                    body { 
                        padding: 0 !important;
                        background: white !important;
                    }
                    .section-title {
                        page-break-after: avoid;
                    }
                    .print-watermark {
                        display: block !important;
                    }
                }
            </style>
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
            
            <div class="no-print" style="text-align: center; margin-top: 20px;">
                <button onclick="window.print()" style="padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-print"></i> طباعة
                </button>
                <button onclick="window.close()" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;">
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
    document.querySelector('tbody').innerHTML = `
        <tr>
            <td colspan="12" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">جاري التحميل...</span>
                </div>
            </td>
        </tr>`;
}

function updateTableWithResults(results) {
    const tbody = document.querySelector('tbody');
    
    if (results.length === 0) {
        tbody.innerHTML = `
            <tr class="animate__animated animate__fadeInUp">
                <td colspan="12" class="text-center py-4 text-muted">
                    <i class="fas fa-search me-2"></i>لا توجد نتائج مطابقة لبحثك
                </td>
            </tr>`;
        return;
    }
    
    let html = '';
    let animationDelay = 0;
    
    // Use the global IS_ADMIN variable that was set in mosques.php
    const isAdmin = typeof IS_ADMIN !== 'undefined' ? IS_ADMIN : false;
    
    results.forEach(row => {
        animationDelay += 0.05;
        const fridayIcon = row.friday_prayer === 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-secondary';
        const statusIcon = row.status === 'مفتوح' ? 'fa-check-circle text-success' : row.status ==='مغلق' ? 'fa-times-circle text-danger' :  'fa-times-circle text-warning';
        
        // Admin action buttons - only show if user is admin
        const adminButtons = isAdmin ? `
            <a href="edit_mosque.php?id=${row.registration_number}" 
               class="btn btn-sm btn-icon btn-primary rounded-circle"
               data-bs-toggle="tooltip" 
               data-bs-placement="top" 
               title="تعديل">
                <i class="fas fa-pen"></i>
            </a>
            <a href="delete_mosque.php?id=${row.registration_number}" 
               class="btn btn-sm btn-icon btn-danger rounded-circle"
               data-bs-toggle="tooltip" 
               data-bs-placement="top" 
               title="حذف"
               onclick="return confirm('هل أنت متأكد من حذف هذا المسجد؟')">
                <i class="fas fa-trash-alt"></i>
            </a>
        ` : '';
        
        html += `
        <tr class="animate__animated animate__fadeInUp" style="animation-delay: ${animationDelay}s">
            <td>
                <input type="checkbox" name="selected_mosques[]" value="${row.registration_number}" class="form-check-input mosque-checkbox">
            </td>
            <td class="fw-bold text-muted">${row.registration_number}</td>
            <td>
                <div class="d-flex align-items-center">
                    <i class="fas fa-mosque text-primary me-2"></i>
                    <span>${row.mosque_name}</span>
                </div>
            </td>
            <td class="mobile-hidden">
                <div class="d-flex align-items-center">
                    <i class="fas fa-map-marker-alt text-danger me-2"></i>
                    <small class="text-muted">${row.address}</small>
                </div>
            </td>
            <td><span class="badge bg-light text-dark">${row.national_code}</span></td>
            <td>
                <span class="d-flex justify-content-center" data-bs-toggle="tooltip" title="${row.friday_prayer == 'نعم' ? 'يوجد صلاة جمعة' : 'لا يوجد صلاة جمعة'}">
                    <i class="fas ${fridayIcon} fa-lg"></i>
                </span>
            </td>
            <td>
                <span class="d-flex justify-content-center" data-bs-toggle="tooltip" title="${row.status == 'مفتوح' ? 'مسجد مفتوح' :row.status =='مغلق' ? 'مسجد مغلق' : 'مسجد مفتوح بدون ترخيص'}">
                    <i class="fas ${statusIcon} fa-lg"></i>
                </span>
            </td>
            <td class="mobile-hidden">
            <span class="badge bg-info">
                ${row.construction_date ? new Date(row.construction_date).getFullYear() : 'غير محدد'}
            </span>
            </td>
            <td>
                <div class="d-flex align-items-center">
                <i class="fas fa-user fs-4 text-info me-2"></i>
                <span>${row.imam_name}</span>
                </div>
            </td>
            <td>
                <div class="d-flex align-items-center">
                <i class="fas fa-user-tie fs-4 text-warning me-2"></i>
                <span>${row.guide_imam}</span>
                </div>
            </td>
            <td><span class="badge bg-info">${row.community}</span></td>
            <td class="mobile-hidden">
                ${renderLocationCell(row)}
            </td>
            <td>
                <div class="d-flex gap-2">
                    <a href="#" 
                       class="btn btn-sm btn-icon btn-info rounded-circle view-mosque-btn"
                       data-bs-toggle="modal" 
                       data-bs-target="#mosqueDetailsModal"
                       data-mosque-id="${row.registration_number}"
                       data-bs-tooltip="tooltip" 
                       data-bs-placement="top" 
                       title="عرض التفاصيل">
                        <i class="fas fa-eye"></i>
                    </a>
                    ${adminButtons}
                </div>
            </td>
        </tr>`;
    });
    
    tbody.innerHTML = html;
    initializeTooltips();
    setupMosqueDetailsModal();
    setupBulkSelection();
    setupMapButtons();
}

function showSearchError(message) {
    document.querySelector('tbody').innerHTML = `
        <tr class="animate__animated animate__shakeX">
            <td colspan="12" class="text-center py-4 text-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>${message}
                <button class="btn btn-sm btn-outline-danger ms-2" onclick="retryLiveSearch()">
                    <i class="fas fa-sync-alt me-1"></i>إعادة المحاولة
                </button>
            </td>
        </tr>`;
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
            newPagination.className = 'mt-4 animate__animated animate__fadeIn';
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
            <a class="page-link" href="#" onclick="loadLiveSearchPage(${currentPage - 1})" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>`;
    }
    
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadLiveSearchPage(1)">1</a></li>`;
        if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${currentPage === i ? 'active' : ''}">
            <a class="page-link" href="#" onclick="loadLiveSearchPage(${i})">${i}</a>
        </li>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadLiveSearchPage(${totalPages})">${totalPages}</a></li>`;
    }
    
    if (currentPage < totalPages) {
        html += `<li class="page-item">
            <a class="page-link" href="#" onclick="loadLiveSearchPage(${currentPage + 1})" aria-label="Next">
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
    const modal = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: false
    });
    
    // Clear content when modal is hidden
    modalElement.addEventListener('hidden.bs.modal', function() {
        document.getElementById('modal-body-content').innerHTML = '';
        if (mosqueDetailsAbortController) {
            mosqueDetailsAbortController.abort();
        }
    });
    
    document.querySelectorAll('.view-mosque-btn').forEach(btn => {
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
        <div class="text-center py-5 animate__animated animate__fadeIn">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2">جاري تحميل بيانات المسجد</p>
        </div>`;
    
    if (mosqueDetailsAbortController) {
        mosqueDetailsAbortController.abort();
    }
    
    mosqueDetailsAbortController = new AbortController();
    
    fetch(`ajax/get_mosque_details.php?id=${mosqueId}&_=${Date.now()}`, {
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
    const constructionYear = mosque.construction_year || 'غير محدد';
    
    const formatPhone = (phone) => {
        if (!phone) return '';
        return phone.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1-$2-$3-$4-$5');
    };
    
    const getStatusBadgeClass = (status) => {
        switch(status) {
            case 'مفتوح': return 'bg-success';
            case 'مغلق': return 'bg-danger';
            default: return 'bg-warning';
        }
    };
    
    let adminHierarchy = '';
    if (mosque.admin_type === 'pashalik') {
        adminHierarchy = `
            <dt class="col-sm-4">الباشوية:</dt>
            <dd class="col-sm-8">${mosque.pashalik || 'غير محدد'}</dd>
        `;
    } else if (mosque.admin_type === 'circle') {
        adminHierarchy = `
            <dt class="col-sm-4">الدائرة:</dt>
            <dd class="col-sm-8">${mosque.circle || 'غير محدد'}</dd>
            <dt class="col-sm-4">القيادة:</dt>
            <dd class="col-sm-8">${mosque.leadership || 'غير محدد'}</dd>
        `;
    }
    
    // Add image display if exists
    const imageDisplay = mosque.main_image ? `
        <div class="text-center mb-4">
            <img src="${mosque.main_image}" class="img-fluid rounded" style="max-height: 250px;" alt="صورة المسجد">
        </div>
    ` : '<div class="alert alert-info text-center">لا توجد صورة متاحة للمسجد</div>';
    
    return `
    <div class="row animate__animated animate__fadeIn print-container">
        <div class="col-md-12">
            ${imageDisplay}
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4 animate__animated animate__fadeInLeft">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>المعلومات الأساسية</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">اسم المسجد:</dt>
                        <dd class="col-sm-8">${mosque.mosque_name || 'غير محدد'}</dd>
                        
                        <dt class="col-sm-4">الرمز الوطني:</dt>
                        <dd class="col-sm-8">${mosque.national_code || 'غير محدد'}</dd>
                        
                        <dt class="col-sm-4">العنوان:</dt>
                        <dd class="col-sm-8">${mosque.address || 'غير محدد'}</dd>
                        
                        <dt class="col-sm-4">سنة البناء:</dt>
                        <dd class="col-sm-8">${constructionYear}</dd>
                        
                        <dt class="col-sm-4">الحالة:</dt>
                        <dd class="col-sm-8">
                            <span class="badge ${getStatusBadgeClass(mosque.status)}">
                                ${mosque.status || 'غير محدد'}
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>
            
            <div class="card mb-4 animate__animated animate__fadeInLeft animate__delay-1s">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-building me-2"></i>المعلومات الإدارية</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        ${adminHierarchy}
                        <dt class="col-sm-4">الجماعة:</dt>
                        <dd class="col-sm-8">${mosque.community || 'غير محدد'}</dd>
                        
                        ${mosque.administrative_attachment ? `
                        <dt class="col-sm-4">الملحقة الإدارية:</dt>
                        <dd class="col-sm-8">${mosque.administrative_attachment || 'غير محدد'}</dd>
                        ` : ''}
                        
                        <dt class="col-sm-4">جهة الإنفاق:</dt>
                        <dd class="col-sm-8">${mosque.funding_source || 'غير محدد'}</dd>
                    </dl>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4 animate__animated animate__fadeInRight">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>طاقم المسجد</h6>
                    <div id="currentTabTitle" class="badge bg-primary">
                        الإمام
                    </div>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="staffTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="imam-tab" data-bs-toggle="tab" data-bs-target="#imam-tab-pane" type="button" 
                                    onclick="updateTabTitle('الإمام')">
                                الإمام
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="preacher-tab" data-bs-toggle="tab" data-bs-target="#preacher-tab-pane" type="button"
                                    onclick="updateTabTitle('الخطيب')">
                                الخطيب
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="muezzin-tab" data-bs-toggle="tab" data-bs-target="#muezzin-tab-pane" type="button"
                                    onclick="updateTabTitle('المؤذن')">
                                المؤذن
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content p-3 border border-top-0" id="staffTabsContent">
                        <div class="tab-pane fade show active" id="imam-tab-pane" role="tabpanel">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">الاسم:</dt>
                                <dd class="col-sm-8">${mosque.imam_name || 'غير محدد'}</dd>
                                
                                <dt class="col-sm-4">ر.ب.ت.و:</dt>
                                <dd class="col-sm-8">${mosque.imam_registration || 'غير محدد'}</dd>
                                
                                <dt class="col-sm-4">الهاتف:</dt>
                                <dd class="col-sm-8">${formatPhone(mosque.imam_phone) || 'غير محدد'}</dd>
                                
                                <dt class="col-sm-4">الإمام المرشد:</dt>
                                <dd class="col-sm-8">${mosque.guide_imam || 'غير محدد'}</dd>
                            </dl>
                        </div>
                        
                        <div class="tab-pane fade" id="preacher-tab-pane" role="tabpanel">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">الاسم:</dt>
                                <dd class="col-sm-8">${mosque.preacher_name || 'غير محدد'}</dd>
                                
                                <dt class="col-sm-4">ر.ب.ت.و:</dt>
                                <dd class="col-sm-8">${mosque.preacher_registration || 'غير محدد'}</dd>
                                
                                <dt class="col-sm-4">الهاتف:</dt>
                                <dd class="col-sm-8">${formatPhone(mosque.preacher_phone) || 'غير محدد'}</dd>
                            </dl>
                        </div>
                        
                        <div class="tab-pane fade" id="muezzin-tab-pane" role="tabpanel">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">الاسم:</dt>
                                <dd class="col-sm-8">${mosque.muezzin_name || 'غير محدد'}</dd>
                                
                                <dt class="col-sm-4">ر.ب.ت.و:</dt>
                                <dd class="col-sm-8">${mosque.muezzin_registration || 'غير محدد'}</dd>
                                
                                <dt class="col-sm-4">الهاتف:</dt>
                                <dd class="col-sm-8">${formatPhone(mosque.muezzin_phone) || 'غير محدد'}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card animate__animated animate__fadeInRight animate__delay-1s">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>الخدمات</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">صلاة الجمعة:</dt>
                        <dd class="col-sm-8">
                            <i class="fas ${mosque.friday_prayer === 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-secondary'} me-2"></i>
                            ${mosque.friday_prayer === 'نعم' ? 'نعم' : 'لا'}
                        </dd>
                        
                        <dt class="col-sm-4">تحفيظ القرآن الكريم:</dt>
                        <dd class="col-sm-8">
                            <i class="fas ${mosque.quran_memorization === 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-secondary'} me-2"></i>
                            ${mosque.quran_memorization === 'نعم' ? 'نعم' : 'لا'}
                        </dd>
                        
                        <dt class="col-sm-4">محو الأمية:</dt>
                        <dd class="col-sm-8">
                            <i class="fas ${mosque.literacy_program === 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-secondary'} me-2"></i>
                            ${mosque.literacy_program === 'نعم' ? 'نعم' : 'لا'}
                        </dd>
                        
                        <dt class="col-sm-4">الوعظ والإرشاد:</dt>
                        <dd class="col-sm-8">
                            <i class="fas ${mosque.guidance_program === 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-secondary'} me-2"></i>
                            ${mosque.guidance_program === 'نعم' ? 'نعم' : 'لا'}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mt-4 animate__animated animate__fadeInUp animate__delay-2s">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>ملاحظات</h6>
        </div>
        <div class="card-body">
            <p class="mb-0">${mosque.notes || 'لا توجد ملاحظات'}</p>
        </div>
    </div>`;
}

function initializeModalTabs() {
    const tabElms = document.querySelectorAll('#staffTabs button[data-bs-toggle="tab"]');
    tabElms.forEach(tabElm => new bootstrap.Tab(tabElm));
}

function showModalError(message) {
    document.getElementById('modal-body-content').innerHTML = `
        <div class="alert alert-danger d-flex align-items-center animate__animated animate__shakeX">
            <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
            <div>
                <h5 class="alert-heading mb-2">خطأ في تحميل البيانات</h5>
                <p class="mb-3">${message}</p>
                <button class="btn btn-sm btn-outline-danger" onclick="retryLastRequest()">
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
// Bulk selection
function setupBulkSelection() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.mosque-checkbox');
    const deleteBtn = document.getElementById('deleteSelected');
    const selectedCount = document.getElementById('selectedCount');
    
    // Only setup if elements exist
    if (!selectAll || !deleteBtn || !selectedCount) {
        return;
    }
    
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        updateSelectionUI();
    });
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            selectAll.checked = [...checkboxes].every(cb => cb.checked);
            updateSelectionUI();
        });
    });
    
    deleteBtn.addEventListener('click', function() {
        const selected = [...checkboxes]
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);
        
        if (selected.length > 0 && confirm(`هل أنت متأكد من حذف ${selected.length} مسجد(اً)؟`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_mosque.php';

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
            }
            
            selected.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_mosques[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    });
    
    function updateSelectionUI() {
        const count = [...checkboxes].filter(checkbox => checkbox.checked).length;
        selectedCount.textContent = count;
        deleteBtn.disabled = count === 0;
    }
}

// Initialize tooltips
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
}

// Tab title update
function updateTabTitle(title) {
    document.getElementById('currentTabTitle').textContent = title;
}

// Setup search form
function setupSearch() {
    const clearSearch = document.getElementById('clearSearch');
    const liveSearch = document.getElementById('liveSearch');
    
    clearSearch.addEventListener('click', function() {
        liveSearch.value = '';
        document.getElementById('searchForm').submit();
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
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'click hover'
            });
        });
    } else {
        document.body.classList.remove('mobile-view');
    }
}


// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {

    
    initializeTooltips();
    setupMosqueDetailsModal();
    setupBulkSelection();
    setupSearch();
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

    document.getElementById('liveSearch').addEventListener('input', function() {
        if (window.innerWidth < 768) {
            this.style.font = '16px';
        }
    });

    // Live search setup with null check
const liveSearch = document.getElementById('liveSearch');
if (liveSearch) {
    let searchTimeout;
    let previousSearchTerm = liveSearch.value.trim(); // Track previous value
    
    liveSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = this.value.trim();
        
        // Only submit if the field was cleared AND it previously had content
        if (searchTerm.length === 0 && previousSearchTerm.length > 0) {
            // Small delay to ensure user finished clearing
            searchTimeout = setTimeout(() => {
                document.getElementById('searchForm').submit();
            }, 300);
            previousSearchTerm = '';
            return;
        }
        
        // If there's content, do live search
        if (searchTerm.length > 0) {
            showLoadingIndicator();
            searchTimeout = setTimeout(() => {
                fetchLiveSearchResults(searchTerm);
                previousSearchTerm = searchTerm;
            }, 500);
        } else {
            // Field is empty and was already empty, do nothing
            previousSearchTerm = '';
        }
    });
    
    // Also handle the clear button click
    const clearSearch = document.getElementById('clearSearch');
    if (clearSearch) {
        clearSearch.addEventListener('click', function() {
            liveSearch.value = '';
            previousSearchTerm = '';
            document.getElementById('searchForm').submit();
        });
    }
}
    
    // Form submission handler
    document.getElementById('searchForm').addEventListener('submit', handleSearchSubmit);

        // Community filter change event
        const communityFilter = document.querySelector('select[name="community"]');
        const statusFilter = document.querySelector('select[name="status"]');
        const fridayFilter = document.querySelector('select[name="friday_prayer"]');
        const guideImamFilter = document.querySelector('select[name="guide_imam"]');

        [communityFilter, statusFilter, fridayFilter,guideImamFilter].forEach(filter => {
            if (filter) {
                filter.addEventListener('change', function() {
                    const searchTerm = document.getElementById('liveSearch').value.trim();
                    if (searchTerm) {
                        showLoadingIndicator();
                        fetchLiveSearchResults(searchTerm);
                    } else {
                        // If no search term, submit the form normally to filter by selected filters
                        document.getElementById('searchForm').submit();
                    }
                });
            }
        });

        document.querySelectorAll('.view-on-map').forEach(button => {
        button.addEventListener('click', function() {
            const lat = this.getAttribute('data-lat');
            const lng = this.getAttribute('data-lng');
            const mosqueName = this.getAttribute('data-mosque');
            
            // Open in new tab with Google Maps
            const url = `https://www.google.com/maps?q=${lat},${lng}&z=15`;
            window.open(url, '_blank');
        });
    });
});