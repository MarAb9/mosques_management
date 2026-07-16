import { MarkerClusterer, SuperClusterAlgorithm } from '@googlemaps/markerclusterer';

const mapPageData = (() => { try { return JSON.parse(document.getElementById('mapPageData')?.textContent || '{}'); } catch (_) { return {}; } })();
let map;
let markers = [];
let markerClusterer;
let selectedMarker;
let selectedTrigger;
let mosquesData = mapPageData.mosques || [];
let allMosquesData = mapPageData.allMosques || [];
const mapDefaults = mapPageData.mapDefaults || { latitude: 34.6814, longitude: -1.9086, zoom: 9 };
const hasGoogleMapsKey = Boolean(mapPageData.hasGoogleMapsKey);

let activeFilters = {
    search: '',
    community: '',
    status: '',
    friday: ''
};
let currentSort = 'name';

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function statusColor(status) {
    const value = String(status || '');
    if (value.includes('مغلق')) return '#dc3545';
    if (value.includes('دون') || value.includes('ترخيص')) return '#ffc107';
    if (value.includes('مفتوح')) return '#198754';
    return '#6c757d';
}

function statusBadgeClass(status) {
    const value = String(status || '');
    if (value.includes('مغلق')) return 'danger';
    if (value.includes('دون') || value.includes('ترخيص')) return 'warning';
    if (value.includes('مفتوح')) return 'success';
    return 'secondary';
}

function markerIcon(status, isSelected = false) {
    return {
        path: google.maps.SymbolPath.CIRCLE,
        fillColor: statusColor(status),
        fillOpacity: 0.95,
        strokeColor: isSelected ? '#b88a3b' : '#ffffff',
        strokeWeight: isSelected ? 4 : 2,
        scale: isSelected ? 12 : 9
    };
}

window.initGoogleMosqueMap = function initGoogleMosqueMap() {
    try {
        hideMapError();
        showMapLoading();

        const center = {
            lat: Number(mapDefaults.latitude) || 34.6814,
            lng: Number(mapDefaults.longitude) || -1.9086
        };

        map = new google.maps.Map(document.getElementById('map'), {
            center,
            zoom: Number(mapDefaults.zoom) || 9,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            gestureHandling: 'greedy'
        });
        addMosqueMarkers();
        setupMapButtons();
        hideMapLoading();
    } catch (error) {
        console.error('Google Maps initialization error:', error);
        hideMapLoading();
        showMapError('تعذر تحميل خرائط Google. تحقق من المفتاح أو اتصال الشبكة.');
    }
};

