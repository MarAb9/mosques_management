import { spawn } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

const mode = process.argv[2] === 'before' ? 'before' : 'after';
const scope = process.env.UI_AUDIT_SCOPE === 'login-directory' ? 'login-directory' : 'full';
const baseUrl = (process.env.UI_AUDIT_BASE_URL || 'http://127.0.0.1:8085').replace(/\/$/, '');
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const port = Number(process.env.UI_AUDIT_DEBUG_PORT || 9333);
const root = process.cwd();
const evidenceDir = path.join(root, 'docs', 'screenshots', 'ui-reset-v2', mode);
const profileDir = path.join(root, 'storage', `.ui-reset-chrome-${mode}-${Date.now()}`);
const auditPath = path.join(root, 'storage', `ui-reset-v2-${mode}-audit.json`);
const browserHeight = 1000;

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

const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

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
            else request.resolve(message.result || {});
            return;
        }
        const handlers = listeners.get(message.method) || [];
        handlers.forEach((handler) => handler(message.params || {}));
    });

    async function send(method, params = {}) {
        await opened;
        const id = ++sequence;
        return new Promise((resolve, reject) => {
            pending.set(id, { resolve, reject });
            socket.send(JSON.stringify({ id, method, params }));
        });
    }

    function on(method, handler) {
        const handlers = listeners.get(method) || [];
        handlers.push(handler);
        listeners.set(method, handlers);
        return () => listeners.set(method, (listeners.get(method) || []).filter((item) => item !== handler));
    }

    function once(method, timeout = 15000) {
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                unsubscribe();
                reject(new Error(`Timed out waiting for ${method}`));
            }, timeout);
            const unsubscribe = on(method, (params) => {
                clearTimeout(timer);
                unsubscribe();
                resolve(params);
            });
        });
    }

    return { send, on, once, close: () => socket.close() };
}

const target = await waitForChrome();
const cdp = connect(target.webSocketDebuggerUrl);
await Promise.all([
    cdp.send('Page.enable'),
    cdp.send('Runtime.enable'),
    cdp.send('Network.enable'),
    cdp.send('Log.enable'),
    cdp.send('Accessibility.enable'),
]);

let activeEvents = null;
cdp.on('Runtime.consoleAPICalled', ({ type, args = [] }) => {
    if (!activeEvents || !['error', 'warning', 'assert'].includes(type)) return;
    activeEvents.console.push({ type, text: args.map((arg) => arg.value ?? arg.description ?? '').join(' ') });
});
cdp.on('Runtime.exceptionThrown', ({ exceptionDetails }) => {
    if (!activeEvents) return;
    activeEvents.console.push({ type: 'exception', text: exceptionDetails?.exception?.description || exceptionDetails?.text || 'Runtime exception' });
});
cdp.on('Log.entryAdded', ({ entry }) => {
    if (!activeEvents || !['error', 'warning'].includes(entry.level)) return;
    activeEvents.console.push({ type: entry.level, text: entry.text, source: entry.source });
});
cdp.on('Network.loadingFailed', ({ errorText, blockedReason, canceled, type }) => {
    if (!activeEvents || canceled) return;
    activeEvents.network.push({ kind: 'failed', type, errorText, blockedReason: blockedReason || '' });
});
cdp.on('Network.responseReceived', ({ response, type }) => {
    if (!activeEvents || response.status < 400) return;
    activeEvents.network.push({ kind: 'http', type, status: response.status, url: response.url });
});
cdp.on('Page.javascriptDialogOpening', () => {
    cdp.send('Page.handleJavaScriptDialog', { accept: true }).catch(() => {});
});

async function viewport(width, height = browserHeight) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width,
        height,
        deviceScaleFactor: 1,
        mobile: width < 768,
        screenWidth: width,
        screenHeight: height,
    });
}

async function evaluate(expression, awaitPromise = true) {
    const result = await cdp.send('Runtime.evaluate', {
        expression,
        awaitPromise,
        returnByValue: true,
        userGesture: true,
    });
    if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    }
    return result.result?.value;
}

