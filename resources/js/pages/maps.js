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
    engine: 'leaflet',
    token: '',
    street: {},
    satellite: {},
    routing: {},
    ...pageData.mapConfig
};
const mapElement = document.getElementById('map');
let map;
let clusters;
let streetLayer;
let imageryLayer;
let labelsLayer;
let locationLayer;
let routeLayers;
let selectedId = '';
let currentMode = 'street';
let routeMode = 'driving';
let filtered = [];
const markers = new Map();
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

function tokenized(url) {
    if (!url || !config.token) return '';
    return `${url}${url.includes('?') ? '&' : '?'}token=${encodeURIComponent(config.token)}&language=ar`;
}

function notify(message, duration = 6000) {
    const notice = document.getElementById('mapNotice');
    if (!notice) return;
    notice.textContent = message;
    notice.hidden = false;
    window.clearTimeout(notice._timer);
    notice._timer = window.setTimeout(() => { notice.hidden = true; }, duration);
}

function tileLayer(settings, url, kind) {
    const layer = L.tileLayer(tokenized(url), {
        attribution: String(settings.attribution || ''),
        minZoom: 0,
        maxZoom: Number(settings.max_zoom) || 22,
        maxNativeZoom: Number(settings.max_native_zoom) || 22,
        tileSize: 256,
        zoomOffset: 0,
        updateWhenIdle: true,
        keepBuffer: 3
    });
    let failures = 0;
    layer.on('tileerror', () => {
        failures += 1;
        mapElement.dataset.tileErrors = String((Number(mapElement.dataset.tileErrors) || 0) + 1);
        if (failures === 1) notify('تعذر تحميل بعض بلاطات الخريطة. تحقق من مفتاح ArcGIS واتصال الشبكة.');
        if (kind !== 'street' && failures === 4 && currentMode !== 'street') switchBasemap('street');
    });
    return layer;
}

function ensureStreet() {
    if (!streetLayer && config.token && config.street?.url) {
        streetLayer = tileLayer(config.street, config.street.url, 'street');
    }
    return streetLayer;
}

function ensureImagery() {
    if (!imageryLayer && config.token && config.satellite?.url) {
        imageryLayer = tileLayer(config.satellite, config.satellite.url, 'imagery');
    }
    if (!labelsLayer && config.token && config.satellite?.labels_url) {
        labelsLayer = tileLayer(config.satellite, config.satellite.labels_url, 'labels');
    }
}

function savedMode() {
    try {
        const value = localStorage.getItem(MODE_KEY);
        return ['street', 'satellite', 'hybrid'].includes(value) ? value : 'street';
    } catch (_) {
        return 'street';
    }
}

function switchBasemap(mode) {
    if (!map || !['street', 'satellite', 'hybrid'].includes(mode)) return;
    [streetLayer, imageryLayer, labelsLayer].forEach(layer => layer && map.removeLayer(layer));
    if (mode === 'street') {
        ensureStreet()?.addTo(map);
    } else {
        ensureImagery();
        if (!imageryLayer) {
            notify('صور القمر الصناعي غير متاحة قبل إعداد مفتاح ArcGIS.');
            mode = 'street';
            ensureStreet()?.addTo(map);
        } else {
            imageryLayer.addTo(map);
            if (mode === 'hybrid') labelsLayer?.addTo(map);
        }
    }
    currentMode = mode;
    mapElement.dataset.mapMode = mode;
    document.querySelectorAll('[data-basemap-mode]').forEach(button => {
        button.setAttribute('aria-pressed', String(button.dataset.basemapMode === mode));
    });
    try { localStorage.setItem(MODE_KEY, mode); } catch (_) {}
}

