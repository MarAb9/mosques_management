import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';

const MODE_KEY = 'mosques.leaflet.basemap';
const EMPTY = 'غير محدد';
const pageData = (() => {
    try {
        return JSON.parse(document.getElementById('mapPageData')?.textContent || '{}');
    } catch (_) {
        return {};
    }
})();
const defaults = {
    latitude: Number(pageData.mapDefaults?.latitude) || 34.6814,
    longitude: Number(pageData.mapDefaults?.longitude) || -1.9086,
    zoom: Number(pageData.mapDefaults?.zoom) || 9
};
const config = {
    streetUrl: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    satelliteUrl: '',
    apiKey: '',
    ...pageData.mapConfig
};
const mapElement = document.getElementById('map');
let map;
let clusters;
let streetLayer;
let satelliteLayer;
let selectedMosqueId = '';
let currentMode = 'street';
let filtered = [];
const markers = new Map();
let tileErrorShown = false;
const filters = { search: '', community: '', status: '', friday: '' };

function validMosques(geoJson) {
    return (Array.isArray(geoJson?.features) ? geoJson.features : []).flatMap(feature => {
        const coordinates = feature?.geometry?.coordinates;
        const longitude = Number(coordinates?.[0]);
        const latitude = Number(coordinates?.[1]);
        const properties = feature?.properties || {};
        const id = String(properties.registration_number ?? feature?.id ?? '');
        if (!id || !Number.isFinite(latitude) || !Number.isFinite(longitude)
            || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return [];
        return [{
            id,
            nationalCode: String(properties.national_code || ''),
            name: String(properties.mosque_name || EMPTY),
            status: String(properties.status || EMPTY),
            address: String(properties.address || EMPTY),
            community: String(properties.community || EMPTY),
            imam: String(properties.imam_name || EMPTY),
            guideImam: String(properties.guide_imam || EMPTY),
            friday: String(properties.friday_prayer || ''),
            latitude,
            longitude
        }];
    });
}

const mosques = validMosques(pageData.mosqueGeoJson);

function escapeHtml(value) {
    const node = document.createElement('span');
    node.textContent = String(value ?? '');
    return node.innerHTML;
}

function statusTone(status) {
    if (status.includes('مغلق')) return 'danger';
    if (status.includes('دون') || status.includes('ترخيص')) return 'warning';
    if (status.includes('مفتوح')) return 'success';
    return 'secondary';
}

function notify(message, duration = 6000, retry = false) {
    const notice = document.getElementById('mapNotice');
    if (!notice) return;
    const text = notice.querySelector('[data-map-notice-text]');
    if (text) text.textContent = message;
    const button = document.getElementById('retryMapTiles');
    if (button) button.hidden = !retry;
    notice.hidden = false;
    window.clearTimeout(notice._timer);
    if (duration > 0) notice._timer = window.setTimeout(() => { notice.hidden = true; }, duration);
}

function isCancellation(error) {
    return /abort|cancel/i.test(`${error?.name || ''} ${error?.message || ''} ${error?.error?.message || ''}`);
}

function recordLayerFailure(kind, error = {}) {
    if (isCancellation(error)) return;
    mapElement.dataset.tileErrors = String((Number(mapElement.dataset.tileErrors) || 0) + 1);
    if (tileErrorShown) return;
    tileErrorShown = true;
    notify(
        kind === 'satellite'
            ? 'تعذر تحميل جزء من صور القمر الصناعي. حاول مرة أخرى.'
            : 'تعذر تحميل جزء من الخريطة. حاول مرة أخرى.',
        0,
        true
    );
}

function tileLayer(url, options, kind) {
    const layer = L.tileLayer(url, { crossOrigin: true, ...options });
    layer.on('tileerror', event => recordLayerFailure(kind, event));
    layer.on('load', syncSelection);
    return layer;
}

function ensureStreet() {
    if (!streetLayer) {
        streetLayer = tileLayer(String(config.streetUrl), {
            maxNativeZoom: 19,
            maxZoom: 22,
            attribution: '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">&copy; OpenStreetMap contributors</a>'
        }, 'street');
    }
    return streetLayer;
}

function ensureSatellite() {
    if (!satelliteLayer && config.apiKey && config.satelliteUrl) {
        const url = String(config.satelliteUrl).replace('{key}', encodeURIComponent(String(config.apiKey)));
        satelliteLayer = tileLayer(url, {
            maxNativeZoom: 22,
            maxZoom: 22,
            attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank" rel="noopener noreferrer">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">&copy; OpenStreetMap contributors</a>'
        }, 'satellite');
    }
    return satelliteLayer;
}

function savedMode() {
    try {
        const value = localStorage.getItem(MODE_KEY);
        return value === 'hybrid' ? 'satellite' : ['street', 'satellite'].includes(value) ? value : 'street';
    } catch (_) {
        return 'street';
    }
}

function switchBasemap(mode) {
    if (!map || !['street', 'satellite'].includes(mode)) return;
    [streetLayer, satelliteLayer].forEach(layer => layer && map.removeLayer(layer));
    const activeLayer = mode === 'street' ? ensureStreet() : ensureSatellite();
    if (activeLayer) activeLayer.addTo(map);
    else notify('أضف MAPTILER_API_KEY لعرض صور القمر الصناعي.', 0);
    currentMode = mode;
    mapElement.closest('.map-canvas-shell')?.setAttribute('data-map-mode', mode);
    mapElement.dataset.mapMode = mode;
    document.querySelectorAll('[data-basemap-mode]').forEach(button => {
        button.setAttribute('aria-pressed', String(button.dataset.basemapMode === mode));
    });
    try { localStorage.setItem(MODE_KEY, mode); } catch (_) {}
    syncSelection();
}

function markerIcon(mosque, selected = false) {
    return L.divIcon({
        className: 'mosque-marker-shell',
        html: `<span class="mosque-marker mosque-marker--${statusTone(mosque.status)}${selected ? ' is-selected' : ''}" aria-hidden="true"><i class="fas fa-mosque"></i></span>`,
        iconSize: [32, 38],
        iconAnchor: [16, 37],
        tooltipAnchor: [0, -33]
    });
}

function clusterIcon(cluster) {
    const count = cluster.getChildCount();
    const size = count >= 75 ? 'large' : count >= 25 ? 'medium' : 'small';
    return L.divIcon({
        className: 'mosque-cluster-shell',
        html: `<span class="mosque-cluster mosque-cluster--${size}"><strong>${count}</strong><small>مسجد</small></span>`,
        iconSize: size === 'large' ? [60, 60] : size === 'medium' ? [52, 52] : [44, 44]
    });
}

function createMarkers() {
    clusters = L.markerClusterGroup({
        chunkedLoading: true,
        chunkInterval: 60,
        chunkDelay: 20,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        removeOutsideVisibleBounds: true,
        disableClusteringAtZoom: 18,
        clusterPane: 'mosqueMarkers',
        maxClusterRadius: zoom => zoom < 10 ? 55 : zoom < 14 ? 45 : 34,
        iconCreateFunction: clusterIcon
    });
    mosques.forEach(mosque => {
        const marker = L.marker([mosque.latitude, mosque.longitude], {
            icon: markerIcon(mosque),
            pane: 'mosqueMarkers',
            keyboard: true,
            title: mosque.name,
            riseOnHover: true
        });
        marker.mosque = mosque;
        marker.bindTooltip(mosque.name, { direction: 'top', opacity: 0.96 });
        marker.on('click', () => selectMosque(mosque.id, false));
        markers.set(mosque.id, marker);
    });
    clusters.addLayers([...markers.values()]);
    clusters.addTo(map);
}

function initializeMap() {
    if (!mapElement) return;
    mapElement.dataset.provider = 'leaflet';
    mapElement.dataset.mapReady = 'false';
    map = L.map(mapElement, {
        center: [defaults.latitude, defaults.longitude],
        zoom: defaults.zoom,
        minZoom: 2,
        maxZoom: 22,
        zoomControl: true,
        attributionControl: true,
        zoomSnap: 1,
        zoomDelta: 1,
        wheelPxPerZoomLevel: 80
    });
    map.createPane('mosqueMarkers').style.zIndex = '650';
    map.attributionControl.setPrefix(false);
    createMarkers();
    switchBasemap(savedMode());
    filtered = [...mosques];
    fitMarkers(false);
    map.whenReady(() => {
        document.getElementById('mapLoading')?.classList.add('d-none');
        mapElement.dataset.mapReady = 'true';
        window.setTimeout(() => map.invalidateSize(), 0);
    });
    map.on('moveend', syncSelection);
    map.on('zoomend', () => {
        syncSelection();
        if (currentMode === 'satellite' && map.getZoom() === 22) {
            notify('تم الوصول إلى أقصى دقة متاحة.', 4000);
        }
    });
}

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value || EMPTY;
}

