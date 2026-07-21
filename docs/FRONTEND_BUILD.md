# Institutional frontend build and deployment

The institutional interface is compiled locally and committed to `public/assets/dist/` so it can run on standard shared hosting without Node.js.

## Requirements

- Node.js 20 or newer for development builds
- PHP and the existing application runtime for serving the site

## Build commands

```bash
npm ci
npm run build
```

The build produces the following minified, cacheable assets:

- `public/assets/dist/app.min.css`
- `public/assets/dist/app.min.js`
- `public/assets/dist/login.min.js`
- `public/assets/dist/dashboard.min.js`
- `public/assets/dist/quran.min.js`
- `public/assets/dist/maps.min.js`
- `public/assets/dist/maps.min.css`
- `public/assets/dist/mosque-form-map.min.js`
- `public/assets/dist/mosque-form-map.min.css`
- `public/assets/dist/import-export.min.js`
- `public/assets/dist/backup-confirm.min.js`

Tailwind is compiled with the `tw` prefix and without Preflight. Bootstrap remains the compatibility layer for the existing server-rendered markup. The institutional stylesheet is loaded last so its tokens and components can refine Bootstrap without changing backend contracts.

## Shared-hosting deployment

1. Run `npm ci && npm run build` in CI or on a development machine.
2. Deploy the PHP application together with the generated `public/assets/dist/` directory.
3. Keep the writable upload/storage directories and `MAPTILER_API_KEY` configured as documented by the application.
4. Do not run Node.js on the production host; the generated files are the production assets.
5. After deployment, verify login, an authenticated dashboard request, form submission, export download, OpenStreetMap street tiles, MapTiler satellite tiles, clusters, and mosque selection.

## Dependency policy

- Bootstrap, Font Awesome, SweetAlert2, Chart.js, jQuery, and Select2 are retained where the current application uses them.
- Animate.css and Hover.css are no longer loaded; motion is implemented in the application stylesheet and honors `prefers-reduced-motion`.
- Leaflet and Leaflet.markercluster are bundled locally through npm/esbuild. Basemaps are plain raster tile layers; no JavaScript or CSS runtime is loaded from a CDN.
- Three.js is intentionally omitted because the visual concept is achieved with lightweight CSS transforms and gradients.

## Source layout

- `resources/css/foundation/`: tokens, typography, base rules, accessibility, RTL, and print
- `resources/css/layout/`: application shell
- `resources/css/components/`: reusable interface patterns and effects
- `resources/css/pages/`: page-specific styling
- `resources/js/components/`: reusable navigation, feedback, motion, and confirmation behavior
- `resources/js/pages/`: page-specific behavior and JSON-data adapters
