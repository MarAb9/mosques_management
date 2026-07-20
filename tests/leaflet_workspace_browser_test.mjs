import { spawn } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = (process.env.MAP_TEST_BASE_URL || 'http://127.0.0.1:8085').replace(/\/$/, '');
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const port = Number(process.env.MAP_TEST_DEBUG_PORT || 9445);
const root = process.cwd();
const evidenceDir = path.join(root, 'docs', 'screenshots', 'leaflet-map');
const profileDir = path.join(root, 'storage', `.leaflet-chrome-${Date.now()}`);
const resultPath = path.join(root, 'storage', 'leaflet-browser-result.json');
const delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
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
const png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
const routePayload = {
    data: {
        distance_km: 4.2,
        duration_minutes: 11,
        geometry: [[34.6814, -1.9086], [34.689, -1.92], [34.7, -1.94]],
        steps: [{ text: 'اتجه شمالاً ثم واصل إلى المسجد', distance_km: 4.2, duration_minutes: 11 }]
    }
};

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
        requests.push({ host: url.hostname, path: url.pathname, method: request.method });
    } catch (_) {}
});
cdp.on('Network.loadingFailed', ({ errorText, canceled, type }) => {
    if (!canceled) failedRequests.push({ errorText, type });
});
cdp.on('Network.responseReceived', ({ response, type }) => {
    if (response.status >= 400) failedRequests.push({ status: response.status, type, url: response.url.split('?')[0] });
});
cdp.on('Fetch.requestPaused', async ({ requestId, request }) => {
    try {
        const url = new URL(request.url);
        if (url.hostname === 'static-map-tiles-api.arcgis.com' || url.hostname === 'ibasemaps-api.arcgis.com') {
            await cdp.send('Fetch.fulfillRequest', { requestId, responseCode: 200, responseHeaders: [{ name: 'Content-Type', value: 'image/png' }], body: png });
        } else if (url.pathname.endsWith('/ajax/map_route.php')) {
            await cdp.send('Fetch.fulfillRequest', { requestId, responseCode: 200, responseHeaders: [{ name: 'Content-Type', value: 'application/json; charset=UTF-8' }], body: Buffer.from(JSON.stringify(routePayload)).toString('base64') });
        } else {
            await cdp.send('Fetch.continueRequest', { requestId });
        }
    } catch (_) {
        await cdp.send('Fetch.continueRequest', { requestId }).catch(() => {});
    }
});