function selectedMosque() {
    return mosques.find(mosque => mosque.id === selectedMosqueId);
}

function syncSelection() {
    const mosque = selectedMosque();
    const marker = mosque && markers.get(mosque.id);
    if (!mosque || !marker || !filtered.some(item => item.id === mosque.id)) return;
    marker.setIcon(markerIcon(mosque, true));
    clusters.refreshClusters(marker);
    const panel = document.getElementById('selectedMosquePanel');
    if (panel) {
        setText('selectedMosqueTitle', mosque.name);
        setText('selectedMosqueStatus', mosque.status);
        setText('selectedMosqueCode', mosque.nationalCode || mosque.id);
        setText('selectedMosqueFriday', mosque.friday || EMPTY);
        setText('selectedMosqueAddress', mosque.address);
        setText('selectedMosqueImam', mosque.imam);
        setText('selectedMosqueGuideImam', mosque.guideImam);
        setText('selectedMosqueCommunity', mosque.community);
        const details = document.getElementById('selectedMosqueDetails');
        if (details) details.href = `mosques.php?national_code=${encodeURIComponent(mosque.nationalCode)}&from_map=${encodeURIComponent(mosque.nationalCode)}`;
        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');
    }
    document.querySelectorAll('.mosque-list-item').forEach(item => {
        const selected = item.dataset.mosqueId === mosque.id;
        item.classList.toggle('is-selected', selected);
        if (selected) item.setAttribute('aria-current', 'true');
        else item.removeAttribute('aria-current');
    });
    mapElement.dataset.selectedMosque = mosque.id;
}

