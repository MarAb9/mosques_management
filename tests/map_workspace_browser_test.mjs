import { spawn } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = (process.env.MAP_TEST_BASE_URL || 'http://127.0.0.1:8085').replace(/\/$/, '');
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const port = Number(process.env.MAP_TEST_DEBUG_PORT || 9444);
const root = process.cwd();
const evidenceDir = path.join(root, 'docs', 'screenshots', 'maplibre-map');
const profileDir = path.join(root, 'storage', `.maplibre-chrome-${Date.now()}`);
const resultPath = path.join(root, 'storage', 'maplibre-browser-result.json');
const delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

await mkdir(evidenceDir, { recursive: true });
await mkdir(profileDir, { recursive: true });

const chrome = spawn(chromePath, [
    '--headless=new',
    '--disable-dev-shm-usage',
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-default-apps',
    '--disable-extensions',
    '--disable-features=Translate,MediaRouter,OptimizationHints',
    '--enable-unsafe-swiftshader',
    '--ignore-gpu-blocklist',
    '--use-gl=angle',
    '--use-angle=swiftshader',
    '--hide-scrollbars',
    '--no-first-run',
    '--no-default-browser-check',
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profileDir}`,
    'about:blank'
], { stdio: 'ignore' });

async function waitForChrome() {
    const deadline = Date.now() + 15000;
    while (Date.now() < deadline) {
        try {
            const response = await fetch(`http://127.0.0.1:${port}/json/list`);
            if (response.ok) {
                const targets = await response.json();
                const page = targets.find(target => target.type === 'page');
                if (page) return page;
            }
        } catch {
            // Chrome is still starting.
        }
        await delay(150);
    }
    throw new Error('Chrome DevTools endpoint did not become ready.');
}

function connect(webSocketDebuggerUrl) {
    const socket = new WebSocket(webSocketDebuggerUrl);
    const pending = new Map();
    const listeners = new Map();
    let sequence = 0;
    const opened = new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });

    socket.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (message.id) {
            const request = pending.get(message.id);
            if (!request) return;
            pending.delete(message.id);
            if (message.error) request.reject(new Error(message.error.message));
            else request.resolve(message.result);
            return;
        }
        (listeners.get(message.method) || []).forEach(listener => listener(message.params || {}));
    });

    return {
        async send(method, params = {}) {
            await opened;
            const id = ++sequence;
            socket.send(JSON.stringify({ id, method, params }));
            return new Promise((resolve, reject) => pending.set(id, { resolve, reject }));
        },
        on(method, listener) {
            if (!listeners.has(method)) listeners.set(method, []);
            listeners.get(method).push(listener);
        },
        close() {
            socket.close();
        }
    };
}

function safeUrl(value) {
    try {
        const url = new URL(value);
        url.search = '';
        return url.toString();
    } catch {
        return '';
    }
}

const target = await waitForChrome();
const cdp = connect(target.webSocketDebuggerUrl);
const consoleEvents = [];
const networkEvents = [];
const requestHosts = new Set();
const googleMapRequests = [];
const apiKeyRequests = [];
const rtlPluginRequests = [];
const eoxTileRequests = [];

cdp.on('Runtime.consoleAPICalled', ({ type, args = [] }) => {
    if (!['error', 'warning', 'assert'].includes(type)) return;
    consoleEvents.push({ type, text: args.map(arg => arg.value ?? arg.description ?? '').join(' ') });
});
cdp.on('Runtime.exceptionThrown', ({ exceptionDetails }) => {
    consoleEvents.push({ type: 'exception', text: exceptionDetails?.exception?.description || exceptionDetails?.text || 'Runtime exception' });
});
cdp.on('Log.entryAdded', ({ entry }) => {
    if (!['error', 'warning'].includes(entry.level)) return;
    consoleEvents.push({ type: entry.level, text: entry.text, source: entry.source });
});
cdp.on('Network.requestWillBeSent', ({ request }) => {
    try {
        const url = new URL(request.url);
        requestHosts.add(url.hostname);
        if (/^(?:maps\.googleapis\.com|maps\.gstatic\.com)$/i.test(url.hostname) || /google\.[^/]+\/maps/i.test(request.url)) {
            googleMapRequests.push(safeUrl(request.url));
        }
        if (url.hostname === 'tiles.openfreemap.org' && url.searchParams.has('key')) {
            apiKeyRequests.push(safeUrl(request.url));
        }
        if (url.pathname.endsWith('/assets/dist/mapbox-gl-rtl-text.js')) {
            rtlPluginRequests.push(safeUrl(request.url));
        }
        if (url.hostname === 'tiles.maps.eox.at') {
            eoxTileRequests.push(safeUrl(request.url));
        }
    } catch {
        // Ignore non-URL browser internals.
    }
});
cdp.on('Network.loadingFailed', ({ errorText, blockedReason, canceled, type }) => {
    if (canceled) return;
    networkEvents.push({ kind: 'failed', type, errorText, blockedReason: blockedReason || '' });
});
cdp.on('Network.responseReceived', ({ response, type }) => {
    if (response.status < 400) return;
    networkEvents.push({ kind: 'http', type, status: response.status, url: safeUrl(response.url) });
});

