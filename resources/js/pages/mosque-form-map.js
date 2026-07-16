import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

let formMap;
let locationMarker;

function mapDefaults(form) {
    try {
        const value = JSON.parse(form.dataset.mapDefaults || '{}');
        return {
            latitude: Number(value.latitude) || 34.6814,
            longitude: Number(value.longitude) || -1.9086,
            zoom: Number(value.zoom) || 12
        };
    } catch (_) {
        return { latitude: 34.6814, longitude: -1.9086, zoom: 12 };
    }
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

function moveMarker(form, latitude, longitude, center = true) {
    const lngLat = [Number(longitude), Number(latitude)];
    if (!locationMarker) {
        locationMarker = new maplibregl.Marker({ color: '#176b57', draggable: true })
            .setLngLat(lngLat)
            .addTo(formMap);
        locationMarker.on('dragend', () => {
            const position = locationMarker.getLngLat();
            setCoordinateValues(form, position.lat, position.lng);
        });
    } else {
        locationMarker.setLngLat(lngLat);
    }
    if (center) formMap.easeTo({ center: lngLat, duration: 350 });
}

function initializeFormMap(form) {
    const mapElement = document.getElementById('map');
    const mapContainer = document.getElementById('mapContainer');
    if (!mapElement || !mapContainer) return;

    const current = coordinates(form);
    if (!formMap) {
        try {
            formMap = new maplibregl.Map({
                container: mapElement,
                style: form.dataset.mapStyleUrl || 'https://tiles.openfreemap.org/styles/liberty',
                center: [current.longitude, current.latitude],
                zoom: mapDefaults(form).zoom,
                attributionControl: false
            });
            formMap.addControl(new maplibregl.NavigationControl({ showCompass: true, showZoom: true }), 'top-right');
            formMap.addControl(new maplibregl.FullscreenControl({ container: mapContainer }), 'top-right');
            formMap.addControl(new maplibregl.AttributionControl({
                compact: true,
                customAttribution: '<a href="https://openfreemap.org/" target="_blank" rel="noopener noreferrer">OpenFreeMap</a>'
            }), 'bottom-right');
            formMap.on('click', event => {
                setCoordinateValues(form, event.lngLat.lat, event.lngLat.lng);
                moveMarker(form, event.lngLat.lat, event.lngLat.lng, false);
            });
            formMap.on('load', () => moveMarker(form, current.latitude, current.longitude, false));
            formMap.on('error', () => {
                if (!formMap.loaded()) mapElement.dataset.mapLoadError = 'true';
            });
        } catch (_) {
            mapElement.innerHTML = '<div class="h-100 d-flex align-items-center justify-content-center text-muted p-3 text-center">تعذر تشغيل الخريطة. يمكن إدخال الإحداثيات يدويا.</div>';
            return;
        }
    } else {
        moveMarker(form, current.latitude, current.longitude, false);
    }

    window.requestAnimationFrame(() => {
        formMap.resize();
        formMap.easeTo({ center: [current.longitude, current.latitude], duration: 0 });
    });
}

function setContainerHidden(container, hidden) {
    Array.from(container.classList)
        .filter(className => className.endsWith('-collapsed-section'))
        .forEach(className => container.classList.remove(className));
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
    mapContainer.dataset.provider = form.dataset.mapProvider || 'maplibre';
    showMapButton.setAttribute('aria-expanded', 'false');
    showMapButton.setAttribute('aria-controls', mapContainer.id);
    setContainerHidden(mapContainer, true);

    showMapButton.addEventListener('click', () => {
        const opening = mapContainer.dataset.mapOpen !== 'true';
        mapContainer.dataset.mapOpen = opening ? 'true' : 'false';
        showMapButton.setAttribute('aria-expanded', String(opening));
        setContainerHidden(mapContainer, !opening);
        if (opening) initializeFormMap(form);
    });

    currentLocationButton?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            window.alert('المتصفح لا يدعم تحديد الموقع الحالي.');
            return;
        }
        navigator.geolocation.getCurrentPosition(position => {
            setCoordinateValues(form, position.coords.latitude, position.coords.longitude);
            if (formMap) moveMarker(form, position.coords.latitude, position.coords.longitude);
        }, () => window.alert('تعذر الحصول على الموقع الحالي. تحقق من صلاحيات المتصفح.'));
    });
}

document.addEventListener('DOMContentLoaded', setupFormMapPicker);