function selectMosque(id, reveal = true) {
    const mosque = mosques.find(item => item.id === String(id));
    const marker = markers.get(String(id));
    if (!mosque || !marker || !filtered.some(item => item.id === mosque.id)) return;
    if (selectedMosqueId && markers.has(selectedMosqueId)) {
        const previous = mosques.find(item => item.id === selectedMosqueId);
        if (previous) markers.get(selectedMosqueId).setIcon(markerIcon(previous));
    }
    selectedMosqueId = mosque.id;
    syncSelection();
    if (reveal) {
        clusters.zoomToShowLayer(marker, () => map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 16), { duration: 0.65 }));
    }
}

function closeSelection({ focus = false } = {}) {
    if (selectedMosqueId && markers.has(selectedMosqueId)) {
        const mosque = mosques.find(item => item.id === selectedMosqueId);
        if (mosque) markers.get(selectedMosqueId).setIcon(markerIcon(mosque));
    }
    selectedMosqueId = '';
    document.getElementById('selectedMosquePanel')?.setAttribute('aria-hidden', 'true');
    const panel = document.getElementById('selectedMosquePanel');
    if (panel) panel.hidden = true;
    document.querySelectorAll('.mosque-list-item').forEach(item => item.classList.remove('is-selected'));
    delete mapElement.dataset.selectedMosque;
    if (focus) document.getElementById('map')?.focus();
}

function listMarkup(mosque, index) {
    return `<article class="mosque-list-item" data-mosque-id="${escapeHtml(mosque.id)}" tabindex="0">
        <div class="mosque-list-item__top"><strong>${index + 1}. ${escapeHtml(mosque.name)}</strong><span class="badge bg-${statusTone(mosque.status)}">${escapeHtml(mosque.status)}</span></div>
        <p><i class="fas fa-map-marker-alt" aria-hidden="true"></i>${escapeHtml(mosque.address)}</p>
        <p><i class="fas fa-user" aria-hidden="true"></i>${escapeHtml(mosque.imam)}</p>
        <p><i class="fas fa-users" aria-hidden="true"></i>${escapeHtml(mosque.community)}</p>
        <button type="button" class="btn btn-sm btn-outline-primary zoom-to-mosque"><i class="fas fa-search-location" aria-hidden="true"></i> تحديد على الخريطة</button>
    </article>`;
}