await Promise.all([
    cdp.send('Page.enable'), cdp.send('Runtime.enable'), cdp.send('Network.enable'), cdp.send('Log.enable'),
    cdp.send('Fetch.enable', { patterns: [
        { urlPattern: 'https://static-map-tiles-api.arcgis.com/*' },
        { urlPattern: 'https://ibasemaps-api.arcgis.com/*' },
        { urlPattern: `${baseUrl}/ajax/map_route.php` }
    ] })
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
        const attribution = document.querySelector('#map .leaflet-control-attribution');
        return {
            ready: map?.dataset.mapReady === 'true',
            provider: map?.dataset.provider || '',
            mode: map?.dataset.mapMode || '',
            maxZoom: window.__leafletWorkspaceTest?.map?.getMaxZoom(),
            visibleCount: Number(map?.dataset.visibleCount || 0),
            markerCount: window.__leafletWorkspaceTest?.clusters?.getLayers()?.length || 0,
            clustersVisible: document.querySelectorAll('#map .mosque-cluster').length,
            markersVisible: document.querySelectorAll('#map .mosque-marker').length,
            selectionVisible: Boolean(panel && !panel.hidden),
            routeReady: map?.dataset.routeReady === 'true',
            attributionText: attribution?.textContent?.trim() || '',
            attributionLink: attribution?.querySelector('a[href*="esri.com"]')?.href || '',
            leafletPrefix: Boolean(attribution?.querySelector('a[href*="leafletjs.com"]')),
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
            controls: document.querySelectorAll('.map-control-stack button').length,
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
    result.states.baseline = await mapState();
    result.screenshots.push(await screenshot('leaflet-unselected-1440x1000'));

    const imageryBefore = requests.filter(request => request.host === 'ibasemaps-api.arcgis.com').length;
    check('Leaflet workspace initializes', result.states.baseline.ready && result.states.baseline.provider === 'leaflet', result.states.baseline);
    check('Full dataset is clustered', result.states.baseline.markerCount === result.states.baseline.visibleCount && result.states.baseline.markerCount > 0, result.states.baseline);
    check('Provider maximum zoom is 22', result.states.baseline.maxZoom === 22, result.states.baseline.maxZoom);
    check('Application controls are present', result.states.baseline.controls === 4, result.states.baseline.controls);
    check('Esri attribution is clickable with no framework prefix', result.states.baseline.attributionLink.includes('esri.com') && !result.states.baseline.leafletPrefix, result.states.baseline);
    check('Satellite is lazy before switching', imageryBefore === 0, imageryBefore);

    await evaluate(`window.__leafletWorkspaceTest.selectFirst()`);
    await waitFor(`!document.querySelector('#selectedMosquePanel').hidden`);
    result.states.selected = await mapState();
    check('Selection uses the persistent details panel', result.states.selected.selectionVisible);

    await evaluate(`document.querySelector('#mapStyleSatellite').click()`);
    await waitFor(`document.querySelector('#map')?.dataset.mapMode === 'satellite'`);
    await waitFor(`window.__leafletWorkspaceTest.mode === 'satellite'`);
    result.states.satellite = await mapState();
    check('Satellite is created on demand', requests.filter(request => request.host === 'ibasemaps-api.arcgis.com').length > imageryBefore);
    check('Selection survives basemap changes', result.states.satellite.selectionVisible);

    await evaluate(`document.querySelector('#mapStyleHybrid').click()`);
    await waitFor(`document.querySelector('#map')?.dataset.mapMode === 'hybrid'`);
    check('Hybrid requests the ArcGIS labels overlay', requests.some(request => request.host === 'static-map-tiles-api.arcgis.com' && request.path.includes('/imagery/labels/')));

    await cdp.send('Browser.setPermission', { permission: { name: 'geolocation' }, setting: 'granted', origin: baseUrl });
    await cdp.send('Emulation.setGeolocationOverride', { latitude: 34.6814, longitude: -1.9086, accuracy: 12 });
    await evaluate(`document.querySelector('#routeToMosque').click()`);
    await waitFor(`document.querySelector('#map')?.dataset.routeReady === 'true'`);
    result.states.routed = await mapState();
    check('Mocked geolocation route renders', result.states.routed.routeReady && Boolean(await evaluate(`document.querySelector('#routeSteps li')?.textContent.includes('شمال')`)));
    check('Selection remains stable while routing', result.states.routed.selectionVisible);
    check('Routing stays on the local endpoint', requests.some(request => request.path.endsWith('/ajax/map_route.php')) && !requests.some(request => request.host === 'route-api.arcgis.com'));

    await evaluate(`location.reload()`);
    await waitFor(`document.querySelector('#map')?.dataset.mapReady === 'true'`);
    await waitFor(`document.querySelector('#map')?.dataset.mapMode === 'hybrid'`);
    result.states.persisted = await mapState();
    check('Basemap choice persists after reload', result.states.persisted.mode === 'hybrid');

    await evaluate(`window.__leafletWorkspaceTest.selectFirst()`);
    const viewports = [[1920, 1080], [1440, 1000], [1024, 900], [768, 1024], [430, 932], [390, 844], [360, 800]];
    for (const [width, height] of viewports) {
        await viewport(width, height);
        await evaluate(`(() => { window.dispatchEvent(new Event('resize')); window.__leafletWorkspaceTest.map.invalidateSize(); return true; })()`);
        await delay(180);
        const state = await mapState();
        check(`${width}x${height} has no page overflow`, !state.overflow, state);
        result.screenshots.push(await screenshot(`leaflet-selected-${width}x${height}`));
    }

    await viewport(1024, 900);
    await navigate('add_mosque.php');
    await evaluate(`document.querySelector('#showMapBtn').click()`);
    await waitFor(`document.querySelector('#mapContainer #map')?.dataset.mapReady === 'true'`);
    result.states.formPicker = await evaluate(`(() => ({
        provider: document.querySelector('#mapContainer')?.dataset.provider,
        leaflet: Boolean(document.querySelector('#mapContainer .leaflet-container')),
        attribution: document.querySelector('#mapContainer .leaflet-control-attribution')?.textContent?.trim() || ''
    }))()`);
    check('Create form coordinate picker uses Leaflet', result.states.formPicker.provider === 'leaflet' && result.states.formPicker.leaflet, result.states.formPicker);

    const forbidden = /maplibre|openfreemap|mapbox-gl-rtl|tiles\.maps\.eox/i;
    check('No superseded map runtime traffic', !requests.some(request => forbidden.test(`${request.host}${request.path}`)));
    check('Street requests use the current ArcGIS static tile service', requests.some(request => request.host === 'static-map-tiles-api.arcgis.com' && request.path.includes('/arcgis/navigation/static/tile/')));
    check('No browser console errors', consoleEvents.length === 0, consoleEvents);
    check('No failed requests or CSP blocks', failedRequests.length === 0, failedRequests);
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
