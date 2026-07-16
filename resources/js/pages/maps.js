import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const SOURCE_ID = 'mosques';
const SELECTED_SOURCE_ID = 'selected-mosque';
const CLUSTER_LAYER_ID = 'mosque-clusters';
const CLUSTER_COUNT_LAYER_ID = 'mosque-cluster-count';
const POINT_LAYER_ID = 'mosque-points';
const SELECTED_HALO_LAYER_ID = 'selected-mosque-halo';
const SELECTED_POINT_LAYER_ID = 'selected-mosque-point';
const EMPTY_COLLECTION = { type: 'FeatureCollection', features: [] };

const mapPageData = (() => {
    try {
        return JSON.parse(document.getElementById('mapPageData')?.textContent || '{}');
    } catch (_) {
        return {};
    }
})();

const mapDefaults = mapPageData.mapDefaults || { latitude: 34.6814, longitude: -1.9086, zoom: 9 };
const mapConfig = mapPageData.mapConfig || {
    provider: 'maplibre',
    styleUrl: 'https://tiles.openfreemap.org/styles/liberty'
};

let map;
let mapReady = false;
let selectedMosqueId = '';
let selectedTrigger;
let filteredMosquesData = [];
let activeFilters = { search: '', community: '', status: '', friday: '' };

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
    if (value.includes('مغلق')) return '#c23a3a';
    if (value.includes('دون') || value.includes('ترخيص')) return '#d89d18';
    if (value.includes('مفتوح')) return '#198754';
    return '#68756f';
}

function statusBadgeClass(status) {
    const value = String(status || '');
    if (value.includes('مغلق')) return 'danger';
    if (value.includes('دون') || value.includes('ترخيص')) return 'warning';
    if (value.includes('مفتوح')) return 'success';
    return 'secondary';
}

function normalizeGeoJson(value) {
    const features = Array.isArray(value?.features) ? value.features : [];
    return {
        type: 'FeatureCollection',
        features: features.flatMap(feature => {
            const coordinates = feature?.geometry?.coordinates;
            const longitude = Number(coordinates?.[0]);
            const latitude = Number(coordinates?.[1]);
            if (!Number.isFinite(longitude) || !Number.isFinite(latitude) || longitude < -180 || longitude > 180 || latitude < -90 || latitude > 90) {
                return [];
            }

            const properties = feature.properties || {};
            const id = String(properties.registration_number ?? feature.id ?? '');
            if (!id) return [];

            return [{
                type: 'Feature',
                id,
                geometry: { type: 'Point', coordinates: [longitude, latitude] },
                properties: {
                    registration_number: id,
                    national_code: String(properties.national_code || ''),
                    mosque_name: String(properties.mosque_name || ''),
                    status: String(properties.status || ''),
                    address: String(properties.address || ''),
                    community: String(properties.community || ''),
                    imam_name: String(properties.imam_name || ''),
                    guide_imam: String(properties.guide_imam || ''),
                    friday_prayer: String(properties.friday_prayer || ''),
                    marker_color: statusColor(properties.status)
                }
            }];
        })
    };
}

const mosqueGeoJson = normalizeGeoJson(mapPageData.mosqueGeoJson);
const allMosquesData = mosqueGeoJson.features.map(feature => {
    const properties = feature.properties;
    return {
        id: properties.registration_number,
        national_code: properties.national_code,
        name: properties.mosque_name,
        status: properties.status,
        address: properties.address,
        community: properties.community,
        imam: properties.imam_name,
        guide_imam: properties.guide_imam,
        friday: properties.friday_prayer === 'نعم' ? '1' : '0',
        friday_prayer: properties.friday_prayer,
        lat: feature.geometry.coordinates[1],
        lng: feature.geometry.coordinates[0],
        feature
    };
});

function featureCollection(mosques) {
    return { type: 'FeatureCollection', features: mosques.map(mosque => mosque.feature) };
}

function selectedMosque() {
    return allMosquesData.find(mosque => String(mosque.id) === String(selectedMosqueId));
}