function addMosqueMarkers() {
    clearMarkers();
    const bounds = new google.maps.LatLngBounds();
    let validMarkers = 0;

    allMosquesData.forEach(mosque => {
        const lat = Number.parseFloat(mosque.latitude);
        const lng = Number.parseFloat(mosque.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const marker = new google.maps.Marker({
            position: { lat, lng },
            title: String(mosque.mosque_name || ''),
            icon: markerIcon(mosque.status),
            optimized: true
        });

        marker.filteredVisible = true;
        marker.mosqueData = {
            id: mosque.registration_number,
            national_code: mosque.national_code,
            name: mosque.mosque_name,
            address: mosque.address,
            imam: mosque.imam_name,
            guide_imam: mosque.guide_imam,
            community: mosque.community,
            status: mosque.status,
            friday: mosque.friday_prayer === 'نعم' ? '1' : '0',
            lat,
            lng,
            raw: mosque
        };
        marker.addListener('click', () => openMarker(marker));

        markers.push(marker);
        bounds.extend(marker.getPosition());
        validMarkers++;
    });

    if (validMarkers > 0) {
        map.fitBounds(bounds, { top: 50, right: 50, bottom: 50, left: 50 });
    }

    markerClusterer = new MarkerClusterer({
        map,
        markers,
        algorithm: new SuperClusterAlgorithm({ radius: 64, maxZoom: 15 }),
        onClusterClick: (_event, cluster, clusterMap) => {
            if (cluster.bounds) clusterMap.fitBounds(cluster.bounds, { top: 64, right: 64, bottom: 64, left: 64 });
        }
    });
}

function refreshMarkerCluster() {
    if (!markerClusterer) return;

    markerClusterer.clearMarkers(true);
    markers.forEach(marker => marker.setMap(null));
    markerClusterer.addMarkers(markers.filter(marker => marker.filteredVisible && marker !== selectedMarker));

    if (selectedMarker?.filteredVisible) selectedMarker.setMap(map);
}

function applyFilters() {
    if (!map) return;

    const filteredMosques = [];
    markers.forEach(marker => {
        const data = marker.mosqueData;
        const haystack = [data.name, data.address, data.imam, data.community, data.national_code]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        let showMarker = true;

        if (activeFilters.search && !haystack.includes(activeFilters.search)) showMarker = false;
        if (activeFilters.community && data.community !== activeFilters.community) showMarker = false;
        if (activeFilters.status && data.status !== activeFilters.status) showMarker = false;
        if (activeFilters.friday !== '' && data.friday !== activeFilters.friday) showMarker = false;

        marker.filteredVisible = showMarker;
        if (showMarker) filteredMosques.push(data);
    });

    updateActiveFiltersDisplay();
    updateSidebarList(filteredMosques);
    fitToVisibleMarkers(false);
    if (selectedMarker && !selectedMarker.filteredVisible) {
        closeSelectedMosque({ restoreFocus: false });
    }
    refreshMarkerCluster();
}

function fitToVisibleMarkers(force = true) {
    if (!map) return;
    const visibleMarkers = markers.filter(marker => marker.filteredVisible);
    if (visibleMarkers.length === 0) return;

    const bounds = new google.maps.LatLngBounds();
    visibleMarkers.forEach(marker => bounds.extend(marker.getPosition()));

    if (visibleMarkers.length === 1) {
        map.setCenter(visibleMarkers[0].getPosition());
        if (force) map.setZoom(16);
        return;
    }

    map.fitBounds(bounds, { top: 50, right: 50, bottom: 50, left: 50 });
}

function setupSearchFunctionality() {
    const globalSearch = document.getElementById('globalSearch');
    const clearGlobalSearch = document.getElementById('clearGlobalSearch');
    const searchSuggestions = document.getElementById('searchSuggestions');
    if (!globalSearch || !clearGlobalSearch || !searchSuggestions) return;

    let searchTimeout;
    globalSearch.addEventListener('input', function(event) {
        const searchTerm = event.target.value.trim().toLowerCase();
        activeFilters.search = searchTerm;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (searchTerm.length >= 2) {
                showSearchSuggestions(searchTerm);
            } else {
                searchSuggestions.innerHTML = '';
                searchSuggestions.classList.remove('show');
            }
            applyFilters();
        }, 250);
    });

    clearGlobalSearch.addEventListener('click', function() {
        globalSearch.value = '';
        activeFilters.search = '';
        searchSuggestions.innerHTML = '';
        searchSuggestions.classList.remove('show');
        applyFilters();
        globalSearch.focus();
    });

    document.addEventListener('click', function(event) {
        if (!globalSearch.contains(event.target) && !searchSuggestions.contains(event.target)) {
            searchSuggestions.classList.remove('show');
        }
    });
}

function showSearchSuggestions(searchTerm) {
    const searchSuggestions = document.getElementById('searchSuggestions');
    const suggestions = allMosquesData.filter(mosque => {
        return [mosque.mosque_name, mosque.address, mosque.imam_name, mosque.national_code]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(searchTerm);
    }).slice(0, 6);

    if (suggestions.length === 0) {
        searchSuggestions.innerHTML = '<div class="text-muted text-center p-2">لا توجد نتائج مقترحة</div>';
        searchSuggestions.classList.add('show');
        return;
    }

    searchSuggestions.innerHTML = '<div class="suggestion-header text-muted small mb-2">نتائج مقترحة:</div>' + suggestions.map(mosque => {
        const lat = Number.parseFloat(mosque.latitude);
        const lng = Number.parseFloat(mosque.longitude);
        return `
            <button type="button" class="suggestion-item p-2 border-bottom cursor-pointer text-start w-100 bg-white border-0"
                    data-lat="${Number.isFinite(lat) ? lat : ''}"
                    data-lng="${Number.isFinite(lng) ? lng : ''}"
                    data-name="${escapeHtml(mosque.mosque_name)}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>${highlightText(mosque.mosque_name, searchTerm)}</strong>
                        <div class="text-muted small">${highlightText(mosque.address, searchTerm)}</div>
                    </div>
                    <span class="badge bg-${statusBadgeClass(mosque.status)}">${escapeHtml(mosque.status)}</span>
                </div>
            </button>`;
    }).join('');
    searchSuggestions.classList.add('show');

    searchSuggestions.querySelectorAll('.suggestion-item').forEach(item => {
        item.addEventListener('click', function() {
            zoomToMosque(Number.parseFloat(this.dataset.lat || ''), Number.parseFloat(this.dataset.lng || ''), this.dataset.name || '', this);
            searchSuggestions.classList.remove('show');
        });
    });
}