function markerIcon(mosque, selected = false) {
    return L.divIcon({
        className: 'mosque-marker-shell',
        html: `<span class="mosque-marker mosque-marker--${statusTone(mosque.status)}${selected ? ' is-selected' : ''}" aria-hidden="true"><i class="fas fa-mosque"></i></span>`,
        iconSize: [36, 44],
        iconAnchor: [18, 42],
        tooltipAnchor: [0, -38]
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
        maxClusterRadius: zoom => zoom < 10 ? 55 : zoom < 14 ? 45 : 34,
        iconCreateFunction: clusterIcon
    });
    mosques.forEach(mosque => {
        const marker = L.marker([mosque.latitude, mosque.longitude], {
            icon: markerIcon(mosque),
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
        zoomControl: false,
        attributionControl: true,
        zoomSnap: 1,
        zoomDelta: 1,
        wheelPxPerZoomLevel: 80
    });
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
    if (!config.token) notify('أضف ARCGIS_ACCESS_TOKEN لعرض خريطة الأساس. علامات المساجد ما زالت متاحة.', 9000);
}

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value || EMPTY;
}

function selectedMosque() {
    return mosques.find(mosque => mosque.id === selectedId);
}

function selectMosque(id, reveal = true) {
    const mosque = mosques.find(item => item.id === String(id));
    const marker = markers.get(String(id));
    if (!mosque || !marker || !filtered.some(item => item.id === mosque.id)) return;
    if (selectedId && markers.has(selectedId)) {
        const previous = mosques.find(item => item.id === selectedId);
        if (previous) markers.get(selectedId).setIcon(markerIcon(previous));
    }
    selectedId = mosque.id;
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
        const external = document.getElementById('externalDirections');
        if (external) external.href = `https://www.google.com/maps/dir/?api=1&destination=${mosque.latitude},${mosque.longitude}`;
        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');
    }
    document.querySelectorAll('.mosque-list-item').forEach(item => item.classList.toggle('is-selected', item.dataset.mosqueId === mosque.id));
    if (reveal) {
        clusters.zoomToShowLayer(marker, () => map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 16), { duration: 0.65 }));
    }
    mapElement.dataset.selectedMosque = mosque.id;
}

function closeSelection({ focus = false } = {}) {
    if (selectedId && markers.has(selectedId)) {
        const mosque = mosques.find(item => item.id === selectedId);
        if (mosque) markers.get(selectedId).setIcon(markerIcon(mosque));
    }
    selectedId = '';
    document.getElementById('selectedMosquePanel')?.setAttribute('aria-hidden', 'true');
    const panel = document.getElementById('selectedMosquePanel');
    if (panel) panel.hidden = true;
    document.querySelectorAll('.mosque-list-item').forEach(item => item.classList.remove('is-selected'));
    delete mapElement.dataset.selectedMosque;
    clearRoute();
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
    if (selectedId) list.querySelector(`[data-mosque-id="${CSS.escape(selectedId)}"]`)?.classList.add('is-selected');
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
    if (selectedId && !filtered.some(mosque => mosque.id === selectedId)) closeSelection();
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

function resetMap() {
    map?.flyTo([defaults.latitude, defaults.longitude], defaults.zoom, { duration: 0.65 });
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

function currentPosition() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('unsupported'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            position => resolve({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy
            }),
            reject,
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
        );
    });
}

function showLocation(position, fit = true) {
    if (locationLayer) map.removeLayer(locationLayer);
    const point = [position.latitude, position.longitude];
    locationLayer = L.layerGroup([
        L.circle(point, { radius: Math.max(10, position.accuracy || 0), color: '#176b57', fillColor: '#74c5ad', fillOpacity: 0.14, weight: 1 }),
        L.circleMarker(point, { radius: 7, color: '#fff', weight: 3, fillColor: '#176b57', fillOpacity: 1 })
    ]).addTo(map);
    if (fit) map.flyTo(point, Math.max(map.getZoom(), 16), { duration: 0.55 });
}

async function locateUser() {
    const button = document.getElementById('locateMe');
    button?.classList.add('is-loading');
    try {
        showLocation(await currentPosition());
    } catch (_) {
        notify('تعذر تحديد موقعك. تحقق من إذن الموقع في المتصفح.');
    } finally {
        button?.classList.remove('is-loading');
    }
}

function clearRoute() {
    if (routeLayers && map) map.removeLayer(routeLayers);
    routeLayers = null;
    const panel = document.getElementById('routePanel');
    if (panel) panel.hidden = true;
    const steps = document.getElementById('routeSteps');
    if (steps) steps.innerHTML = '';
    const summary = document.getElementById('routeSummary');
    if (summary) summary.hidden = true;
}