await Promise.all([
    cdp.send('Page.enable'),
    cdp.send('Runtime.enable'),
    cdp.send('Network.enable'),
    cdp.send('Log.enable')
]);

async function evaluate(expression) {
    const result = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true, userGesture: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    return result.result?.value;
}

async function waitFor(expression, timeout = 20000) {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
        try {
            if (await evaluate(expression)) return;
        } catch {
            // Navigation can replace the execution context.
        }
        await delay(150);
    }
    throw new Error(`Timed out waiting for: ${expression}`);
}

async function viewport(width, height) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width,
        height,
        deviceScaleFactor: 1,
        mobile: width < 768,
        screenWidth: width,
        screenHeight: height
    });
}

async function navigate(relativeUrl) {
    await cdp.send('Page.navigate', { url: `${baseUrl}/${relativeUrl.replace(/^\//, '')}` });
    await waitFor(`document.readyState === 'complete'`);
    await delay(300);
}

async function login() {
    await navigate('login.php');
    await evaluate(`(() => {
        document.querySelector('#username').value = 'admin';
        document.querySelector('#password').value = 'admin123';
        const form = document.querySelector('#loginForm');
        form.requestSubmit(form.querySelector('[type="submit"]'));
        return true;
    })()`);
    await waitFor(`document.readyState === 'complete' && !location.pathname.endsWith('/login.php')`);
}

async function screenshot(name) {
    const capture = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false, fromSurface: true });
    const filePath = path.join(evidenceDir, `${name}.png`);
    await writeFile(filePath, Buffer.from(capture.data, 'base64'));
    return path.relative(root, filePath).replaceAll('\\', '/');
}