function setupFilters() {
    const bindings = [
        ['communityFilter', 'community'],
        ['statusFilter', 'status'],
        ['fridayFilter', 'friday']
    ];
    bindings.forEach(([elementId, filterKey]) => {
        const element = document.getElementById(elementId);
        if (element) element.addEventListener('change', function() {
            activeFilters[filterKey] = this.value;
            applyFilters();
        });
    });

    document.getElementById('clearAllFilters')?.addEventListener('click', resetAllFilters);
    document.addEventListener('click', function(event) {
        const button = event.target instanceof Element ? event.target.closest('[data-filter]') : null;
        if (button) removeFilter(button.dataset.filter);
    });
}

function updateActiveFiltersDisplay() {
    const container = document.getElementById('activeFiltersContainer');
    const filtersDiv = document.getElementById('activeFilters');
    if (!container || !filtersDiv) return;

    filtersDiv.innerHTML = '';
    let hasActiveFilters = false;
    const labels = {
        search: 'بحث',
        community: 'الجماعة',
        status: 'الحالة',
        friday: 'صلاة الجمعة'
    };

    Object.entries(activeFilters).forEach(([key, value]) => {
        if (!value) return;
        hasActiveFilters = true;
        const filterBadge = document.createElement('span');
        filterBadge.className = 'badge bg-primary-subtle text-primary border';
        filterBadge.appendChild(document.createTextNode(`${labels[key] || key}: ${key === 'friday' ? (value === '1' ? 'نعم' : 'لا') : value} `));

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn-close btn-close-sm ms-1';
        removeButton.dataset.filter = key;
        removeButton.setAttribute('aria-label', 'إزالة عامل التصفية');
        filterBadge.appendChild(removeButton);
        filtersDiv.appendChild(filterBadge);
    });

    container.hidden = !hasActiveFilters;
}

function removeFilter(filterKey) {
    activeFilters[filterKey] = '';
    const inputMap = {
        search: 'globalSearch',
        community: 'communityFilter',
        status: 'statusFilter',
        friday: 'fridayFilter'
    };
    const element = document.getElementById(inputMap[filterKey]);
    if (element) element.value = '';
    document.getElementById('searchSuggestions')?.classList.remove('show');
    applyFilters();
}

function resetAllFilters() {
    activeFilters = { search: '', community: '', status: '', friday: '' };
    ['globalSearch', 'communityFilter', 'statusFilter', 'fridayFilter'].forEach(id => {
        const element = document.getElementById(id);
        if (element) element.value = '';
    });
    document.getElementById('searchSuggestions')?.classList.remove('show');
    applyFilters();
}

function updateSidebarList(filteredMosques) {
    const mosquesList = document.getElementById('mosquesList');
    const sidebarCount = document.getElementById('sidebarMosqueCount');
    const resultsSummary = document.getElementById('resultsSummary');
    if (!mosquesList || !sidebarCount || !resultsSummary) return;

    if (filteredMosques.length === 0) {
        mosquesList.innerHTML = `
            <div class="empty-state text-center py-5 text-muted">
                <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
                <p class="mb-0">لا توجد نتائج للبحث</p>
                <small class="text-muted">جرب تعديل معايير البحث</small>
            </div>`;
        sidebarCount.textContent = '0';
        resultsSummary.textContent = 'لا توجد نتائج';
        return;
    }

    mosquesList.innerHTML = filteredMosques.map((mosque, index) => {
        const safeId = escapeHtml(mosque.id);
        const safeCode = encodeURIComponent(String(mosque.national_code || mosque.id || ''));
        return `
            <div class="mosque-list-item p-3 border-bottom" data-mosque-id="${safeId}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0 text-primary cursor-pointer mosque-name fw-bold">${index + 1}. ${highlightText(mosque.name, activeFilters.search)}</h6>
                    <span class="badge bg-${statusBadgeClass(mosque.status)} px-2 py-1">${escapeHtml(mosque.status)}</span>
                </div>
                <p class="text-muted small mb-1"><i class="fas fa-map-marker-alt me-1"></i>${highlightText(mosque.address, activeFilters.search)}</p>
                <p class="text-muted small mb-1"><i class="fas fa-user me-1"></i>${escapeHtml(mosque.imam || 'غير محدد')}</p>
                ${mosque.community ? `<p class="text-muted small mb-2"><i class="fas fa-users me-1"></i>${escapeHtml(mosque.community)}</p>` : ''}
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-fill zoom-to-mosque d-flex align-items-center justify-content-center"
                            data-lat="${mosque.lat}" data-lng="${mosque.lng}" data-name="${escapeHtml(mosque.name)}">
                        <i class="fas fa-search-location me-1"></i>تحديد
                    </button>
                    <a href="mosques.php?national_code=${safeCode}&from_map=${safeCode}" class="btn btn-sm btn-outline-info d-flex align-items-center justify-content-center" title="عرض التفاصيل">
                        <i class="fas fa-info-circle"></i>
                    </a>
                </div>
            </div>`;
    }).join('');

    sidebarCount.textContent = String(filteredMosques.length);
    resultsSummary.textContent = `عرض ${filteredMosques.length} مسجد`;
    reattachZoomEventListeners();
}