function renderRoute(route, origin) {
    if (!Array.isArray(route?.geometry) || route.geometry.length < 2) throw new Error('invalid-route');
    if (routeLayers) map.removeLayer(routeLayers);
    const line = route.geometry.map(point => [Number(point[0]), Number(point[1])]);
    routeLayers = L.layerGroup([
        L.polyline(line, { color: '#fff', weight: 9, opacity: 0.9, lineJoin: 'round' }),
        L.polyline(line, { color: '#176b57', weight: 5, opacity: 0.98, lineJoin: 'round' }),
        L.circleMarker([origin.latitude, origin.longitude], { radius: 7, color: '#fff', weight: 3, fillColor: '#176b57', fillOpacity: 1 })
    ]).addTo(map);
    map.fitBounds(L.latLngBounds(line), { padding: [70, 70], maxZoom: 17, duration: 0.7 });
    setText('routeDistance', `${Number(route.distance_km || 0).toLocaleString('ar-MA', { maximumFractionDigits: 2 })} كم`);
    setText('routeDuration', `${Number(route.duration_minutes || 0).toLocaleString('ar-MA')} دقيقة`);
    const summary = document.getElementById('routeSummary');
    if (summary) summary.hidden = false;
    const steps = document.getElementById('routeSteps');
    if (steps) steps.innerHTML = (route.steps || []).map(step => `<li><span>${escapeHtml(step.text)}</span><small>${Number(step.distance_km || 0).toLocaleString('ar-MA')} كم</small></li>`).join('');
    setText('routeStatus', '');
    mapElement.dataset.routeReady = 'true';
}

function routeErrorMessage(code) {
    return {
        route_rate_limit: 'تم تجاوز الحد المؤقت لطلبات الاتجاهات. حاول بعد دقيقة.',
        route_quota: 'حصة خدمة الاتجاهات غير متاحة حالياً.',
        route_not_found: 'لم يتم العثور على مسار مناسب.',
        route_unavailable: 'خدمة الاتجاهات غير مهيأة أو غير متاحة.',
        csrf_failed: 'انتهت صلاحية الجلسة. حدّث الصفحة وحاول مجدداً.'
    }[code] || 'تعذر حساب المسار حالياً.';
}

async function requestRoute() {
    const mosque = selectedMosque();
    const panel = document.getElementById('routePanel');
    if (!mosque || !panel) return;
    panel.hidden = false;
    setText('routeStatus', 'جاري تحديد موقعك وحساب المسار...');
    document.getElementById('routeSummary')?.setAttribute('hidden', '');
    try {
        if (!config.routing?.enabled) throw new Error('route_unavailable');
        const origin = await currentPosition();
        const body = new URLSearchParams({
            csrf_token: String(pageData.csrfToken || ''),
            origin_latitude: String(origin.latitude),
            origin_longitude: String(origin.longitude),
            destination_latitude: String(mosque.latitude),
            destination_longitude: String(mosque.longitude),
            mode: routeMode
        });
        const response = await fetch(String(config.routing.endpoint || 'ajax/map_route.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json' },
            body,
            credentials: 'same-origin'
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.data) throw new Error(payload.error?.code || 'route_failed');
        renderRoute(payload.data, origin);
    } catch (error) {
        setText('routeStatus', routeErrorMessage(error.message));
        delete mapElement.dataset.routeReady;
    }
}

function setupControls() {
    document.querySelectorAll('[data-basemap-mode]').forEach(button => button.addEventListener('click', () => switchBasemap(button.dataset.basemapMode)));
    document.getElementById('mapZoomIn')?.addEventListener('click', () => map.zoomIn());
    document.getElementById('mapZoomOut')?.addEventListener('click', () => map.zoomOut());
    document.getElementById('fitToMarkers')?.addEventListener('click', () => fitMarkers(true));
    document.getElementById('resetMapView')?.addEventListener('click', resetMap);
    document.getElementById('locateMe')?.addEventListener('click', locateUser);
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
    document.getElementById('routeToMosque')?.addEventListener('click', requestRoute);
    document.getElementById('clearRoute')?.addEventListener('click', clearRoute);
    document.querySelectorAll('[data-route-mode]').forEach(button => button.addEventListener('click', () => {
        routeMode = button.dataset.routeMode;
        document.querySelectorAll('[data-route-mode]').forEach(item => item.setAttribute('aria-checked', String(item === button)));
        if (!document.getElementById('routePanel')?.hidden) requestRoute();
    }));
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
    }));
    document.getElementById('retryMap')?.addEventListener('click', () => window.location.reload());
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
            switchBasemap,
            selectFirst: () => filtered[0] && selectMosque(filtered[0].id, true),
            renderRoute: route => renderRoute(route, { latitude: defaults.latitude, longitude: defaults.longitude })
        };
    } catch (_) {
        document.getElementById('mapLoading')?.classList.add('d-none');
        document.getElementById('mapError')?.classList.remove('d-none');
        mapElement.dataset.mapLoadError = 'true';
    }
}

document.addEventListener('DOMContentLoaded', start);