async function mapState() {
    return evaluate(`(() => {
        const panel = document.querySelector('#selectedMosquePanel');
        const mapElement = document.querySelector('#map');
        const shell = document.querySelector('.map-canvas-shell') || mapElement?.parentElement;
        const attribution = document.querySelector('#map .maplibregl-ctrl-attrib');
        const basemapSwitch = document.querySelector('.map-basemap-switch');
        const panelRect = panel && !panel.hidden ? panel.getBoundingClientRect() : null;
        const shellRect = shell?.getBoundingClientRect() || null;
        const mapRect = mapElement?.getBoundingClientRect() || null;
        const attributionRect = attribution?.getBoundingClientRect() || null;
        const basemapSwitchRect = basemapSwitch?.getBoundingClientRect() || null;
        const rect = value => value ? ({ top: value.top, right: value.right, bottom: value.bottom, left: value.left, width: value.width, height: value.height }) : null;
        const overlaps = (a, b) => Boolean(a && b && a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top);
        const controls = [...document.querySelectorAll('#map .maplibregl-ctrl-top-right button')]
            .map(button => ({ label: button.getAttribute('aria-label') || button.title || '', rect: button.getBoundingClientRect() }))
            .filter(value => value.rect.width > 0 && value.rect.height > 0);
        const testState = window.__mapWorkspaceTest?.getState?.() || {};
        return {
            mapLoaded: mapElement?.dataset.mapReady === 'true' && Boolean(document.querySelector('#map .maplibregl-canvas')),
            provider: mapElement?.dataset.provider || '',
            styleUrl: mapElement?.dataset.styleUrl || '',
            visibleCount: testState.visibleCount ?? null,
            clusterCount: testState.clusterCount ?? null,
            pointCount: testState.pointCount ?? null,
            zoom: testState.zoom ?? null,
            center: testState.center || null,
            pitch: testState.pitch ?? null,
            bearing: testState.bearing ?? null,
            mapMode: testState.mapMode || '',
            satelliteVisibility: testState.satelliteVisibility || '',
            satelliteTileUrl: testState.satelliteTileUrl || '',
            satelliteBelowLabels: Boolean(testState.satelliteBelowLabels),
            satelliteBelowMosques: Boolean(testState.satelliteBelowMosques),
            streetPressed: document.querySelector('#mapStyleStreet')?.getAttribute('aria-pressed') || '',
            satellitePressed: document.querySelector('#mapStyleSatellite')?.getAttribute('aria-pressed') || '',
            storedMapMode: localStorage.getItem('mosques.mapMode') || '',
            satelliteNoticeVisible: !document.querySelector('#satelliteMapNotice')?.hidden,
            satelliteNoticeText: document.querySelector('#satelliteMapNotice')?.textContent?.trim() || '',
            basemapSwitchVisible: Boolean(basemapSwitchRect && basemapSwitchRect.width > 0 && basemapSwitchRect.height > 0),
            basemapSwitchHeight: basemapSwitchRect?.height || 0,
            rtlTextStatus: testState.rtlTextStatus || '',
            selectedMosqueId: testState.selectedMosqueId || '',
            documentScrollY: window.scrollY,
            documentOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            panelExists: Boolean(panel),
            panelVisible: Boolean(panel && !panel.hidden),
            panelTitle: document.querySelector('#selectedMosqueTitle')?.textContent?.trim() || '',
            nationalCode: document.querySelector('#selectedMosqueCode')?.textContent?.trim() || '',
            status: document.querySelector('#selectedMosqueStatus')?.textContent?.trim() || '',
            address: document.querySelector('#selectedMosqueAddress')?.textContent?.trim() || '',
            imam: document.querySelector('#selectedMosqueImam')?.textContent?.trim() || '',
            guideImam: document.querySelector('#selectedMosqueGuideImam')?.textContent?.trim() || '',
            community: document.querySelector('#selectedMosqueCommunity')?.textContent?.trim() || '',
            friday: document.querySelector('#selectedMosqueFriday')?.textContent?.trim() || '',
            detailsHref: document.querySelector('#selectedMosqueDetails')?.getAttribute('href') || '',
            panelRect: rect(panelRect),
            shellRect: rect(shellRect),
            mapRect: rect(mapRect),
            panelInsideShell: Boolean(panelRect && shellRect && panelRect.top >= shellRect.top - 1 && panelRect.bottom <= shellRect.bottom + 1 && panelRect.left >= shellRect.left - 1 && panelRect.right <= shellRect.right + 1),
            panelIsBottomSheet: window.innerWidth > 768 || Boolean(panelRect && shellRect && panelRect.left <= shellRect.left + 12 && panelRect.right >= shellRect.right - 12 && panelRect.bottom <= shellRect.bottom && panelRect.bottom >= shellRect.bottom - 60),
            controlsOnRight: Boolean(mapRect && controls.length > 0 && controls.every(control => control.rect.left + control.rect.width / 2 > mapRect.left + mapRect.width / 2)),
            overlapsControls: controls.some(control => overlaps(panelRect, control.rect)),
            attributionVisible: Boolean(attribution && attributionRect && attributionRect.width > 0 && attributionRect.height > 0),
            attributionText: attribution?.textContent?.trim() || '',
            overlapsAttribution: overlaps(panelRect, attributionRect),
            popupVisible: Boolean(document.querySelector('#map .maplibregl-popup')),
            listScrollTop: document.querySelector('#mosquesList')?.scrollTop || 0,
            activeMosqueId: document.activeElement?.closest?.('.mosque-list-item')?.dataset.mosqueId || ''
        };
    })()`);
}