async function navigate(relativeUrl) {
    await cdp.send('Page.navigate', { url: relativeUrl.startsWith('http') ? relativeUrl : `${baseUrl}/${relativeUrl.replace(/^\//, '')}` });
    const deadline = Date.now() + 15000;
    while (Date.now() < deadline) {
        await delay(150);
        try {
            if (await evaluate(`document.readyState === 'complete'`)) {
                await delay(350);
                return;
            }
        } catch {
            // The JavaScript context is replaced during navigation.
        }
    }
    throw new Error(`Timed out loading ${relativeUrl}`);
}

async function submitLogin() {
    await evaluate(`(() => {
        const username = document.querySelector('#username');
        const password = document.querySelector('#password');
        const form = document.querySelector('#loginForm');
        username.value = 'admin';
        password.value = 'admin123';
        form.requestSubmit(form.querySelector('[type="submit"]'));
        return true;
    })()`);
    const deadline = Date.now() + 15000;
    while (Date.now() < deadline) {
        await delay(150);
        try {
            const state = await evaluate(`({ ready: document.readyState, path: location.pathname })`);
            if (state.ready === 'complete' && !state.path.endsWith('/login.php')) {
                await delay(350);
                return;
            }
        } catch {
            // The JavaScript context is replaced during form submission.
        }
    }
    throw new Error('Timed out submitting the login form.');
}

async function screenshot(name, fullPage = false) {
    let clip;
    if (fullPage) {
        const metrics = await cdp.send('Page.getLayoutMetrics');
        const size = metrics.cssContentSize || metrics.contentSize;
        clip = { x: 0, y: 0, width: size.width, height: Math.min(size.height, 8000), scale: 1 };
    }
    const image = await cdp.send('Page.captureScreenshot', {
        format: 'png',
        fromSurface: true,
        captureBeyondViewport: fullPage,
        ...(clip ? { clip } : {}),
    });
    const file = path.join(evidenceDir, `${name}.png`);
    await writeFile(file, Buffer.from(image.data, 'base64'));
    return path.relative(root, file).replaceAll('\\', '/');
}

async function inspect(label, relativeUrl, width, { capture = true, fullPage = false } = {}) {
    activeEvents = { console: [], network: [] };
    await viewport(width);
    await navigate(relativeUrl);
    const layout = await evaluate(`(() => {
        const box = (selector) => {
            const element = document.querySelector(selector);
            if (!element) return null;
            const rect = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            return { x: Math.round(rect.x), y: Math.round(rect.y), width: Math.round(rect.width), height: Math.round(rect.height), display: style.display, position: style.position, overflowX: style.overflowX };
        };
        const unlabeledButtons = [...document.querySelectorAll('button, [role="button"]')].filter((element) => !((element.getAttribute('aria-label') || element.textContent || '').trim())).length;
        const headings = [...document.querySelectorAll('h1')].map((element) => element.textContent.trim()).filter(Boolean);
        return {
            url: location.href,
            title: document.title,
            viewport: { width: innerWidth, height: innerHeight },
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
            hasHorizontalOverflow: document.documentElement.scrollWidth !== document.documentElement.clientWidth,
            h1: headings,
            unlabeledButtons,
            visibleDialogs: [...document.querySelectorAll('dialog[open], .modal.show')].length,
            sidebar: box('.app-sidebar'),
            topbar: box('.app-topbar'),
            content: box('.app-content'),
            mainTable: box('.directory-table, .quran-table-wrapper table'),
            mobileCard: box('.mosque-mobile-card, .quran-mobile-cards .card'),
        };
    })()`);
    const ax = await cdp.send('Accessibility.getFullAXTree');
    await delay(200);
    const evidence = capture ? await screenshot(`${label}-${width}`, fullPage) : null;
    const events = activeEvents;
    activeEvents = null;
    return {
        label,
        relativeUrl,
        width,
        layout,
        accessibility: {
            nodeCount: ax.nodes?.length || 0,
            namedButtonCount: (ax.nodes || []).filter((node) => node.role?.value === 'button' && node.name?.value).length,
            unnamedButtonCount: (ax.nodes || []).filter((node) => node.role?.value === 'button' && !node.name?.value).length,
        },
        console: events.console,
        network: events.network,
        screenshot: evidence,
    };
}