function setupMapButtons() {
    document.getElementById('fitToMarkers')?.addEventListener('click', () => fitToVisibleMarkers(true));
    document.getElementById('refreshMap')?.addEventListener('click', () => window.location.reload());
    document.getElementById('retryMap')?.addEventListener('click', () => window.location.reload());
    reattachZoomEventListeners();
}

function reattachZoomEventListeners() {
    document.querySelectorAll('.zoom-to-mosque').forEach(button => {
        button.addEventListener('click', function() {
            zoomToMosque(Number.parseFloat(this.dataset.lat || ''), Number.parseFloat(this.dataset.lng || ''), this.dataset.name || '');
        });
    });
    document.querySelectorAll('.mosque-list-item').forEach(item => {
        item.addEventListener('click', function(event) {
            if (event.target instanceof Element && event.target.closest('a,button')) return;
            const id = this.dataset.mosqueId;
            const marker = markers.find(candidate => String(candidate.mosqueData.id) === String(id));
            if (marker) openMarker(marker, this);
        });
    });
}

function openMarker(marker, trigger = null) {
    if (!map || !marker) return;

    if (selectedMarker && selectedMarker !== marker) {
        selectedMarker.setIcon(markerIcon(selectedMarker.mosqueData.status));
        selectedMarker.setZIndex(undefined);
    }

    selectedMarker = marker;
    selectedTrigger = trigger instanceof HTMLElement ? trigger : null;
    marker.setIcon(markerIcon(marker.mosqueData.status, true));
    marker.setZIndex(google.maps.Marker.MAX_ZINDEX + 1);
    refreshMarkerCluster();
    renderSelectedMosque(marker.mosqueData);
    highlightSidebarItem(marker.mosqueData.id);
}

function zoomToMosque(lat, lng, name, trigger = null) {
    if (!map || !Number.isFinite(lat) || !Number.isFinite(lng)) return;
    map.setCenter({ lat, lng });
    map.setZoom(16);
    const marker = markers.find(candidate => Math.abs(candidate.getPosition().lat() - lat) < 0.000001 && Math.abs(candidate.getPosition().lng() - lng) < 0.000001);
    if (marker) openMarker(marker, trigger);
}

function highlightSidebarItem(id) {
    document.querySelectorAll('.mosque-list-item').forEach(item => {
        item.classList.remove('is-active');
        item.removeAttribute('aria-current');
    });
    const item = document.querySelector(`.mosque-list-item[data-mosque-id="${CSS.escape(String(id))}"]`);
    if (item) {
        item.classList.add('is-active');
        item.setAttribute('aria-current', 'true');
        revealListItem(item);
    }
}

function revealListItem(item) {
    const list = document.getElementById('mosquesList');
    if (!list) return;
    const itemTop = item.offsetTop;
    const itemBottom = itemTop + item.offsetHeight;
    if (itemTop < list.scrollTop) list.scrollTo({ top: itemTop, behavior: 'smooth' });
    else if (itemBottom > list.scrollTop + list.clientHeight) {
        list.scrollTo({ top: itemBottom - list.clientHeight, behavior: 'smooth' });
    }
}

function setPanelText(id, value, fallback = 'غير محدد') {
    const element = document.getElementById(id);
    if (element) element.textContent = String(value || fallback);
}

