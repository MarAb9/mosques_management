import { spawn } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = (process.env.MAP_TEST_BASE_URL || 'http://127.0.0.1:8085').replace(/\/$/, '');
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const port = Number(process.env.MAP_TEST_DEBUG_PORT || 9300 + Math.floor(Math.random() * 500));
const root = process.cwd();
const evidenceDir = path.join(root, 'docs', 'screenshots', 'leaflet-legacy');
const profileDir = path.join(root, 'storage', `.leaflet-chrome-${Date.now()}`);
const resultPath = path.join(root, 'storage', 'leaflet-browser-result.json');
const delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
await rm(evidenceDir, { recursive: true, force: true });
await mkdir(evidenceDir, { recursive: true });
await mkdir(profileDir, { recursive: true });

const chrome = spawn(chromePath, [
    '--headless=new', '--disable-dev-shm-usage', '--disable-background-networking',
    '--disable-component-update', '--disable-default-apps', '--disable-extensions',
    '--disable-features=Translate,MediaRouter,OptimizationHints', '--enable-unsafe-swiftshader',
    '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
    `--remote-debugging-port=${port}`, `--user-data-dir=${profileDir}`, 'about:blank'
], { stdio: 'ignore' });

async function waitForChrome() {
    const deadline = Date.now() + 15000;
    while (Date.now() < deadline) {
        try {
            const response = await fetch(`http://127.0.0.1:${port}/json/list`);
            const targets = response.ok ? await response.json() : [];
            const page = targets.find(target => target.type === 'page');
            if (page) return page;
        } catch (_) {}
        await delay(150);
    }
    throw new Error('Chrome DevTools endpoint did not become ready.');
}

function connect(url) {
    const socket = new WebSocket(url);
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
            return message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
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
        close: () => socket.close()
    };
}

const target = await waitForChrome();
const cdp = connect(target.webSocketDebuggerUrl);
const consoleEvents = [];
const failedRequests = [];
const requests = [];
const checks = [];
const check = (name, ok, detail = '') => checks.push({ name, ok: Boolean(ok), detail });
cdp.on('Runtime.consoleAPICalled', ({ type, args = [] }) => {
    if (['error', 'warning', 'assert'].includes(type)) consoleEvents.push({ type, text: args.map(arg => arg.value ?? arg.description ?? '').join(' ') });
});
cdp.on('Runtime.exceptionThrown', ({ exceptionDetails }) => consoleEvents.push({ type: 'exception', text: exceptionDetails?.exception?.description || exceptionDetails?.text }));
cdp.on('Log.entryAdded', ({ entry }) => {
    if (['error', 'warning'].includes(entry.level)) consoleEvents.push({ type: entry.level, text: entry.text });
});
cdp.on('Network.requestWillBeSent', ({ request }) => {
    try {
        const url = new URL(request.url);
        requests.push({
            host: url.hostname,
            path: url.pathname,
            query: [...url.searchParams.keys()].sort(),
            hasKey: url.searchParams.has('key'),
            method: request.method
        });
    } catch (_) {}
});
cdp.on('Network.loadingFailed', ({ errorText, canceled, type }) => {
    if (!canceled) failedRequests.push({ errorText, type });
});
cdp.on('Network.responseReceived', ({ response, type }) => {
    if (response.status >= 400) failedRequests.push({ status: response.status, type, url: response.url.split('?')[0] });
});
await Promise.all([
    cdp.send('Page.enable'), cdp.send('Runtime.enable'), cdp.send('Network.enable'), cdp.send('Log.enable')
]);

async function evaluate(expression) {
    const result = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true, userGesture: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    return result.result?.value;
}

async function waitFor(expression, timeout = 20000) {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
        try { if (await evaluate(expression)) return; } catch (_) {}
        await delay(150);
    }
    throw new Error(`Timed out waiting for: ${expression}`);
}

async function viewport(width, height) {
    await cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768, screenWidth: width, screenHeight: height });
}

async function navigate(pathname) {
    await cdp.send('Page.navigate', { url: `${baseUrl}/${pathname.replace(/^\//, '')}` });
    await waitFor(`document.readyState === 'complete'`);
    await delay(250);
}

async function screenshot(name) {
    const capture = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false, fromSurface: true });
    const file = path.join(evidenceDir, `${name}.png`);
    await writeFile(file, Buffer.from(capture.data, 'base64'));
    return path.relative(root, file).replaceAll('\\', '/');
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