async function selectListItem(index = 0) {
    const before = await evaluate('window.scrollY');
    const selected = await evaluate(`(() => {
        const buttons = [...document.querySelectorAll('.zoom-to-mosque')];
        const button = buttons[${index}] || buttons[0];
        if (!button) return null;
        button.focus({ preventScroll: true });
        button.click();
        return { id: button.closest('.mosque-list-item')?.dataset.mosqueId || '', label: button.textContent.trim() };
    })()`);
    await delay(700);
    return { selected, before, after: await evaluate('window.scrollY'), state: await mapState() };
}

async function zoomMap() {
    const point = await evaluate(`(() => {
        const value = document.querySelector('#map').getBoundingClientRect();
        return { x: value.left + value.width / 2, y: value.top + value.height / 2 };
    })()`);
    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseWheel', x: point.x, y: point.y, deltaX: 0, deltaY: -420 });
    await delay(900);
    return mapState();
}

async function panMap() {
    const point = await evaluate(`(() => {
        const value = document.querySelector('#map').getBoundingClientRect();
        return { x: value.left + value.width * 0.55, y: value.top + value.height * 0.45 };
    })()`);
    await cdp.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: point.x, y: point.y, button: 'left', clickCount: 1 });
    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: point.x + 90, y: point.y + 40, button: 'left', buttons: 1 });
    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: point.x + 90, y: point.y + 40, button: 'left', clickCount: 1 });
    await delay(900);
    return mapState();
}

async function testTextFilter(property) {
    const selected = await evaluate(`(() => {
        const data = JSON.parse(document.querySelector('#mapPageData').textContent);
        const feature = data.mosqueGeoJson.features.find(item => String(item.properties[${JSON.stringify(property)}] || '').trim());
        const value = String(feature?.properties?.[${JSON.stringify(property)}] || '').trim();
        const input = document.querySelector('#globalSearch');
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return value;
    })()`);
    await delay(650);
    const state = await mapState();
    await evaluate(`document.querySelector('#clearGlobalSearch')?.click()`);
    await delay(350);
    return { property, selected, state };
}

async function testSelectFilter(elementId, preferredValue = '') {
    const selector = JSON.stringify(`#${elementId}`);
    const requestedValue = JSON.stringify(preferredValue);
    const selected = await evaluate(`(() => {
        const select = document.querySelector(${selector});
        const preferred = ${requestedValue};
        const option = [...select.options].find(item => item.value && (!preferred || item.value === preferred))
            || [...select.options].find(item => item.value);
        if (!option) return '';
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        return option.value;
    })()`);
    await delay(500);
    const state = await mapState();
    await evaluate(`(() => {
        const select = document.querySelector(${selector});
        select.value = '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    })()`);
    await delay(350);
    return { elementId, selected, state };
}

const viewports = [
    [1920, 1080],
    [1440, 1000],
    [1024, 900],
    [768, 1024],
    [430, 932],
    [390, 844],
    [360, 800]
];

const result = {
    generatedAt: new Date().toISOString(),
    baseline: null,
    interactions: {},
    filters: [],
    viewports: [],
    formPicker: null,
    console: consoleEvents,
    network: networkEvents,
    requestHosts: [],
    assertions: [],
    failures: []
};

function check(name, ok, detail = '') {
    result.assertions.push({ name, ok: Boolean(ok), detail });
    if (!ok) result.failures.push({ name, detail });
}

function sameCamera(left, right) {
    const close = (a, b) => Math.abs(Number(a) - Number(b)) < 1e-7;
    return left.center?.length === 2
        && right.center?.length === 2
        && left.center.every((value, index) => close(value, right.center[index]))
        && close(left.zoom, right.zoom)
        && close(left.pitch, right.pitch)
        && close(left.bearing, right.bearing);
}

