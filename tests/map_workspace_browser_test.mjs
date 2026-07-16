import { spawn } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

const phase = process.argv[2] === 'before' ? 'before' : 'after';
const baseUrl = (process.env.MAP_TEST_BASE_URL || 'http://127.0.0.1:8085').replace(/\/$/, '');
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const port = Number(process.env.MAP_TEST_DEBUG_PORT || 9444);
const root = process.cwd();
const evidenceDir = path.join(root, 'docs', 'screenshots', 'map-workspace-v3', phase);
const profileDir = path.join(root, 'storage', `.map-workspace-chrome-${Date.now()}`);
const resultPath = path.join(root, 'storage', `map-workspace-${phase}-result.json`);
const mapCss = await readFile(path.join(root, 'resources', 'css', 'pages', 'maps.css'), 'utf8');
const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

await mkdir(evidenceDir, { recursive: true });
await mkdir(profileDir, { recursive: true });

const chrome = spawn(chromePath, [
    '--headless=new',
    '--disable-gpu',
    '--disable-dev-shm-usage',
    '--disable-background-networking',
    '--disable-component-update',
    '--disable-default-apps',
    '--disable-extensions',
    '--disable-features=Translate,MediaRouter,OptimizationHints',
    '--hide-scrollbars',
    '--no-first-run',
    '--no-default-browser-check',
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profileDir}`,
    'about:blank',
], { stdio: 'ignore' });

async function waitForChrome() {
    const deadline = Date.now() + 15000;
    while (Date.now() < deadline) {
        try {
            const response = await fetch(`http://127.0.0.1:${port}/json/list`);
            if (response.ok) {
                const targets = await response.json();
                const page = targets.find((target) => target.type === 'page');
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

    socket.addEventListener('message', (event) => {
        const message = JSON.parse(event.data);
        if (message.id) {
            const request = pending.get(message.id);
            if (!request) return;
            pending.delete(message.id);
            if (message.error) request.reject(new Error(message.error.message));
            else request.resolve(message.result);
            return;
        }
        (listeners.get(message.method) || []).forEach((listener) => listener(message.params || {}));
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
        },
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

cdp.on('Runtime.consoleAPICalled', ({ type, args = [] }) => {
    if (!['error', 'warning', 'assert'].includes(type)) return;
    consoleEvents.push({ type, text: args.map((arg) => arg.value ?? arg.description ?? '').join(' ') });
});
cdp.on('Runtime.exceptionThrown', ({ exceptionDetails }) => {
    consoleEvents.push({ type: 'exception', text: exceptionDetails?.exception?.description || exceptionDetails?.text || 'Runtime exception' });
});
cdp.on('Log.entryAdded', ({ entry }) => {
    if (!['error', 'warning'].includes(entry.level)) return;
    consoleEvents.push({ type: entry.level, text: entry.text, source: entry.source });
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
    cdp.send('Log.enable'),
]);

async function evaluate(expression) {
    const result = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true, userGesture: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    return result.result?.value;
}

async function waitFor(expression, timeout = 15000) {
    const deadline = Date.now() + timeout;
    while (Date.now() < deadline) {
        try {
            if (await evaluate(expression)) return;
        } catch {
            // The JavaScript context can be replaced during navigation.
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
        screenHeight: height,
    });
}

async function navigate(relativeUrl) {
    await cdp.send('Page.navigate', { url: `${baseUrl}/${relativeUrl.replace(/^\//, '')}` });
    await waitFor(`document.readyState === 'complete'`);
    await delay(350);
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
        const panelRect = panel && !panel.hidden ? panel.getBoundingClientRect() : null;
        const shellRect = shell?.getBoundingClientRect() || null;
        const mapRect = mapElement?.getBoundingClientRect() || null;
        const rect = (value) => value ? ({ top: value.top, right: value.right, bottom: value.bottom, left: value.left, width: value.width, height: value.height }) : null;
        const overlaps = (a, b) => Boolean(a && b && a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top);
        const controls = [...document.querySelectorAll('#map button')].map((button) => ({
            label: button.getAttribute('aria-label') || button.title || button.textContent.trim(),
            rect: button.getBoundingClientRect()
        })).filter((value) => value.rect.width > 0 && value.rect.height > 0);
        const attribution = [...document.querySelectorAll('#map a')].map((link) => link.getBoundingClientRect()).filter((value) => value.width > 0 && value.height > 0);
        return {
            mapLoaded: Boolean(window.google?.maps && document.querySelector('#map .gm-style')),
            documentScrollY: window.scrollY,
            documentOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            panelExists: Boolean(panel),
            panelVisible: Boolean(panel && !panel.hidden),
            panelTitle: document.querySelector('#selectedMosqueTitle')?.textContent?.trim() || '',
            address: document.querySelector('#selectedMosqueAddress')?.textContent?.trim() || '',
            imam: document.querySelector('#selectedMosqueImam')?.textContent?.trim() || '',
            community: document.querySelector('#selectedMosqueCommunity')?.textContent?.trim() || '',
            panelRect: rect(panelRect),
            shellRect: rect(shellRect),
            mapRect: rect(mapRect),
            panelInsideShell: Boolean(panelRect && shellRect && panelRect.top >= shellRect.top - 1 && panelRect.bottom <= shellRect.bottom + 1 && panelRect.left >= shellRect.left - 1 && panelRect.right <= shellRect.right + 1),
            panelScrollable: Boolean(panel && panel.scrollHeight > panel.clientHeight + 1),
            overlapsGoogleControl: controls.some((control) => overlaps(panelRect, control.rect)),
            overlappingControls: controls.filter((control) => overlaps(panelRect, control.rect)).map((control) => ({ label: control.label, rect: rect(control.rect) })),
            overlapsAttribution: attribution.some((item) => overlaps(panelRect, item)),
            legacyInfoWindowVisible: Boolean(document.querySelector('.gm-style-iw-c')),
            legacyInfoWindowText: document.querySelector('.gm-style-iw-c')?.textContent?.trim() || '',
            listScrollTop: document.querySelector('#mosquesList')?.scrollTop || 0,
            activeMosqueId: document.activeElement?.closest?.('.mosque-list-item')?.dataset.mosqueId || '',
        };
    })()`);
}

async function selectListItem(index = 0) {
    const before = await evaluate('window.scrollY');
    const selected = await evaluate(`(() => {
        const buttons = [...document.querySelectorAll('.zoom-to-mosque')];
        const button = buttons[${index}] || buttons[0];
        if (!button) return null;
        button.focus();
        button.click();
        return { id: button.closest('.mosque-list-item')?.dataset.mosqueId || '', label: button.textContent.trim() };
    })()`);
    await delay(650);
    return { selected, before, after: await evaluate('window.scrollY'), state: await mapState() };
}

async function zoomMap() {
    const rect = await evaluate(`(() => { const value = document.querySelector('#map').getBoundingClientRect(); return { x: value.left + value.width / 2, y: value.top + value.height / 2 }; })()`);
    await cdp.send('Input.dispatchMouseEvent', { type: 'mouseWheel', x: rect.x, y: rect.y, deltaX: 0, deltaY: -420 });
    await delay(900);
    return mapState();
}

const viewports = [
    [1920, 1080],
    [1440, 1000],
    [1280, 800],
    [1024, 900],
    [768, 1024],
    [430, 932],
    [390, 844],
    [360, 800],
];
const result = { phase, generatedAt: new Date().toISOString(), baseline: null, detailed: null, viewports: [], console: consoleEvents, network: networkEvents, assertions: [], failures: [] };

function check(name, ok, detail = '') {
    result.assertions.push({ name, ok: Boolean(ok), detail });
    if (!ok) result.failures.push({ name, detail });
}

try {
    await viewport(1440, 1000);
    await login();
    await navigate('mosque_maps.php');
    await waitFor(`Boolean(window.google?.maps && document.querySelector('#map .gm-style'))`, 20000);
    await delay(800);
    result.baseline = await mapState();
    result.baseline.screenshot = await screenshot('map-unselected-1440x1000');

    const firstSelection = await selectListItem(0);
    firstSelection.state.screenshot = await screenshot('map-selected-1440x1000');
    await evaluate(`(() => { const list = document.querySelector('#mosquesList'); list.scrollTop = list.scrollHeight; list.dispatchEvent(new Event('scroll')); return list.scrollTop; })()`);
    await delay(350);
    const afterListScroll = await mapState();
    const afterZoom = await zoomMap();
    await evaluate(`window.scrollTo({ top: Math.min(240, document.documentElement.scrollHeight - innerHeight), behavior: 'instant' })`);
    await delay(250);
    const afterPageScroll = await mapState();
    await evaluate(`window.scrollTo({ top: 0, behavior: 'instant' })`);
    const secondSelection = await selectListItem(1);

    let closeState = null;
    let escapeState = null;
    let filteredState = null;
    if (phase === 'after') {
        await evaluate(`document.querySelector('#selectedMosqueClose')?.click()`);
        await delay(250);
        closeState = await mapState();
        await selectListItem(0);
        await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape' });
        await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape' });
        await delay(250);
        escapeState = await mapState();
        await selectListItem(0);
        await evaluate(`(() => {
            const selected = document.querySelector('.mosque-list-item[aria-current="true"]');
            const currentCommunity = selected?.dataset.community || '';
            const filter = document.querySelector('#communityFilter');
            const option = [...filter.options].find((item) => item.value && item.value !== currentCommunity);
            if (!option) return false;
            filter.value = option.value;
            filter.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        })()`);
        await delay(500);
        filteredState = await mapState();
    }

    result.detailed = { firstSelection, afterListScroll, afterZoom, afterPageScroll, secondSelection, closeState, escapeState, filteredState };

    if (phase === 'after') {
        for (const [width, height] of viewports) {
            await viewport(width, height);
            await navigate('mosque_maps.php');
            await waitFor(`Boolean(window.google?.maps && document.querySelector('#map .gm-style'))`, 20000);
            await delay(550);
            const selection = await selectListItem(0);
            selection.state.screenshot = await screenshot(`map-selected-${width}x${height}`);
            result.viewports.push({ width, height, ...selection });
        }
    }

    if (phase === 'before') {
        check('Baseline uses an application-owned selection panel', result.baseline.panelExists, 'Expected failure before implementation.');
        check('Baseline avoids Google private InfoWindow DOM', !firstSelection.state.legacyInfoWindowVisible, 'Expected failure before implementation.');
        check('Baseline selection keeps document scroll fixed', firstSelection.before === firstSelection.after, `${firstSelection.before} -> ${firstSelection.after}`);
    } else {
        check('Map loads', result.baseline.mapLoaded);
        check('Selection panel exists', firstSelection.state.panelExists);
        check('Selection panel becomes visible', firstSelection.state.panelVisible);
        check('Selected mosque name is present', firstSelection.state.panelTitle.length > 0);
        check('Selected mosque address is present', firstSelection.state.address.length > 0);
        check('Selected mosque imam is present', firstSelection.state.imam.length > 0);
        check('Selected mosque community is present', firstSelection.state.community.length > 0);
        check('Selection keeps document scroll fixed', firstSelection.before === firstSelection.after, `${firstSelection.before} -> ${firstSelection.after}`);
        check('List scrolling keeps panel visible', afterListScroll.panelVisible);
        check('Map zoom keeps panel visible', afterZoom.panelVisible);
        check('Page scrolling keeps panel open', afterPageScroll.panelVisible);
        check('Second selection updates the same panel', secondSelection.state.panelVisible
            && secondSelection.selected?.id !== firstSelection.selected?.id
            && (secondSelection.state.address !== firstSelection.state.address || secondSelection.state.community !== firstSelection.state.community));
        check('Close button hides panel', closeState && !closeState.panelVisible);
        check('Close returns focus to selected list action', closeState?.activeMosqueId === secondSelection.selected?.id);
        check('Escape hides panel', escapeState && !escapeState.panelVisible);
        check('Escape returns focus to selected list action', escapeState?.activeMosqueId === firstSelection.selected?.id);
        check('Filtering out selection closes panel', filteredState && !filteredState.panelVisible);
        check('Native InfoWindow is absent', !firstSelection.state.legacyInfoWindowVisible);
        check('Application CSS avoids Google InfoWindow selectors', !/\.gm-style-iw-(?:c|d)\b/.test(mapCss));
        result.viewports.forEach((entry) => {
            const label = `${entry.width}x${entry.height}`;
            check(`${label}: panel visible`, entry.state.panelVisible);
            check(`${label}: full panel inside map shell`, entry.state.panelInsideShell);
            check(`${label}: no document overflow`, !entry.state.documentOverflow);
            check(`${label}: no Google control overlap`, !entry.state.overlapsGoogleControl);
            check(`${label}: no attribution overlap`, !entry.state.overlapsAttribution);
        });
        const errors = consoleEvents.filter((entry) => ['error', 'exception', 'assert'].includes(entry.type));
        check('No JavaScript or CSP errors', errors.length === 0, `${errors.length} error events`);
        check('No failed network requests', networkEvents.length === 0, `${networkEvents.length} failed requests`);
    }
} finally {
    result.console = consoleEvents;
    result.network = networkEvents;
    await writeFile(resultPath, JSON.stringify(result, null, 2));
    cdp.close();
    chrome.kill();
    await rm(profileDir, { recursive: true, force: true }).catch(() => {});
}

console.log(JSON.stringify({
    phase,
    resultPath,
    screenshots: phase === 'after' ? viewports.length + 2 : 2,
    failures: result.failures,
}, null, 2));

if (phase === 'after' && result.failures.length > 0) process.exitCode = 1;
