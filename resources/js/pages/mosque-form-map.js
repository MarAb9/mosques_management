import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

let formMap;
let locationMarker;

function json(value, fallback = {}) {
    try { return JSON.parse(value || '{}'); } catch (_) { return fallback; }
}

function mapDefaults(form) {
    const value = json(form.dataset.mapDefaults);
    return {
        latitude: Number(value.latitude) || 34.6814,
        longitude: Number(value.longitude) || -1.9086,
        zoom: Number(value.zoom) || 12
    };
}

function coordinates(form) {
    const defaults = mapDefaults(form);
    const latitude = Number.parseFloat(form.querySelector('[name="latitude"]')?.value || '');
    const longitude = Number.parseFloat(form.querySelector('[name="longitude"]')?.value || '');
    return {
        latitude: Number.isFinite(latitude) ? latitude : defaults.latitude,
        longitude: Number.isFinite(longitude) ? longitude : defaults.longitude
    };
}

function setCoordinateValues(form, latitude, longitude) {
    const latitudeInput = form.querySelector('[name="latitude"]');
    const longitudeInput = form.querySelector('[name="longitude"]');
    if (latitudeInput) latitudeInput.value = Number(latitude).toFixed(8);
    if (longitudeInput) longitudeInput.value = Number(longitude).toFixed(8);
    latitudeInput?.dispatchEvent(new Event('input', { bubbles: true }));
    longitudeInput?.dispatchEvent(new Event('input', { bubbles: true }));
}

function markerIcon() {
    return L.divIcon({
        className: 'mosque-form-marker-shell',
        html: '<span class="mosque-form-marker"><i class="fas fa-mosque" aria-hidden="true"></i></span>',
        iconSize: [38, 46],
        iconAnchor: [19, 44]
    });
}

function moveMarker(form, latitude, longitude, center = true) {
    const point = [Number(latitude), Number(longitude)];
    if (!locationMarker) {
        locationMarker = L.marker(point, { icon: markerIcon(), draggable: true, autoPan: true }).addTo(formMap);
        locationMarker.on('dragend', () => {
            const position = locationMarker.getLatLng();
            setCoordinateValues(form, position.lat, position.lng);
        });
    } else {
        locationMarker.setLatLng(point);
    }
    if (center) formMap.panTo(point, { animate: true, duration: 0.35 });
}

function addFullscreenControl(container) {
    const Control = L.Control.extend({
        options: { position: 'topright' },
        onAdd() {
            const button = L.DomUtil.create('button', 'leaflet-control map-form-fullscreen');
            button.type = 'button';
            button.title = 'ملء الشاشة';
            button.setAttribute('aria-label', 'ملء الشاشة');
            button.innerHTML = '<i class="fas fa-expand" aria-hidden="true"></i>';
            L.DomEvent.disableClickPropagation(button);
            L.DomEvent.on(button, 'click', () => document.fullscreenElement ? document.exitFullscreen?.() : container.requestFullscreen?.());
            return button;
        }
    });
    formMap.addControl(new Control());
    document.addEventListener('fullscreenchange', () => window.setTimeout(() => formMap?.invalidateSize(), 80));
}

function initializeFormMap(form) {
    const mapElement = document.getElementById('map');
    const mapContainer = document.getElementById('mapContainer');
    if (!mapElement || !mapContainer) return;
    const current = coordinates(form);
    if (!formMap) {
        const config = json(form.dataset.mapConfig);
        formMap = L.map(mapElement, {
            center: [current.latitude, current.longitude],
            zoom: mapDefaults(form).zoom,
            minZoom: 2,
            maxZoom: 22,
            zoomControl: false,
            attributionControl: true
        });
        formMap.attributionControl.setPrefix(false);
        L.control.zoom({ position: 'topright' }).addTo(formMap);
        if (config.street?.url) {
            L.tileLayer(config.street.url, {
                attribution: String(config.street.attribution || ''),
                maxZoom: Number(config.street.max_zoom) || 22,
                maxNativeZoom: Number(config.street.max_native_zoom) || 22,
                tileSize: Number(config.street.tile_size) || 256
            }).on('tileerror', () => { mapElement.dataset.tileError = 'true'; }).addTo(formMap);
        } else {
            mapElement.dataset.tileError = 'missing-token';
        }
        formMap.on('click', event => {
            setCoordinateValues(form, event.latlng.lat, event.latlng.lng);
            moveMarker(form, event.latlng.lat, event.latlng.lng, false);
        });
        addFullscreenControl(mapContainer);
        moveMarker(form, current.latitude, current.longitude, false);
        mapElement.dataset.provider = 'leaflet';
        mapElement.dataset.mapReady = 'true';
    } else {
        moveMarker(form, current.latitude, current.longitude, false);
    }
    window.requestAnimationFrame(() => {
        formMap.invalidateSize();
        formMap.setView([current.latitude, current.longitude], formMap.getZoom(), { animate: false });
    });
}

function setContainerHidden(container, hidden) {
    [...container.classList].filter(name => name.endsWith('-collapsed-section')).forEach(name => container.classList.remove(name));
    container.hidden = hidden;
    container.classList.toggle('d-none', hidden);
}

function setupFormMapPicker() {
    const form = document.querySelector('form[data-guard-unsaved="true"]');
    const showMapButton = document.getElementById('showMapBtn');
    const currentLocationButton = document.getElementById('getCurrentLocationBtn');
    const mapContainer = document.getElementById('mapContainer');
    if (!form || !showMapButton || !mapContainer) return;
    mapContainer.dataset.mapOpen = 'false';
    mapContainer.dataset.provider = 'leaflet';
    showMapButton.setAttribute('aria-expanded', 'false');
    showMapButton.setAttribute('aria-controls', mapContainer.id);
    setContainerHidden(mapContainer, true);
    showMapButton.addEventListener('click', () => {
        const opening = mapContainer.dataset.mapOpen !== 'true';
        mapContainer.dataset.mapOpen = String(opening);
        showMapButton.setAttribute('aria-expanded', String(opening));
        setContainerHidden(mapContainer, !opening);
        if (opening) initializeFormMap(form);
    });
    currentLocationButton?.addEventListener('click', () => {
        if (!navigator.geolocation) return window.alert('المتصفح لا يدعم تحديد الموقع الحالي.');
        navigator.geolocation.getCurrentPosition(position => {
            setCoordinateValues(form, position.coords.latitude, position.coords.longitude);
            if (formMap) moveMarker(form, position.coords.latitude, position.coords.longitude);
        }, () => window.alert('تعذر الحصول على الموقع الحالي. تحقق من صلاحيات المتصفح.'));
    });
}

document.addEventListener('DOMContentLoaded', setupFormMapPicker);