try {
    await viewport(1440, 1000);
    await login();
    await navigate('mosque_maps.php');
    await waitFor(`document.querySelector('#map')?.dataset.mapReady === 'true' && window.__mapWorkspaceTest?.getState().ready`);
    await delay(900);

    result.baseline = await mapState();
    result.baseline.screenshot = await screenshot('maplibre-unselected-1440x1000');
    result.rtlPluginServed = await evaluate(`fetch(new URL('assets/dist/mapbox-gl-rtl-text.js', document.baseURI)).then(response => response.ok && response.url.startsWith(location.origin))`);
    result.fullscreen = await evaluate(`(async () => {
        const button = document.querySelector('#map .maplibregl-ctrl-fullscreen');
        button?.click();
        await new Promise(resolve => setTimeout(resolve, 250));
        const active = Boolean(document.fullscreenElement);
        if (active) await document.exitFullscreen();
        return { buttonExists: Boolean(button), active };
    })()`);
    await delay(250);
    const eoxRequestsBeforeSatellite = eoxTileRequests.length;

    const clusterBefore = await mapState();
    const clusterExpansion = await evaluate(`window.__mapWorkspaceTest.expandFirstCluster()`);
    await delay(900);
    const clusterAfter = await mapState();

    const firstSelection = await selectListItem(0);
    firstSelection.state.screenshot = await screenshot('maplibre-selected-1440x1000');
    const modeBeforeSatellite = firstSelection.state;
    await evaluate(`document.querySelector('#mapStyleSatellite')?.click()`);
    await waitFor(`document.querySelector('#map')?.dataset.mapMode === 'satellite'`);
    await delay(1600);
    const satelliteActive = await mapState();
    satelliteActive.screenshot = await screenshot('maplibre-satellite-selected-1440x1000');
    const eoxRequestsAfterSatellite = eoxTileRequests.length;
    await evaluate(`document.querySelector('#mapStyleStreet')?.click()`);
    await waitFor(`document.querySelector('#map')?.dataset.mapMode === 'street'`);
    await delay(300);
    const streetRestored = await mapState();

    await evaluate(`document.querySelector('#mapStyleSatellite')?.click()`);
    await waitFor(`localStorage.getItem('mosques.mapMode') === 'satellite'`);
    await navigate('mosque_maps.php');
    await waitFor(`document.querySelector('#map')?.dataset.mapReady === 'true' && document.querySelector('#map')?.dataset.mapMode === 'satellite'`);
    await delay(900);
    const satellitePreferenceRestored = await mapState();
    await evaluate(`window.__mapWorkspaceTest.failSatellite()`);
    await waitFor(`document.querySelector('#map')?.dataset.mapMode === 'street'`);
    const satelliteFailureFallback = await mapState();
    await selectListItem(0);
    await evaluate(`(() => { const list = document.querySelector('#mosquesList'); list.scrollTop = list.scrollHeight; return list.scrollTop; })()`);
    await delay(300);
    const afterListScroll = await mapState();
    const afterZoom = await zoomMap();
    const afterPan = await panMap();
    await evaluate(`window.scrollTo({ top: Math.min(220, Math.max(0, document.documentElement.scrollHeight - innerHeight)), behavior: 'instant' })`);
    await delay(250);
    const afterPageScroll = await mapState();
    await evaluate(`window.scrollTo({ top: 0, behavior: 'instant' })`);
    const secondSelection = await selectListItem(1);

    await evaluate(`document.querySelector('#selectedMosqueClose')?.click()`);
    await delay(250);
    const closeState = await mapState();
    await selectListItem(0);
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape' });
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape' });
    await delay(250);
    const escapeState = await mapState();

    await selectListItem(0);
    await evaluate(`(() => {
        const selected = document.querySelector('.mosque-list-item[aria-current="true"]');
        const currentCommunity = selected?.dataset.community || '';
        const filter = document.querySelector('#communityFilter');
        const option = [...filter.options].find(item => item.value && item.value !== currentCommunity);
        if (!option) return false;
        filter.value = option.value;
        filter.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    })()`);
    await delay(500);
    const filteredSelection = await mapState();
    await evaluate(`document.querySelector('#clearAllFilters')?.click()`);
    await delay(350);

    result.filters.push(await testTextFilter('mosque_name'));
    result.filters.push(await testTextFilter('address'));
    result.filters.push(await testTextFilter('national_code'));
    result.filters.push(await testTextFilter('imam_name'));
    result.filters.push(await testSelectFilter('communityFilter'));
    result.filters.push(await testSelectFilter('statusFilter'));
    result.filters.push(await testSelectFilter('fridayFilter', '1'));

    result.interactions = {
        clusterBefore,
        clusterExpansion,
        clusterAfter,
        firstSelection,
        modeBeforeSatellite,
        satelliteActive,
        streetRestored,
        satellitePreferenceRestored,
        satelliteFailureFallback,
        eoxRequestsBeforeSatellite,
        eoxRequestsAfterSatellite,
        afterListScroll,
        afterZoom,
        afterPan,
        afterPageScroll,
        secondSelection,
        closeState,
        escapeState,
        filteredSelection
    };

    for (const [width, height] of viewports) {
        await viewport(width, height);
        await navigate('mosque_maps.php');
        await waitFor(`document.querySelector('#map')?.dataset.mapReady === 'true' && window.__mapWorkspaceTest?.getState().ready`);
        await delay(650);
        const selection = await selectListItem(0);
        selection.state.screenshot = await screenshot(`maplibre-selected-${width}x${height}`);
        await evaluate(`document.querySelector('#mapStyleSatellite')?.click()`);
        await waitFor(`document.querySelector('#map')?.dataset.mapMode === 'satellite'`);
        await delay(700);
        const satelliteState = await mapState();
        await evaluate(`document.querySelector('#mapStyleStreet')?.click()`);
        await waitFor(`document.querySelector('#map')?.dataset.mapMode === 'street'`);
        result.viewports.push({ width, height, ...selection, satelliteState });
    }

    await viewport(1024, 900);
    await navigate('add_mosque.php');
    await evaluate(`document.querySelector('#showMapBtn')?.click()`);
    await waitFor(`document.querySelector('#map')?.dataset.rtlTextReady === 'true' && Boolean(document.querySelector('#mapContainer[data-provider="maplibre"] .maplibregl-canvas'))`);
    await delay(700);
    result.formPicker = await evaluate(`(() => ({
        mapLoaded: Boolean(document.querySelector('#mapContainer .maplibregl-canvas')),
        provider: document.querySelector('#mapContainer')?.dataset.provider || '',
        rtlTextReady: document.querySelector('#map')?.dataset.rtlTextReady === 'true',
        attribution: document.querySelector('#mapContainer .maplibregl-ctrl-attrib')?.textContent?.trim() || '',
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1
    }))()`);

    check('MapLibre map loads', result.baseline.mapLoaded);
    check('Map provider is MapLibre', result.baseline.provider === 'maplibre', result.baseline.provider);
    check('Fullscreen control activates the map workspace', result.fullscreen.buttonExists && result.fullscreen.active, JSON.stringify(result.fullscreen));
    check('Configured Liberty style is used', result.baseline.styleUrl.includes('tiles.openfreemap.org/styles/liberty'), result.baseline.styleUrl);
    check('Arabic RTL shaping plugin is loaded', result.baseline.rtlTextStatus === 'loaded', result.baseline.rtlTextStatus);
    check('Arabic RTL shaping plugin is served locally', result.rtlPluginServed && rtlPluginRequests.some(url => url.startsWith(baseUrl)), rtlPluginRequests.join(', '));
    check('All valid coordinate records reach the GeoJSON map', Number(result.baseline.visibleCount) > 0, String(result.baseline.visibleCount));
    check('Clusters render', Number(clusterBefore.clusterCount) > 0, String(clusterBefore.clusterCount));
    check('Cluster click resolves expansion zoom', Boolean(clusterExpansion && clusterExpansion.zoom > clusterExpansion.before), JSON.stringify(clusterExpansion));
    check('Cluster click smoothly increases zoom', Number(clusterAfter.zoom) > Number(clusterBefore.zoom), `${clusterBefore.zoom} -> ${clusterAfter.zoom}`);
    check('Selection panel is application-owned and visible', firstSelection.state.panelExists && firstSelection.state.panelVisible);
    check('Selected mosque name is present', firstSelection.state.panelTitle.length > 0);
    check('Selected national code is present', firstSelection.state.nationalCode.length > 0);
    check('Selected status is present', firstSelection.state.status.length > 0);
    check('Selected address is present', firstSelection.state.address.length > 0);
    check('Selected imam is present', firstSelection.state.imam.length > 0);
    check('Selected guide imam is present', firstSelection.state.guideImam.length > 0);
    check('Selected community is present', firstSelection.state.community.length > 0);
    check('Selected Friday-prayer value is present', ['نعم', 'لا'].includes(firstSelection.state.friday), firstSelection.state.friday);
    check('Details link remains internal', /^mosques\.php\?/.test(firstSelection.state.detailsHref), firstSelection.state.detailsHref);
    check('Selection keeps document scroll fixed', firstSelection.before === firstSelection.after, `${firstSelection.before} -> ${firstSelection.after}`);
    check('List scroll keeps selection panel visible', afterListScroll.panelVisible);
    check('Zoom keeps selection panel visible', afterZoom.panelVisible);
    check('Pan keeps selection panel visible', afterPan.panelVisible);
    check('Page scroll keeps selection panel visible', afterPageScroll.panelVisible);
    check('Second selection updates the same panel', secondSelection.state.panelVisible && secondSelection.selected?.id !== firstSelection.selected?.id);
    check('Close button hides panel', !closeState.panelVisible);
    check('Close restores focus without page scroll', closeState.activeMosqueId === secondSelection.selected?.id, closeState.activeMosqueId);
    check('Escape hides panel', !escapeState.panelVisible);
    check('Filtering out selection closes panel', !filteredSelection.panelVisible);
    check('No MapLibre popup is used for full details', !firstSelection.state.popupVisible);
    check('Satellite WMTS keeps z/y/x order', satelliteActive.satelliteTileUrl.endsWith('/{z}/{y}/{x}.jpg'), satelliteActive.satelliteTileUrl);
    check('Satellite raster stays below OpenFreeMap labels', satelliteActive.satelliteBelowLabels);
    check('Satellite raster stays below mosque layers', satelliteActive.satelliteBelowMosques);
    check('Normal mode makes no EOX tile requests', eoxRequestsBeforeSatellite === 0, String(eoxRequestsBeforeSatellite));
    check('Satellite mode starts EOX tile requests', eoxRequestsAfterSatellite > eoxRequestsBeforeSatellite, `${eoxRequestsBeforeSatellite} -> ${eoxRequestsAfterSatellite}`);
    check('Satellite toggle activates only the raster layer', satelliteActive.mapMode === 'satellite' && satelliteActive.satelliteVisibility === 'visible' && satelliteActive.streetPressed === 'false' && satelliteActive.satellitePressed === 'true');
    check('Mosque markers stay visible over satellite imagery', Number(satelliteActive.pointCount) > 0, String(satelliteActive.pointCount));
    check('Satellite attribution is visible', ['EOxCloudless', 'EOX IT Services GmbH', 'Copernicus Sentinel data 2025', 'OpenFreeMap'].every(value => satelliteActive.attributionText.includes(value)), satelliteActive.attributionText);
    check('Satellite attribution does not overlap selected mosque panel', !satelliteActive.overlapsAttribution);
    check('Satellite switch preserves camera', sameCamera(modeBeforeSatellite, satelliteActive), JSON.stringify({ before: modeBeforeSatellite, after: satelliteActive }));
    check('Satellite switch preserves selected mosque and panel', satelliteActive.panelVisible && satelliteActive.selectedMosqueId === modeBeforeSatellite.selectedMosqueId);
    check('Satellite switch preserves page scroll', satelliteActive.documentScrollY === modeBeforeSatellite.documentScrollY, `${modeBeforeSatellite.documentScrollY} -> ${satelliteActive.documentScrollY}`);
    check('Street toggle hides only the raster layer', streetRestored.mapMode === 'street' && streetRestored.satelliteVisibility === 'none' && streetRestored.streetPressed === 'true' && streetRestored.satellitePressed === 'false');
    check('Street toggle preserves camera', sameCamera(satelliteActive, streetRestored), JSON.stringify({ before: satelliteActive, after: streetRestored }));
    check('Street toggle preserves selected mosque and panel', streetRestored.panelVisible && streetRestored.selectedMosqueId === satelliteActive.selectedMosqueId);
    check('Saved satellite preference survives refresh', satellitePreferenceRestored.mapMode === 'satellite' && satellitePreferenceRestored.satelliteVisibility === 'visible' && satellitePreferenceRestored.storedMapMode === 'satellite');
    check('Satellite failure falls back once with Arabic notice', satelliteFailureFallback.mapMode === 'street' && satelliteFailureFallback.satelliteVisibility === 'none' && satelliteFailureFallback.storedMapMode === 'street' && satelliteFailureFallback.satelliteNoticeVisible && satelliteFailureFallback.satelliteNoticeText === 'تعذر تحميل صور القمر الصناعي. تم الرجوع إلى الخريطة العادية.');

    result.filters.forEach(entry => {
        check(`Filter works: ${entry.property || entry.elementId}`, Boolean(entry.selected) && Number(entry.state.visibleCount) > 0, `${entry.selected}: ${entry.state.visibleCount}`);
    });
    result.viewports.forEach(entry => {
        const label = `${entry.width}x${entry.height}`;
        check(`${label}: selection panel visible`, entry.state.panelVisible);
        check(`${label}: panel stays inside map`, entry.state.panelInsideShell);
        check(`${label}: mobile panel is a bottom sheet`, entry.width >= 768 || entry.state.panelIsBottomSheet);
        check(`${label}: no horizontal overflow`, !entry.state.documentOverflow);
        check(`${label}: RTL controls stay on the right`, entry.state.controlsOnRight);
        check(`${label}: panel avoids controls`, !entry.state.overlapsControls);
        check(`${label}: OpenFreeMap attribution is visible`, entry.state.attributionVisible && entry.state.attributionText.includes('OpenFreeMap'));
        check(`${label}: panel avoids attribution`, !entry.state.overlapsAttribution);
        check(`${label}: basemap switch stays visible and compact`, entry.state.basemapSwitchVisible && entry.state.basemapSwitchHeight <= 40, String(entry.state.basemapSwitchHeight));
        check(`${label}: satellite mode preserves selected mosque`, entry.satelliteState.mapMode === 'satellite' && entry.satelliteState.panelVisible && entry.satelliteState.selectedMosqueId === entry.state.selectedMosqueId);
        check(`${label}: satellite layout has no overflow`, !entry.satelliteState.documentOverflow);
        check(`${label}: satellite panel avoids attribution`, !entry.satelliteState.overlapsAttribution);
        check(`${label}: satellite attribution is visible`, entry.satelliteState.attributionVisible && entry.satelliteState.attributionText.includes('EOxCloudless'));
    });

    check('Create-form coordinate picker uses MapLibre', result.formPicker.mapLoaded && result.formPicker.provider === 'maplibre');
    check('Create-form coordinate picker has RTL shaping', result.formPicker.rtlTextReady);
    check('Create-form attribution is visible', result.formPicker.attribution.includes('OpenFreeMap'));
    check('Create-form map has no horizontal overflow', !result.formPicker.overflow);
    check('OpenFreeMap requests are present', requestHosts.has('tiles.openfreemap.org'));
    check('No Google Maps requests', googleMapRequests.length === 0, googleMapRequests.join(', '));
    check('No map API-key requests', apiKeyRequests.length === 0, apiKeyRequests.join(', '));
    const errors = consoleEvents.filter(entry => ['error', 'exception', 'assert'].includes(entry.type));
    check('No console errors', errors.length === 0, JSON.stringify(errors));
    check('No missing or failed map assets', networkEvents.length === 0, JSON.stringify(networkEvents));
} catch (error) {
    result.failures.push({ name: 'Browser regression completed', detail: error.stack || error.message });
} finally {
    result.console = consoleEvents;
    result.network = networkEvents;
    result.requestHosts = [...requestHosts].sort();
    result.rtlPluginRequests = rtlPluginRequests;
    await writeFile(resultPath, JSON.stringify(result, null, 2));
    cdp.close();
    chrome.kill();
    await rm(profileDir, { recursive: true, force: true }).catch(() => {});
}

console.log(JSON.stringify({
    resultPath,
    screenshots: result.viewports.length + (result.baseline?.screenshot ? 3 : 0),
    assertions: result.assertions.length,
    failures: result.failures
}, null, 2));

if (result.failures.length > 0) process.exitCode = 1;