function updateList() {
    const list = document.getElementById('mosquesList');
    if (!list) return;
    list.innerHTML = filtered.length
        ? filtered.map(listMarkup).join('')
        : '<div class="map-empty-state"><i class="fas fa-map-marker-alt"></i><p>لا توجد مساجد مطابقة للتصفية</p></div>';
    ['toolbarMosqueCount', 'sidebarMosqueCount'].forEach(id => setText(id, new Intl.NumberFormat('ar-MA').format(filtered.length)));
    setText('resultsSummary', `عرض ${filtered.length} مسجد`);
    if (selectedMosqueId) list.querySelector(`[data-mosque-id="${CSS.escape(selectedMosqueId)}"]`)?.classList.add('is-selected');
}

function updateActiveFilters() {
    const values = [
        filters.search && `بحث: ${filters.search}`,
        filters.community && `الجماعة: ${filters.community}`,
        filters.status && `الوضعية: ${filters.status}`,
        filters.friday !== '' && `الجمعة: ${filters.friday === '1' ? 'نعم' : 'لا'}`
    ].filter(Boolean);
    const container = document.getElementById('activeFiltersContainer');
    const active = document.getElementById('activeFilters');
    if (container) container.hidden = values.length === 0;
    if (active) active.innerHTML = values.map(value => `<span class="map-filter-chip">${escapeHtml(value)}</span>`).join('');
}

function applyFilters({ fit = true } = {}) {
    const query = filters.search.toLocaleLowerCase('ar');
    filtered = mosques.filter(mosque => {
        const searchable = [mosque.id, mosque.nationalCode, mosque.name, mosque.address, mosque.community, mosque.imam, mosque.guideImam].join(' ').toLocaleLowerCase('ar');
        return (!query || searchable.includes(query))
            && (!filters.community || mosque.community === filters.community)
            && (!filters.status || mosque.status === filters.status)
            && (filters.friday === '' || (mosque.friday === 'نعم' ? '1' : '0') === filters.friday);
    });
    clusters.clearLayers();
    clusters.addLayers(filtered.map(mosque => markers.get(mosque.id)).filter(Boolean));
    mapElement.dataset.visibleCount = String(filtered.length);
    updateList();
    updateActiveFilters();
    if (selectedMosqueId && !filtered.some(mosque => mosque.id === selectedMosqueId)) closeSelection();
    else syncSelection();
    if (fit) fitMarkers(true);
}

function fitMarkers(animate = true) {
    if (!map || filtered.length === 0) return;
    if (filtered.length === 1) {
        map.setView([filtered[0].latitude, filtered[0].longitude], 16, { animate });
        return;
    }
    const bounds = L.latLngBounds(filtered.map(mosque => [mosque.latitude, mosque.longitude]));
    map.fitBounds(bounds, { padding: [48, 48], maxZoom: 15, animate, duration: 0.65 });
}

function setupFilters() {
    let timer;
    const search = document.getElementById('globalSearch');
    search?.addEventListener('input', event => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            filters.search = event.target.value.trim();
            applyFilters();
        }, 180);
    });
    document.getElementById('clearGlobalSearch')?.addEventListener('click', () => {
        if (search) search.value = '';
        filters.search = '';
        applyFilters();
        search?.focus();
    });
    [['communityFilter', 'community'], ['statusFilter', 'status'], ['fridayFilter', 'friday']].forEach(([id, key]) => {
        document.getElementById(id)?.addEventListener('change', event => {
            filters[key] = event.target.value;
            applyFilters();
        });
    });
    document.getElementById('clearAllFilters')?.addEventListener('click', () => {
        filters.search = filters.community = filters.status = filters.friday = '';
        ['globalSearch', 'communityFilter', 'statusFilter', 'fridayFilter'].forEach(id => {
            const element = document.getElementById(id);
            if (element) element.value = '';
        });
        applyFilters();
    });
}

function retryBasemap() {
    const failedLayer = currentMode === 'street' ? streetLayer : satelliteLayer;
    if (failedLayer) map.removeLayer(failedLayer);
    if (currentMode === 'street') streetLayer = null;
    else satelliteLayer = null;
    tileErrorShown = false;
    switchBasemap(currentMode);
    notify('جاري إعادة تحميل خريطة الأساس...', 4000);
}