function initializeMap() {
    const mapElement = document.getElementById('map');
    if (!mapElement) return;

    showMapLoading();
    hideMapError();
    mapElement.dataset.provider = String(mapConfig.provider || 'maplibre');
    mapElement.dataset.styleUrl = String(mapConfig.styleUrl || '');
    mapElement.dataset.mapReady = 'false';

    try {
        map = new maplibregl.Map({
            container: mapElement,
            style: String(mapConfig.styleUrl || 'https://tiles.openfreemap.org/styles/liberty'),
            center: [Number(mapDefaults.longitude) || -1.9086, Number(mapDefaults.latitude) || 34.6814],
            zoom: Number(mapDefaults.zoom) || 9,
            attributionControl: false,
            cooperativeGestures: false
        });

        map.addControl(new maplibregl.NavigationControl({ showCompass: true, showZoom: true, visualizePitch: true }), 'top-right');
        map.addControl(new maplibregl.FullscreenControl({ container: document.querySelector('.map-canvas-card') || mapElement }), 'top-right');
        map.addControl(new maplibregl.AttributionControl({
            compact: false,
            customAttribution: '<a href="https://openfreemap.org/" target="_blank" rel="noopener noreferrer">OpenFreeMap</a>'
        }), 'bottom-right');

        map.on('load', () => {
            addMapSourcesAndLayers();
            mapReady = true;
            mapElement.dataset.mapReady = 'true';
            applyFilters({ fit: false });
            fitToVisibleMosques(true);
            selectMosqueFromUrl();
            map.once('idle', hideMapLoading);
            window.setTimeout(hideMapLoading, 800);
        });

        map.on('error', () => {
            if (!mapReady) {
                hideMapLoading();
                showMapError('تعذر تحميل نمط الخريطة المفتوح. تحقق من اتصال الشبكة ثم أعد المحاولة.');
            }
        });
    } catch (_) {
        hideMapLoading();
        showMapError('تعذر تشغيل الخريطة على هذا المتصفح.');
    }
}

function addMapSourcesAndLayers() {
    map.addSource(SOURCE_ID, {
        type: 'geojson',
        data: mosqueGeoJson,
        cluster: true,
        clusterRadius: 64,
        clusterMaxZoom: 14
    });
    map.addSource(SELECTED_SOURCE_ID, { type: 'geojson', data: EMPTY_COLLECTION });

    map.addLayer({
        id: CLUSTER_LAYER_ID,
        type: 'circle',
        source: SOURCE_ID,
        filter: ['has', 'point_count'],
        paint: {
            'circle-color': ['step', ['get', 'point_count'], '#176b57', 25, '#0f4f41', 75, '#b88a3b'],
            'circle-radius': ['step', ['get', 'point_count'], 18, 25, 23, 75, 29],
            'circle-stroke-color': '#ffffff',
            'circle-stroke-width': 2,
            'circle-opacity': 0.94
        }
    });
    map.addLayer({
        id: CLUSTER_COUNT_LAYER_ID,
        type: 'symbol',
        source: SOURCE_ID,
        filter: ['has', 'point_count'],
        layout: { 'text-field': ['get', 'point_count_abbreviated'], 'text-font': ['Noto Sans Bold'], 'text-size': 12 },
        paint: { 'text-color': '#ffffff' }
    });
    map.addLayer({
        id: POINT_LAYER_ID,
        type: 'circle',
        source: SOURCE_ID,
        filter: ['!', ['has', 'point_count']],
        paint: {
            'circle-color': ['get', 'marker_color'],
            'circle-radius': 8,
            'circle-stroke-color': '#ffffff',
            'circle-stroke-width': 2
        }
    });
    map.addLayer({
        id: SELECTED_HALO_LAYER_ID,
        type: 'circle',
        source: SELECTED_SOURCE_ID,
        paint: { 'circle-color': '#ffffff', 'circle-radius': 15, 'circle-stroke-color': '#b88a3b', 'circle-stroke-width': 4 }
    });
    map.addLayer({
        id: SELECTED_POINT_LAYER_ID,
        type: 'circle',
        source: SELECTED_SOURCE_ID,
        paint: { 'circle-color': ['get', 'marker_color'], 'circle-radius': 8 }
    });

    map.on('click', CLUSTER_LAYER_ID, event => {
        const feature = event.features?.[0];
        if (feature) expandCluster(feature);
    });
    map.on('click', POINT_LAYER_ID, event => {
        const id = event.features?.[0]?.properties?.registration_number;
        if (id !== undefined) selectMosqueById(String(id), null, false);
    });

    [CLUSTER_LAYER_ID, POINT_LAYER_ID].forEach(layerId => {
        map.on('mouseenter', layerId, () => { map.getCanvas().style.cursor = 'pointer'; });
        map.on('mouseleave', layerId, () => { map.getCanvas().style.cursor = ''; });
    });
}