function renderSelectedMosque(mosque) {
    const data = mosque.raw || mosque;
    const nationalCode = data.national_code || mosque.national_code || mosque.id || '';
    const codeQuery = encodeURIComponent(String(nationalCode));
    const panel = document.getElementById('selectedMosquePanel');
    if (!panel) return;

    setPanelText('selectedMosqueTitle', data.mosque_name || mosque.name);
    setPanelText('selectedMosqueAddress', data.address || mosque.address);
    setPanelText('selectedMosqueImam', data.imam_name || mosque.imam);
    setPanelText('selectedMosqueGuideImam', data.guide_imam || mosque.guide_imam);
    setPanelText('selectedMosqueCommunity', data.community || mosque.community);
    setPanelText('selectedMosqueCode', nationalCode);
    setPanelText('selectedMosqueStatus', data.status || mosque.status);
    setPanelText('selectedMosqueFriday', data.friday_prayer === 'نعم' || mosque.friday === '1' ? 'نعم' : 'لا');

    const detailsLink = document.getElementById('selectedMosqueDetails');
    if (detailsLink) detailsLink.href = `mosques.php?national_code=${codeQuery}&from_map=${codeQuery}`;
    const mapsButton = document.getElementById('selectedMosqueGoogleMaps');
    if (mapsButton) {
        mapsButton.dataset.lat = String(mosque.lat);
        mapsButton.dataset.lng = String(mosque.lng);
    }
    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'false');
}

function closeSelectedMosque({ restoreFocus = true } = {}) {
    const previousMarker = selectedMarker;
    const previousTrigger = selectedTrigger;
    selectedMarker = undefined;
    selectedTrigger = undefined;

    if (previousMarker) {
        previousMarker.setIcon(markerIcon(previousMarker.mosqueData.status));
        previousMarker.setZIndex(undefined);
    }

    const panel = document.getElementById('selectedMosquePanel');
    if (panel) {
        panel.hidden = true;
        panel.setAttribute('aria-hidden', 'true');
    }
    document.querySelectorAll('.mosque-list-item').forEach(item => {
        item.classList.remove('is-active');
        item.removeAttribute('aria-current');
    });
    refreshMarkerCluster();

    if (!restoreFocus) return;
    const fallback = previousMarker
        ? document.querySelector(`.mosque-list-item[data-mosque-id=${CSS.escape(String(previousMarker.mosqueData.id))}] .zoom-to-mosque`)
        : null;
    const focusTarget = previousTrigger?.isConnected ? previousTrigger : fallback;
    focusTarget?.focus({ preventScroll: true });
}

function clearMarkers() {
    markerClusterer?.clearMarkers(true);
    markerClusterer?.setMap(null);
    markerClusterer = undefined;
    selectedMarker = undefined;
    markers.forEach(marker => marker.setMap(null));
    markers = [];
}

function openInGoogleMaps(lat, lng) {
    window.open(`https://www.google.com/maps?q=${lat},${lng}&z=17`, '_blank', 'noopener,noreferrer');
}

function showMapLoading() {
    document.getElementById('mapLoading')?.classList.remove('d-none');
}

function hideMapLoading() {
    document.getElementById('mapLoading')?.classList.add('d-none');
}

function showMapError(message) {
    const mapError = document.getElementById('mapError');
    if (!mapError) return;
    if (message) {
        const paragraph = mapError.querySelector('p');
        if (paragraph) paragraph.textContent = message;
    }
    mapError.classList.remove('d-none');
}

function hideMapError() {
    document.getElementById('mapError')?.classList.add('d-none');
}

function highlightText(text, searchTerm) {
    const safeText = escapeHtml(text);
    if (!searchTerm || !text) return safeText;
    const regex = new RegExp(`(${escapeRegExp(searchTerm)})`, 'gi');
    return safeText.replace(regex, '<mark class="bg-warning">$1</mark>');
}

function bootMapPage() {
    setupSearchFunctionality();
    setupFilters();

    document.addEventListener('click', function(event) {
        const target = event.target instanceof Element ? event.target.closest('.js-open-google-maps') : null;
        if (!target) return;
        const lat = Number.parseFloat(target.dataset.lat || '');
        const lng = Number.parseFloat(target.dataset.lng || '');
        if (Number.isFinite(lat) && Number.isFinite(lng)) openInGoogleMaps(lat, lng);
    });

    document.getElementById('selectedMosqueClose')?.addEventListener('click', () => closeSelectedMosque());
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && selectedMarker) closeSelectedMosque();
    });

    if (!hasGoogleMapsKey) {
        hideMapLoading();
        showMapError('مفتاح Google Maps غير مضبوط بعد. أضف GOOGLE_MAPS_API_KEY في ملف .env لتفعيل الخريطة.');
    }
}

document.addEventListener('DOMContentLoaded', bootMapPage);