function setupControls() {
    document.querySelectorAll('[data-basemap-mode]').forEach(button => button.addEventListener('click', () => switchBasemap(button.dataset.basemapMode)));
    document.getElementById('fitToMarkers')?.addEventListener('click', () => fitMarkers(true));
    document.getElementById('refreshMap')?.addEventListener('click', () => window.location.reload());
    document.getElementById('mapFullscreen')?.addEventListener('click', () => {
        const container = document.querySelector('.map-canvas-card');
        if (!document.fullscreenElement) container?.requestFullscreen?.();
        else document.exitFullscreen?.();
    });
    document.addEventListener('fullscreenchange', () => window.setTimeout(() => map.invalidateSize(), 80));
    document.getElementById('selectedMosqueClose')?.addEventListener('click', () => closeSelection({ focus: true }));
    document.getElementById('copyMosqueCoordinates')?.addEventListener('click', async () => {
        const mosque = selectedMosque();
        if (!mosque) return;
        const value = `${mosque.latitude.toFixed(6)}, ${mosque.longitude.toFixed(6)}`;
        try {
            await navigator.clipboard.writeText(value);
            notify('تم نسخ الإحداثيات.');
        } catch (_) {
            notify(value);
        }
    });
    document.getElementById('mosquesList')?.addEventListener('click', event => {
        const item = event.target.closest('[data-mosque-id]');
        if (item) selectMosque(item.dataset.mosqueId, true);
    });
    document.getElementById('mosquesList')?.addEventListener('keydown', event => {
        if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-mosque-id]')) {
            event.preventDefault();
            selectMosque(event.target.dataset.mosqueId, true);
        }
    });
    const filterToggle = document.getElementById('mapFilterToggle');
    const filterPanel = document.getElementById('mapFilterPanel');
    if (window.matchMedia('(max-width: 767.98px)').matches) {
        filterToggle?.setAttribute('aria-expanded', 'false');
        filterPanel?.classList.add('is-collapsed');
    }
    filterToggle?.addEventListener('click', event => {
        const panel = filterPanel;
        const collapsed = event.currentTarget.getAttribute('aria-expanded') === 'false';
        event.currentTarget.setAttribute('aria-expanded', String(collapsed));
        panel?.classList.toggle('is-collapsed', !collapsed);
    });
    document.querySelectorAll('[data-map-view]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[data-map-view]').forEach(item => item.setAttribute('aria-selected', String(item === button)));
        document.getElementById('mapMainRow')?.setAttribute('data-active-view', button.dataset.mapView);
        if (button.dataset.mapView === 'map') window.setTimeout(() => map.invalidateSize(), 50);
        syncSelection();
    }));
    document.getElementById('retryMap')?.addEventListener('click', () => window.location.reload());
    document.getElementById('retryMapTiles')?.addEventListener('click', retryBasemap);
}

function start() {
    if (!mapElement) return;
    try {
        initializeMap();
        setupFilters();
        setupControls();
        applyFilters({ fit: false });
        window.__leafletWorkspaceTest = {
            map,
            clusters,
            get mode() { return currentMode; },
            get visibleCount() { return filtered.length; },
            get selectedMarkerHighlighted() {
                return Boolean(markers.get(selectedMosqueId)?.options.icon?.options.html?.includes('is-selected'));
            },
            switchBasemap,
            recordLayerFailure,
            selectFirst: () => filtered[0] && selectMosque(filtered[0].id, true),
            clickFirstMarker: () => {
                const marker = filtered[0] && markers.get(filtered[0].id);
                if (!marker) return false;
                marker.fire('click');
                return true;
            },
            spiderfyFirstCluster: () => {
                const cluster = clusters?._featureGroup?.getLayers?.().find(layer => typeof layer.spiderfy === 'function');
                if (!cluster) return false;
                cluster.spiderfy();
                return true;
            }
        };
    } catch (_) {
        document.getElementById('mapLoading')?.classList.add('d-none');
        document.getElementById('mapError')?.classList.remove('d-none');
        mapElement.dataset.mapLoadError = 'true';
    }
}

document.addEventListener('DOMContentLoaded', start);