async function expandCluster(feature) {
    const source = map?.getSource(SOURCE_ID);
    const clusterId = Number(feature?.properties?.cluster_id);
    const coordinates = feature?.geometry?.coordinates;
    if (!source || !Number.isFinite(clusterId) || !Array.isArray(coordinates)) return null;

    const zoom = await source.getClusterExpansionZoom(clusterId);
    const before = map.getZoom();
    map.easeTo({ center: coordinates, zoom, duration: 650 });
    const mapElement = document.getElementById('map');
    if (mapElement) mapElement.dataset.lastClusterZoom = String(zoom);
    return { before, zoom, clusterId };
}

function applyFilters({ fit = true } = {}) {
    filteredMosquesData = allMosquesData.filter(mosque => {
        const haystack = [mosque.name, mosque.address, mosque.national_code, mosque.imam, mosque.community]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        if (activeFilters.search && !haystack.includes(activeFilters.search)) return false;
        if (activeFilters.community && mosque.community !== activeFilters.community) return false;
        if (activeFilters.status && mosque.status !== activeFilters.status) return false;
        if (activeFilters.friday !== '' && mosque.friday !== activeFilters.friday) return false;
        return true;
    });

    if (mapReady) {
        map.getSource(SOURCE_ID)?.setData(featureCollection(filteredMosquesData));
        const mapElement = document.getElementById('map');
        if (mapElement) mapElement.dataset.visibleCount = String(filteredMosquesData.length);
    }

    updateActiveFiltersDisplay();
    updateSidebarList(filteredMosquesData);

    if (selectedMosqueId && !filteredMosquesData.some(mosque => String(mosque.id) === String(selectedMosqueId))) {
        closeSelectedMosque({ restoreFocus: false });
    }
    if (fit && mapReady) fitToVisibleMosques(false);
}

function fitToVisibleMosques(animated = true) {
    if (!mapReady || filteredMosquesData.length === 0) return;

    if (filteredMosquesData.length === 1) {
        const mosque = filteredMosquesData[0];
        map.easeTo({ center: [mosque.lng, mosque.lat], zoom: 15, duration: animated ? 550 : 0 });
        return;
    }

    const bounds = new maplibregl.LngLatBounds();
    filteredMosquesData.forEach(mosque => bounds.extend([mosque.lng, mosque.lat]));
    map.fitBounds(bounds, {
        padding: { top: 52, right: 52, bottom: 52, left: 52 },
        maxZoom: 14,
        duration: animated ? 650 : 0
    });
}

function resetMapView() {
    if (!map) return;
    map.easeTo({
        center: [Number(mapDefaults.longitude) || -1.9086, Number(mapDefaults.latitude) || 34.6814],
        zoom: Number(mapDefaults.zoom) || 9,
        bearing: 0,
        pitch: 0,
        duration: 650
    });
}

function setupSearchFunctionality() {
    const globalSearch = document.getElementById('globalSearch');
    const clearGlobalSearch = document.getElementById('clearGlobalSearch');
    const searchSuggestions = document.getElementById('searchSuggestions');
    if (!globalSearch || !clearGlobalSearch || !searchSuggestions) return;

    let searchTimeout;
    globalSearch.addEventListener('input', event => {
        const searchTerm = event.target.value.trim().toLowerCase();
        activeFilters.search = searchTerm;
        window.clearTimeout(searchTimeout);
        searchTimeout = window.setTimeout(() => {
            if (searchTerm.length >= 2) showSearchSuggestions(searchTerm);
            else {
                searchSuggestions.innerHTML = '';
                searchSuggestions.classList.remove('show');
            }
            applyFilters();
        }, 250);
    });

    clearGlobalSearch.addEventListener('click', () => {
        globalSearch.value = '';
        activeFilters.search = '';
        searchSuggestions.innerHTML = '';
        searchSuggestions.classList.remove('show');
        applyFilters();
        globalSearch.focus();
    });

    document.addEventListener('click', event => {
        if (!globalSearch.contains(event.target) && !searchSuggestions.contains(event.target)) {
            searchSuggestions.classList.remove('show');
        }
    });
}