async function interaction(label, width, setup) {
    activeEvents = { console: [], network: [] };
    await viewport(width);
    await setup();
    await delay(300);
    const state = await evaluate(`({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        activeElement: document.activeElement?.id || document.activeElement?.getAttribute('aria-label') || document.activeElement?.tagName,
        columnsMenu: (() => { const element = document.querySelector('.directory-column-menu.show'); if (!element) return null; const rect = element.getBoundingClientRect(); return { top: Math.round(rect.top), right: Math.round(rect.right), bottom: Math.round(rect.bottom), left: Math.round(rect.left), viewportWidth: innerWidth, viewportHeight: innerHeight }; })(),
    })`);
    const evidence = await screenshot(`${label}-${width}`);
    const events = activeEvents;
    activeEvents = null;
    return { label, width, state, console: events.console, network: events.network, screenshot: evidence };
}

const results = { mode, scope, baseUrl, generatedAt: new Date().toISOString(), pages: [], interactions: [] };

try {
    await viewport(1440);
    await navigate('login.php');
    results.pages.push(await inspect('login', 'login.php', 1440, { fullPage: true }));

    if (mode === 'before') {
        await submitLogin();
        results.pages.push(await inspect('dashboard', 'index.php', 1440, { fullPage: true }));
        results.pages.push(await inspect('mosques', 'mosques.php', 1440, { fullPage: true }));
        results.pages.push(await inspect('mosques-mobile', 'mosques.php', 390));
    } else {
        if (scope === 'login-directory') {
            results.pages.push(await inspect('login-mobile', 'login.php', 390));
            const recovery = await interaction('login-recovery-open', 1440, async () => {
                await evaluate(`(() => { const details = document.querySelector('.login-recovery'); details.open = true; return true; })()`);
            });
            recovery.recoveryState = await evaluate(`({
                open: document.querySelector('.login-recovery')?.open === true,
                heading: document.querySelector('.login-recovery__content strong')?.textContent?.trim() || '',
            })`);
            results.interactions.push(recovery);
        }

        await submitLogin();
        if (scope === 'login-directory') {
            results.pages.push(await inspect('mosques', 'mosques.php', 1440, { fullPage: true }));
            results.pages.push(await inspect('mosques-mobile', 'mosques.php', 390));

            await viewport(1440);
            await navigate('mosques.php');
            const columns = await interaction('mosques-columns-open', 1440, async () => {
                await evaluate(`bootstrap.Dropdown.getOrCreateInstance(document.querySelector('#columnsDropdown')).show()`);
            });
            columns.columnOptions = await evaluate(`[...document.querySelectorAll('.directory-column-option')].map((option) => {
                const input = option.querySelector('input');
                const label = option.querySelector('span');
                const inputRect = input.getBoundingClientRect();
                const labelRect = label.getBoundingClientRect();
                return {
                    text: label.textContent.trim(),
                    inputRight: Math.round(inputRect.right),
                    labelRight: Math.round(labelRect.right),
                    overlap: !(inputRect.right <= labelRect.left || inputRect.left >= labelRect.right),
                };
            })`);
            results.interactions.push(columns);

            await navigate('mosques.php');
            const liveFilter = await interaction('mosques-live-filter', 1440, async () => {
                await evaluate(`(() => {
                    const filter = document.querySelector('#communityFilter');
                    const option = [...filter.options].find((item) => item.value);
                    filter.value = option.value;
                    filter.dispatchEvent(new Event('change', { bubbles: true }));
                    return option.value;
                })()`);
                const deadline = Date.now() + 10000;
                while (Date.now() < deadline) {
                    const ready = await evaluate(`location.search.includes('community=') && document.querySelector('.directory-table tbody')?.getAttribute('aria-busy') !== 'true'`);
                    if (ready) break;
                    await delay(100);
                }
            });
            liveFilter.filterState = await evaluate(`({
                selected: document.querySelector('#communityFilter')?.value || '',
                resultSummary: document.querySelector('.directory-result-summary > strong')?.textContent?.trim() || '',
                url: location.pathname + location.search,
                densityControlExists: document.querySelector('#densityToggle') !== null,
            })`);
            results.interactions.push(liveFilter);
        } else {
        const directoryEditHref = await (async () => {
            await navigate('mosques.php');
            return evaluate(`document.querySelector('a[href^="edit_mosque.php?id="]')?.getAttribute('href') || 'edit_mosque.php?id=1'`);
        })();
        const pages = [
            ['dashboard', 'index.php'],
            ['mosques', 'mosques.php'],
            ['add-mosque', 'add_mosque.php'],
            ['edit-mosque', directoryEditHref],
            ['quran', 'quran_mosques.php'],
            ['map', 'mosque_maps.php'],
            ['import-export', 'import_export.php'],
            ['data-quality', 'data_quality.php'],
        ];

        for (const [label, url] of pages) {
            results.pages.push(await inspect(label, url, 1440, { fullPage: true }));
            results.pages.push(await inspect(`${label}-mobile`, url, 390));
        }

        for (const width of [1920, 1440, 1280, 1024, 768, 430, 390, 360]) {
            results.pages.push(await inspect('responsive-dashboard', 'index.php', width));
            results.pages.push(await inspect('responsive-mosques', 'mosques.php', width));
        }

        await viewport(1440);
        await navigate('mosques.php');
        results.interactions.push(await interaction('mosques-columns-open', 1440, async () => {
            await evaluate(`bootstrap.Dropdown.getOrCreateInstance(document.querySelector('#columnsDropdown')).show()`);
        }));
        await navigate('mosques.php');
        results.interactions.push(await interaction('mosques-selected-rows', 1440, async () => {
            await evaluate(`(() => { [...document.querySelectorAll('.mosque-checkbox')].slice(0, 3).forEach((box) => { box.checked = true; box.dispatchEvent(new Event('change', { bubbles: true })); }); return true; })()`);
        }));

        await viewport(390);
        await navigate('mosques.php');
        results.interactions.push(await interaction('mosques-mobile-filters', 390, async () => {
            await evaluate(`bootstrap.Collapse.getOrCreateInstance(document.querySelector('#directoryFilters'), { toggle: false }).show()`);
        }));
        await navigate('mosques.php');
        results.interactions.push(await interaction('mosques-mobile-card', 390, async () => {}));

        await navigate('index.php');
        results.interactions.push(await interaction('mobile-sidebar', 390, async () => {
            await evaluate(`document.querySelector('#mobileNavToggle').click()`);
        }));

        await navigate('index.php');
        results.interactions.push(await interaction('global-search-focus', 1440, async () => {
            await viewport(1440);
            await evaluate(`(() => { const trigger = document.querySelector('#globalSearchToggle'); trigger.focus(); trigger.click(); return true; })()`);
            await delay(250);
        }));
        results.interactions.push(await interaction('global-search-escape-restored', 1440, async () => {
            await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape' });
            await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape' });
            await delay(250);
        }));
        }
    }
} finally {
    await writeFile(auditPath, JSON.stringify(results, null, 2));
    cdp.close();
    chrome.kill();
    await rm(profileDir, { recursive: true, force: true }).catch(() => {});
}

const failures = results.pages.filter((page) => page.layout?.hasHorizontalOverflow || page.console.length || page.network.length || page.layout?.unlabeledButtons).map((page) => ({
    label: page.label,
    width: page.width,
    overflow: page.layout?.hasHorizontalOverflow,
    unlabeledButtons: page.layout?.unlabeledButtons,
    console: page.console.length,
    network: page.network.length,
}));

console.log(JSON.stringify({ mode, auditPath, screenshots: results.pages.filter((page) => page.screenshot).length + results.interactions.length, failures }, null, 2));
