const RTL_PLUGIN_ASSET = 'assets/dist/mapbox-gl-rtl-text.js';

let rtlPluginPromise;

export function ensureMaplibreRtlText(maplibregl) {
    const status = maplibregl.getRTLTextPluginStatus();
    if (status === 'loaded') return Promise.resolve();
    if (status === 'loading' && rtlPluginPromise) return rtlPluginPromise;
    if (status !== 'unavailable') {
        return Promise.reject(new Error(`Unexpected MapLibre RTL plugin status: ${status}`));
    }

    const pluginUrl = new URL(RTL_PLUGIN_ASSET, document.baseURI).href;
    rtlPluginPromise = maplibregl.setRTLTextPlugin(pluginUrl, false);
    return rtlPluginPromise;
}
