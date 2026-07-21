import { spawn } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = (process.env.IDENTITY_TEST_BASE_URL || 'http://127.0.0.1:8085').replace(/\/$/, '');
const phase = process.env.IDENTITY_PHASE === 'after' ? 'after' : 'before';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const port = 9800 + Math.floor(Math.random() * 100);
const root = process.cwd();
const evidenceDir = path.join(root, 'docs', 'screenshots', 'institutional-v3', phase);
const profileDir = path.join(root, 'storage', `.identity-chrome-${Date.now()}`);
const resultPath = path.join(root, 'storage', `institutional-${phase}-result.json`);
const delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
const viewports = [[1920, 1080], [1440, 1000], [1280, 800], [1024, 900], [768, 1024], [430, 932], [390, 844], [360, 800]];
const pages = [
    ['dashboard', 'index.php'],
    ['directory', 'mosques.php'],
    ['form', 'add_mosque.php'],
    ['map', 'mosque_maps.php'],
    ['quran', 'quran_mosques.php']
];

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
const consoleErrors = [];
const httpErrors = [];
cdp.on('Runtime.consoleAPICalled', ({ type, args = [] }) => {
    if (['error', 'assert'].includes(type)) consoleErrors.push(args.map(arg => arg.value ?? arg.description ?? '').join(' '));
});
cdp.on('Runtime.exceptionThrown', ({ exceptionDetails }) => consoleErrors.push(exceptionDetails?.exception?.description || exceptionDetails?.text));
cdp.on('Network.responseReceived', ({ response, type }) => {
    if (response.status >= 400 && !['Image', 'Font'].includes(type)) httpErrors.push({ status: response.status, url: response.url.split('?')[0] });
});
await Promise.all([cdp.send('Page.enable'), cdp.send('Runtime.enable'), cdp.send('Network.enable')]);

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
    await cdp.send('Page.navigate', { url: `${baseUrl}/${pathname}` });
    await waitFor(`document.readyState === 'complete'`);
    await delay(300);
}

async function screenshot(name) {
    const capture = await cdp.send('Page.captureScreenshot', { format: 'jpeg', quality: 76, captureBeyondViewport: false, fromSurface: true });
    const file = path.join(evidenceDir, `${name}.jpg`);
    await writeFile(file, Buffer.from(capture.data, 'base64'));
    return path.relative(root, file).replaceAll('\\', '/');
}

const result = { phase, screenshots: [], overflow: [], consoleErrors, httpErrors, fatalError: '' };
try {
    for (const [width, height] of viewports) {
        await viewport(width, height);
        await navigate('login.php');
        result.screenshots.push(await screenshot(`login-${width}x${height}`));
    }

    await viewport(1440, 1000);
    await navigate('login.php');
    await evaluate(`(() => {
        document.querySelector('#username').value = 'admin';
        document.querySelector('#password').value = 'admin123';
        const form = document.querySelector('#loginForm');
        form.requestSubmit(form.querySelector('[type=submit]'));
        return true;
    })()`);
    await waitFor(`document.readyState === 'complete' && !location.pathname.endsWith('/login.php')`);

    for (const [name, pathname] of pages) {
        for (const [width, height] of viewports) {
            await viewport(width, height);
            await navigate(pathname);
            if (name === 'map') await waitFor(`document.querySelector('#map')?.dataset.mapReady === 'true'`, 30000);
            const hasOverflow = await evaluate(`document.documentElement.scrollWidth > document.documentElement.clientWidth`);
            if (hasOverflow) result.overflow.push(`${name}-${width}x${height}`);
            result.screenshots.push(await screenshot(`${name}-${width}x${height}`));
        }
    }
} catch (error) {
    result.fatalError = error.stack || error.message;
} finally {
    await writeFile(resultPath, JSON.stringify(result, null, 2));
    cdp.close();
    chrome.kill();
    await delay(250);
    await rm(profileDir, { recursive: true, force: true });
}

console.log(`Institutional ${phase}: ${result.screenshots.length} screenshots, ${result.overflow.length} overflow issues, ${result.consoleErrors.length} console errors, ${result.httpErrors.length} HTTP errors.`);
if (result.fatalError || result.overflow.length || result.consoleErrors.length || result.httpErrors.length) console.error(result);
process.exit(result.fatalError || result.overflow.length || result.consoleErrors.length || result.httpErrors.length ? 1 : 0);