function showSearchSuggestions(searchTerm) {
    const searchSuggestions = document.getElementById('searchSuggestions');
    if (!searchSuggestions) return;

    const suggestions = allMosquesData.filter(mosque => {
        return [mosque.name, mosque.address, mosque.national_code, mosque.imam, mosque.community]
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

    searchSuggestions.innerHTML = '<div class="suggestion-header text-muted small mb-2">نتائج مقترحة:</div>' + suggestions.map(mosque => `
        <button type="button" class="suggestion-item p-2 border-bottom cursor-pointer text-start w-100 bg-white border-0"
                data-mosque-id="${escapeHtml(mosque.id)}">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong>${highlightText(mosque.name, searchTerm)}</strong>
                    <div class="text-muted small">${highlightText(mosque.address, searchTerm)}</div>
                </div>
                <span class="badge bg-${statusBadgeClass(mosque.status)}">${escapeHtml(mosque.status)}</span>
            </div>
        </button>`).join('');
    searchSuggestions.classList.add('show');

    searchSuggestions.querySelectorAll('.suggestion-item').forEach(item => {
        item.addEventListener('click', function() {
            selectMosqueById(this.dataset.mosqueId || '', this, true);
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
        document.getElementById(elementId)?.addEventListener('change', function() {
            activeFilters[filterKey] = this.value;
            applyFilters();
        });
    });

    document.getElementById('clearAllFilters')?.addEventListener('click', resetAllFilters);
    document.addEventListener('click', event => {
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
    const labels = { search: 'بحث', community: 'الجماعة', status: 'الحالة', friday: 'صلاة الجمعة' };

    Object.entries(activeFilters).forEach(([key, value]) => {
        if (value === '') return;
        hasActiveFilters = true;
        const filterBadge = document.createElement('span');
        filterBadge.className = 'badge bg-primary-subtle text-primary border';
        const label = key === 'friday' ? (value === '1' ? 'نعم' : 'لا') : value;
        filterBadge.appendChild(document.createTextNode(`${labels[key] || key}: ${label} `));

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
    if (!(filterKey in activeFilters)) return;
    activeFilters[filterKey] = '';
    const inputMap = { search: 'globalSearch', community: 'communityFilter', status: 'statusFilter', friday: 'fridayFilter' };
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

function updateSidebarList(mosques) {
    const mosquesList = document.getElementById('mosquesList');
    const sidebarCount = document.getElementById('sidebarMosqueCount');
    const toolbarCount = document.getElementById('toolbarMosqueCount');
    const resultsSummary = document.getElementById('resultsSummary');
    if (!mosquesList || !sidebarCount || !resultsSummary) return;

    if (toolbarCount) toolbarCount.textContent = String(mosques.length);
    if (mosques.length === 0) {
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

    mosquesList.innerHTML = mosques.map((mosque, index) => {
        const safeId = escapeHtml(mosque.id);
        const safeCode = encodeURIComponent(String(mosque.national_code || mosque.id || ''));
        const isSelected = String(selectedMosqueId) === String(mosque.id);
        return `
            <article class="mosque-list-item${isSelected ? ' is-active' : ''}"
                     data-mosque-id="${safeId}" data-community="${escapeHtml(mosque.community)}"
                     ${isSelected ? 'aria-current="true"' : ''}>
                <div class="mosque-list-item__heading">
                    <h3 class="mosque-name"><span>${index + 1}.</span> ${highlightText(mosque.name, activeFilters.search)}</h3>
                    <span class="badge bg-${statusBadgeClass(mosque.status)}">${escapeHtml(mosque.status)}</span>
                </div>
                <p class="mosque-list-item__address"><i class="fas fa-map-marker-alt" aria-hidden="true"></i>${highlightText(mosque.address, activeFilters.search)}</p>
                <div class="mosque-list-item__meta">
                    <span><i class="fas fa-user" aria-hidden="true"></i>${escapeHtml(mosque.imam || 'غير محدد')}</span>
                    ${mosque.community ? `<span><i class="fas fa-users" aria-hidden="true"></i>${escapeHtml(mosque.community)}</span>` : ''}
                </div>
                <div class="mosque-list-item__actions">
                    <button type="button" class="btn btn-sm btn-outline-primary zoom-to-mosque" data-mosque-id="${safeId}">
                        <i class="fas fa-location-crosshairs" aria-hidden="true"></i><span>تحديد</span>
                    </button>
                    <a href="mosques.php?national_code=${safeCode}&from_map=${safeCode}" class="btn btn-sm btn-light" title="عرض التفاصيل" aria-label="عرض تفاصيل ${escapeHtml(mosque.name)}">
                        <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>
                </div>
            </article>`;
    }).join('');

    sidebarCount.textContent = String(mosques.length);
    resultsSummary.textContent = `عرض ${mosques.length} مسجد`;
    attachListEventListeners();
}

function attachListEventListeners() {
    document.querySelectorAll('.zoom-to-mosque').forEach(button => {
        button.addEventListener('click', function() {
            selectMosqueById(this.dataset.mosqueId || '', this, true);
        });
    });
    document.querySelectorAll('.mosque-list-item').forEach(item => {
        item.addEventListener('click', function(event) {
            if (event.target instanceof Element && event.target.closest('a,button')) return;
            selectMosqueById(this.dataset.mosqueId || '', this, true);
        });
    });
}

function selectMosqueById(id, trigger = null, moveMap = true) {
    if (!mapReady) return false;
    const mosque = allMosquesData.find(candidate => String(candidate.id) === String(id));
    if (!mosque) return false;

    selectedMosqueId = String(mosque.id);
    selectedTrigger = trigger instanceof HTMLElement ? trigger : null;
    map.getSource(SELECTED_SOURCE_ID)?.setData(featureCollection([mosque]));
    renderSelectedMosque(mosque);
    highlightSidebarItem(mosque.id);

    const mapElement = document.getElementById('map');
    if (mapElement) mapElement.dataset.selectedMosqueId = selectedMosqueId;
    if (moveMap) {
        map.easeTo({ center: [mosque.lng, mosque.lat], zoom: Math.max(map.getZoom(), 15), duration: 600 });
    }
    if (window.matchMedia('(max-width: 1199.98px)').matches) setMapWorkspaceView('map');
    return true;
}

function selectMosqueFromUrl() {
    const requestedId = new URLSearchParams(window.location.search).get('mosque');
    if (requestedId) selectMosqueById(requestedId, null, true);
}

function highlightSidebarItem(id) {
    document.querySelectorAll('.mosque-list-item').forEach(item => {
        item.classList.remove('is-active');
        item.removeAttribute('aria-current');
    });
    const item = document.querySelector(`.mosque-list-item[data-mosque-id="${CSS.escape(String(id))}"]`);
    if (!item) return;
    item.classList.add('is-active');
    item.setAttribute('aria-current', 'true');
    revealListItem(item);
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
    const nationalCode = mosque.national_code || mosque.id || '';
    const codeQuery = encodeURIComponent(String(nationalCode));
    const panel = document.getElementById('selectedMosquePanel');
    if (!panel) return;

    setPanelText('selectedMosqueTitle', mosque.name);
    setPanelText('selectedMosqueAddress', mosque.address);
    setPanelText('selectedMosqueImam', mosque.imam);
    setPanelText('selectedMosqueGuideImam', mosque.guide_imam);
    setPanelText('selectedMosqueCommunity', mosque.community);
    setPanelText('selectedMosqueCode', nationalCode);
    setPanelText('selectedMosqueStatus', mosque.status);
    setPanelText('selectedMosqueFriday', mosque.friday === '1' ? 'نعم' : 'لا');

    const detailsLink = document.getElementById('selectedMosqueDetails');
    if (detailsLink) detailsLink.href = `mosques.php?national_code=${codeQuery}&from_map=${codeQuery}`;
    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'false');
}

function closeSelectedMosque({ restoreFocus = true } = {}) {
    const previousId = selectedMosqueId;
    const previousTrigger = selectedTrigger;
    selectedMosqueId = '';
    selectedTrigger = undefined;
    map?.getSource(SELECTED_SOURCE_ID)?.setData(EMPTY_COLLECTION);

    const panel = document.getElementById('selectedMosquePanel');
    if (panel) {
        panel.hidden = true;
        panel.setAttribute('aria-hidden', 'true');
    }
    const mapElement = document.getElementById('map');
    if (mapElement) delete mapElement.dataset.selectedMosqueId;
    document.querySelectorAll('.mosque-list-item').forEach(item => {
        item.classList.remove('is-active');
        item.removeAttribute('aria-current');
    });

    if (!restoreFocus) return;
    const fallback = previousId
        ? document.querySelector(`.mosque-list-item[data-mosque-id="${CSS.escape(String(previousId))}"] .zoom-to-mosque`)
        : null;
    const focusTarget = previousTrigger?.isConnected ? previousTrigger : fallback;
    focusTarget?.focus({ preventScroll: true });
}

function setupMapButtons() {
    document.getElementById('fitToMarkers')?.addEventListener('click', () => fitToVisibleMosques(true));
    document.getElementById('resetMapView')?.addEventListener('click', resetMapView);
    document.getElementById('retryMap')?.addEventListener('click', () => window.location.reload());
    document.getElementById('selectedMosqueClose')?.addEventListener('click', () => closeSelectedMosque());
    attachListEventListeners();
}

function setupFilterToggle() {
    const button = document.getElementById('mapFilterToggle');
    const panel = document.getElementById('mapFilterPanel');
    if (!button || !panel) return;
    const mobileQuery = window.matchMedia('(max-width: 767.98px)');
    const syncViewport = event => {
        panel.classList.toggle('is-collapsed', event.matches);
        button.setAttribute('aria-expanded', String(!event.matches));
    };
    syncViewport(mobileQuery);
    mobileQuery.addEventListener('change', syncViewport);
    button.addEventListener('click', () => {
        const collapsed = panel.classList.toggle('is-collapsed');
        button.setAttribute('aria-expanded', String(!collapsed));
    });
}

function setMapWorkspaceView(view) {
    const row = document.getElementById('mapMainRow');
    if (!row) return;
    const listView = view === 'list';
    row.classList.toggle('is-list-view', listView);
    document.querySelectorAll('[data-map-view]').forEach(button => {
        button.setAttribute('aria-selected', String(button.dataset.mapView === view));
    });
    if (!listView && map) {
        window.setTimeout(() => {
            map.resize();
            const mosque = selectedMosque();
            if (mosque) map.easeTo({ center: [mosque.lng, mosque.lat], duration: 0 });
        }, 0);
    }
}

function setupMapViewSwitcher() {
    document.querySelectorAll('[data-map-view]').forEach(button => {
        button.addEventListener('click', () => setMapWorkspaceView(button.dataset.mapView || 'map'));
    });
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
    const paragraph = mapError.querySelector('p');
    if (message && paragraph) paragraph.textContent = message;
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

function exposeBrowserTestApi() {
    window.__mapWorkspaceTest = Object.freeze({
        getState() {
            const clusters = mapReady ? map.queryRenderedFeatures({ layers: [CLUSTER_LAYER_ID] }) : [];
            return {
                ready: mapReady,
                provider: String(mapConfig.provider || ''),
                styleUrl: String(mapConfig.styleUrl || ''),
                visibleCount: filteredMosquesData.length,
                clusterCount: clusters.length,
                selectedMosqueId,
                zoom: map?.getZoom() ?? null
            };
        },
        async expandFirstCluster() {
            if (!mapReady) return null;
            let clusters = map.queryRenderedFeatures({ layers: [CLUSTER_LAYER_ID] });
            if (clusters.length === 0) {
                resetMapView();
                await new Promise(resolve => map.once('idle', resolve));
                clusters = map.queryRenderedFeatures({ layers: [CLUSTER_LAYER_ID] });
            }
            return clusters[0] ? expandCluster(clusters[0]) : null;
        },
        selectMosque(id) {
            return selectMosqueById(id, null, true);
        },
        fitVisible: fitToVisibleMosques,
        reset: resetMapView
    });
}

function bootMapPage() {
    setupSearchFunctionality();
    setupFilters();
    setupFilterToggle();
    setupMapViewSwitcher();
    setupMapButtons();
    exposeBrowserTestApi();

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && selectedMosqueId) closeSelectedMosque();
    });
    initializeMap();
}

document.addEventListener('DOMContentLoaded', bootMapPage);