async function mapState() {
    return evaluate(`(() => {
        const map = document.querySelector('#map');
        const panel = document.querySelector('#selectedMosquePanel');
        const panelRect = panel?.getBoundingClientRect();
        const attribution = document.querySelector('#map .leaflet-control-attribution');
        const attributionRect = attribution?.getBoundingClientRect();
        const shellRect = document.querySelector('.map-canvas-shell')?.getBoundingClientRect();
        const leaflet = window.__leafletWorkspaceTest;
        return {
            ready: map?.dataset.mapReady === 'true',
            provider: map?.dataset.provider || '',
            mode: map?.dataset.mapMode || '',
            zoom: leaflet?.map?.getZoom(),
            maxZoom: leaflet?.map?.getMaxZoom(),
            visibleCount: Number(map?.dataset.visibleCount || 0),
            markerCount: leaflet?.clusters?.getLayers()?.length || 0,
            clustersVisible: document.querySelectorAll('#map .mosque-cluster').length,
            markersVisible: document.querySelectorAll('#map .mosque-marker').length,
            streetTiles: document.querySelectorAll('#map img.leaflet-tile-loaded[src*="tile.openstreetmap.org"]').length,
            selectionVisible: Boolean(panel && !panel.hidden),
            selectionInViewport: Boolean(panelRect && !panel.hidden && panelRect.top >= 0 && panelRect.bottom <= innerHeight),
            selectedMarkerHighlighted: Boolean(leaflet?.selectedMarkerHighlighted),
            selectedListItems: document.querySelectorAll('.mosque-list-item[aria-current="true"]').length,
            attributionText: attribution?.textContent?.trim() || '',
            osmLink: attribution?.querySelector('a[href*="openstreetmap.org"]')?.href || '',
            attributionVisible: Boolean(attributionRect && shellRect
                && attributionRect.width > 0
                && attributionRect.bottom <= shellRect.bottom
                && attributionRect.left >= shellRect.left),
            leafletPrefix: Boolean(attribution?.querySelector('a[href*="leafletjs.com"]')),
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
            scrollY: window.scrollY,
            controls: document.querySelectorAll('#map .leaflet-control-zoom a').length,
            errors: map?.dataset.mapLoadError || ''
        };
    })()`);
}
const result = { baseUrl, screenshots: [], states: {}, requests, consoleEvents, failedRequests, checks };
let fatalError = '';
try {
    await viewport(1440, 1000);
    await login();
    await navigate('mosque_maps.php');
    await waitFor(`document.querySelector('#map')?.dataset.mapReady === 'true' && Boolean(window.__leafletWorkspaceTest)`);
    await waitFor(`window.__leafletWorkspaceTest.visibleCount > 0`);
    await waitFor(`document.querySelectorAll('#map img.leaflet-tile-loaded[src*="tile.openstreetmap.org"]').length > 0`, 30000);
    result.states.baseline = await mapState();
    result.screenshots.push(await screenshot('legacy-street-1440x1000'));

    check('Leaflet workspace initializes', result.states.baseline.ready && result.states.baseline.provider === 'leaflet', result.states.baseline);
    check('Legacy OpenStreetMap raster is the default', result.states.baseline.mode === 'street'
        && result.states.baseline.streetTiles > 0
        && requests.some(request => request.host.endsWith('tile.openstreetmap.org')), requests);
    check('Leaflet owns zoom, pan, markers, and clusters', result.states.baseline.maxZoom === 22 && result.states.baseline.controls === 2, result.states.baseline);
    check('Full dataset is clustered once', result.states.baseline.markerCount === result.states.baseline.visibleCount
        && result.states.baseline.markerCount > 0 && result.states.baseline.clustersVisible > 0, result.states.baseline);
    check('Required attribution is visible', result.states.baseline.attributionVisible
        && result.states.baseline.osmLink.includes('openstreetmap.org')
        && !result.states.baseline.leafletPrefix, result.states.baseline);
    check('Marker clusters can spiderfy', Boolean(await evaluate(`window.__leafletWorkspaceTest.spiderfyFirstCluster()`)));

    const firstLoadTime = await evaluate('performance.timeOrigin');
    await evaluate(`document.querySelector('#refreshMap').click()`);
    await waitFor(`performance.timeOrigin !== ${firstLoadTime}`);
    await waitFor(`document.querySelector('#map')?.dataset.mapReady === 'true' && Boolean(window.__leafletWorkspaceTest)`);
    await waitFor(`document.querySelectorAll('#map img.leaflet-tile-loaded[src*="tile.openstreetmap.org"]').length > 0`, 30000);
    check('Refresh restores the working street map', (await mapState()).mode === 'street' && (await mapState()).streetTiles > 0);

    const scrollBeforeSelection = await evaluate('window.scrollY');
    await evaluate(`document.querySelector('#mosquesList [data-mosque-id]').click()`);
    await waitFor(`!document.querySelector('#selectedMosquePanel').hidden`);
    result.states.selected = await mapState();
    check('Mosque card and selected marker are stable', result.states.selected.selectionVisible
        && result.states.selected.selectedMarkerHighlighted && result.states.selected.selectedListItems === 1, result.states.selected);
    check('Selection does not scroll the page', result.states.selected.scrollY === scrollBeforeSelection, result.states.selected);
    await evaluate(`document.querySelector('#selectedMosqueClose').click()`);
    await waitFor(`document.querySelector('#selectedMosquePanel').hidden`);
    check('Marker click opens the mosque card', Boolean(await evaluate(`window.__leafletWorkspaceTest.clickFirstMarker()`)));
    await waitFor(`!document.querySelector('#selectedMosquePanel').hidden`);

    await evaluate(`(() => {
        const select = document.querySelector('#communityFilter');
        const community = document.querySelector('#selectedMosqueCommunity')?.textContent?.trim();
        const option = [...select.options].find(item => item.value === community);
        if (!option) return false;
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    })()`);
    await delay(250);
    check('Filters preserve an included selection', (await mapState()).selectionVisible && (await mapState()).selectedMarkerHighlighted);

    const beforeSearch = await evaluate('window.__leafletWorkspaceTest.visibleCount');
    await evaluate(`(() => {
        const input = document.querySelector('#globalSearch');
        input.value = document.querySelector('#selectedMosqueTitle').textContent.trim().slice(0, 3);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    })()`);
    await delay(300);
    check('Search synchronizes the list and markers', await evaluate(`window.__leafletWorkspaceTest.visibleCount > 0 && window.__leafletWorkspaceTest.visibleCount <= ${beforeSearch}`));
    await evaluate(`document.querySelector('#clearGlobalSearch').click()`);

    await evaluate(`document.querySelector('#fitToMarkers').click()`);
    await delay(200);
    check('Fit-all keeps the map usable', (await mapState()).ready && !(await mapState()).errors);

    await evaluate(`(() => { window.__leafletWorkspaceTest.map.setZoom(18, { animate: false }); return true; })()`);
    await waitFor(`document.querySelectorAll('#map img.leaflet-tile-loaded[src*="tile.openstreetmap.org/18/"]').length > 0`, 30000);
    check('Street detail renders at zoom 18', (await mapState()).mode === 'street' && (await mapState()).streetTiles > 0);
    result.screenshots.push(await screenshot('legacy-street-selected-z18'));

    await evaluate(`document.querySelector('#mapFullscreen').click()`);
    await waitFor(`Boolean(document.fullscreenElement)`);
    check('Fullscreen opens without losing selection', (await mapState()).selectionVisible);
    await evaluate(`document.exitFullscreen()`);
    await waitFor(`!document.fullscreenElement`);

    await evaluate(`(() => { for (let i = 0; i < 4; i++) window.__leafletWorkspaceTest.recordLayerFailure(new Error('tile failed')); return true; })()`);
    check('Tile errors keep the street map active', (await mapState()).mode === 'street');
    check('Tile errors produce one non-blocking message', await evaluate(`document.querySelector('#mapNotice [data-map-notice-text]')?.textContent === 'تعذر تحميل جزء من الخريطة. حاول مرة أخرى.'`));

    for (const zoom of [9, 12, 15, 17, 19]) {
        await evaluate(`(() => { window.__leafletWorkspaceTest.map.setView([34.6814, -1.9086], ${zoom}, { animate: false }); return true; })()`);
        await waitFor(`window.__leafletWorkspaceTest.map.getZoom() === ${zoom}`);
        await waitFor(`document.querySelectorAll('#map img.leaflet-tile-loaded[src*="tile.openstreetmap.org/${zoom}/"]').length > 0`, 45000);
        const state = await mapState();
        check(`OpenStreetMap renders at zoom ${zoom}`, state.mode === 'street' && state.streetTiles > 0, state);
        check(`Mosque card survives zoom ${zoom}`, state.selectionVisible && state.selectedMarkerHighlighted, state);
        result.screenshots.push(await screenshot(`legacy-street-berkane-z${zoom}`));
    }

    await evaluate(`(() => { window.__leafletWorkspaceTest.map.panBy([80, 40], { animate: false }); return true; })()`);
    await delay(180);
    check('Mosque card survives panning', (await mapState()).selectionVisible && (await mapState()).selectedMarkerHighlighted);

    await evaluate(`window.__leafletWorkspaceTest.selectFirst()`);
    const viewports = [[1920, 1080], [1440, 1000], [1280, 800], [1024, 900], [768, 1024], [430, 932], [390, 844], [360, 800]];
    for (const [width, height] of viewports) {
        await viewport(width, height);
        await evaluate(`(() => { window.dispatchEvent(new Event('resize')); window.__leafletWorkspaceTest.map.invalidateSize(); return true; })()`);
        await evaluate(`(() => {
            const shell = document.querySelector('.map-canvas-shell');
            document.documentElement.style.scrollBehavior = 'auto';
            window.scrollTo(0, Math.max(0, shell.getBoundingClientRect().top + scrollY - (innerHeight - shell.offsetHeight) / 2));
            return true;
        })()`);
        await delay(180);
        await waitFor(`document.querySelectorAll('#map img.leaflet-tile-loaded[src*="tile.openstreetmap.org"]').length > 0`, 30000);
        const state = await mapState();
        check(`${width}x${height} has no page overflow`, !state.overflow, state);
        check(`${width}x${height} keeps attribution visible`, state.attributionVisible, state);
        check(`${width}x${height} keeps the mosque card in view`, state.selectionVisible && state.selectionInViewport, state);
        result.screenshots.push(await screenshot(`legacy-selected-${width}x${height}`));
        if (width === 430) {
            await evaluate(`document.querySelector('[data-map-view="list"]').click()`);
            check('Mobile list tab shows one view at a time', await evaluate(`getComputedStyle(document.querySelector('#mapListColumn')).display !== 'none'
                && getComputedStyle(document.querySelector('#mapCanvasColumn')).display === 'none'`));
            result.screenshots.push(await screenshot('legacy-list-430x932'));
            await evaluate(`document.querySelector('[data-map-view="map"]').click()`);
        }
    }

    await viewport(1440, 1000);
    await navigate('add_mosque.php');
    await evaluate(`document.querySelector('#showMapBtn').click()`);
    await waitFor(`document.querySelector('#mapContainer #map')?.dataset.mapReady === 'true'`);
    await waitFor(`document.querySelectorAll('#mapContainer img.leaflet-tile-loaded[src*="tile.openstreetmap.org"]').length > 0`, 30000);
    result.states.formPicker = await evaluate(`(() => ({
        provider: document.querySelector('#mapContainer')?.dataset.provider,
        leaflet: Boolean(document.querySelector('#mapContainer .leaflet-container')),
        raster: Boolean(document.querySelector('#mapContainer img.leaflet-tile-loaded[src*="tile.openstreetmap.org"]')),
        attribution: document.querySelector('#mapContainer .leaflet-control-attribution')?.textContent?.trim() || ''
    }))()`);
    check('Coordinate picker uses Leaflet and the legacy street raster', result.states.formPicker.provider === 'leaflet'
        && result.states.formPicker.leaflet && result.states.formPicker.raster, result.states.formPicker);

    check('No tile API key is exposed', !requests.some(request => request.hasKey), requests);
    check('No routing UI or request remains', !requests.some(request => request.path.includes('map_route'))
        && !Boolean(await evaluate(`document.querySelector('#routeToMosque, #routePanel, a[href*="google.com/maps"]')`)));
    check('No rejected provider runtime request exists', !requests.some(request => /arcgis|esri|maplibre|maptiler|openfreemap|eox|google\.com\/maps/i.test(`${request.host}${request.path}`)), requests);
    check('No browser console or CSP errors', consoleEvents.length === 0, consoleEvents);
    check('No failed requests or tile storm', failedRequests.length === 0, failedRequests);
} catch (error) {
    fatalError = error.stack || error.message;
    check('Browser suite completed', false, fatalError);
} finally {
    result.fatalError = fatalError;
    result.passed = checks.filter(item => item.ok).length;
    result.failed = checks.filter(item => !item.ok).length;
    await writeFile(resultPath, JSON.stringify(result, null, 2));
    cdp.close();
    chrome.kill();
    await delay(250);
    await rm(profileDir, { recursive: true, force: true });
}

console.log(`Leaflet browser verification: ${result.passed} passed, ${result.failed} failed`);
if (result.failed) console.error(checks.filter(item => !item.ok));
process.exit(result.failed === 0 ? 0 : 1);
