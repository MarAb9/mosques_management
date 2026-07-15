# Atlas Noor UI redesign implementation report

Completed: 2026-07-15  
Branch: `redesign/atlas-noor-ui`  
Presentation baseline: `6b67b36`

## 1. Redesigned pages

- Login
- Dashboard
- Mosque directory, details modal, create form, and edit form
- Quran program directory, responsive cards, details modal, create form, and edit form
- Geographic mosque map
- Import, aggregate preview, export, custom-export, and rollback workspace
- Data-quality dashboard
- Trash and restore workspace
- Backup confirmation flow
- Shared empty, warning, validation, and error states

## 2. New components

- RTL application shell with fixed/collapsible desktop sidebar and focus-managed mobile drawer
- Page header/hero, metric card, data panel, table toolbar, quick action, role badge, upload zone, status badge, record card, and sticky form actions
- Reusable PHP partials: `page_header`, `metric_card`, `progress`, and `empty_state`
- Shared JavaScript modules for navigation, focus/accessibility behavior, feedback/confirmations, progress, and motion
- Page adapters for login, dashboard, Quran management, map, import/export, and backup confirmation
- CSS-only Atlas Noor arch/zellij scenes with reduced-motion/static fallbacks

## 3. Removed inline CSS

- Removed all 6 embedded `<style>` blocks identified in the baseline audit.
- Removed all 57 baseline `style="..."` attributes from views and related application scripts.
- Moved tokens, layout, components, page rules, accessibility, RTL, motion, and print styling into `resources/css/`.
- Final audit: 0 embedded style blocks and 0 application inline-style attributes.

## 4. Removed inline JavaScript

- Removed all 11 executable inline script blocks identified in the baseline audit.
- Moved layout, dashboard, mosque, Quran, and map behavior into external modules.
- Four nonce-protected `application/json` data payloads remain for server-to-page configuration; they contain no executable code.
- Google Maps is still loaded through external API script tags only where required.

## 5. Tailwind and Bootstrap coexistence

Tailwind CSS 4.3.2 compiles only theme and utilities, uses the `tw` prefix, and omits Preflight. Bootstrap RTL remains the compatibility layer for the existing markup and JavaScript components. Bootstrap and third-party styles load first; `app.min.css` loads last and maps Bootstrap variables to Atlas Noor tokens. This prevents global reset conflicts and preserves the current MVC and form contracts.

## 6. Libraries kept

- Bootstrap RTL for grid, modal, collapse, dropdown, tabs, and validation behavior
- Font Awesome as the self-hosted icon system
- jQuery and Select2, conditionally loaded on selector-heavy mosque/Quran pages
- SweetAlert2 for confirmation and destructive-action feedback
- Chart.js, conditionally loaded for mosque statistics
- Google Maps API, conditionally loaded for the map workspace and coordinate picker

## 7. Libraries removed

- Animate.css is no longer loaded; focused internal motion replaces it.
- Hover.css is no longer loaded; component hover/focus states replace it.
- Leaflet is not loaded because the application currently uses Google Maps. Its vendor files remain untouched for a future dependency-cleanup decision.
- Three.js was not introduced; CSS transforms and gradients provide the requested depth at substantially lower cost.

## 8. Performance impact

- Base compiled CSS: 40,278 bytes minified.
- Base application JavaScript: 5,720 bytes minified.
- Page scripts are split and loaded conditionally: dashboard 717 B, login 682 B, import/export 858 B, backup confirmation 1,521 B, maps 15,890 B, and Quran 38,576 B.
- jQuery, Select2, Chart.js, and Google Maps are not loaded globally when a page does not need them.
- No remote font is required, animations use transform/opacity, and reduced-motion mode disables nonessential work.
- The compiled assets total about 104 KB before compression, excluding existing third-party libraries.

## 9. Accessibility improvements

- Semantic landmarks, page headings, Arabic labels, and skip links
- Visible `:focus-visible` treatment and 44 px minimum control targets
- Focus trap, Escape close, and focus restoration for the mobile navigation drawer
- Text labels or accessible names for icon-only controls
- ARIA state on navigation and password visibility controls
- Status information includes text rather than relying on color alone
- `prefers-reduced-motion` handling and static CSS-3D fallbacks
- Existing form labels, validation feedback, permission boundaries, and Bootstrap modal accessibility retained

## 10. Responsive testing results

Headless Chrome captured and inspected seven authenticated/guest screenshots at 1440×1000, 1024×900, and 390×844: login desktop/mobile; dashboard desktop/tablet/mobile; mosque directory mobile; and map desktop. Every capture reported `scrollWidth === clientWidth`, with no viewport-level horizontal overflow. The sidebar becomes a focus-managed drawer below 992 px, desktop tables switch to record cards where appropriate, and toolbars/actions stack on narrow screens.

## 11. Feature regression results

- `foundation_test.php`: 22 passed
- `guide_imams_test.php`: 9 passed
- `mosque_crud_http_test.php`: 31 passed
- `quran_http_test.php`: 33 passed
- `import_export_http_test.php`: 21 passed
- Total PHP/feature assertions: 116 passed, 0 failed
- `smoke_http.sh`: passed login, dashboard, mosque/Quran/import/map routes, AJAX endpoints, clean route, CSP nonce, private-path boundaries, upload-script blocking, public assets, and logout
- Browser audit: no JavaScript runtime exceptions and no horizontal overflow; the brand favicon removed the only resource 404

## 12. Files changed

The current Atlas Noor implementation differs from the presentation baseline across 76 files. The later audit/error-visibility cleanup changes 19 files, including three deliberate deletions: the audit-log reader service, public audit shim, and audit view.

The work is organized into focused design and compatibility commits. Business-data controllers, repositories, database schema, request methods, field names, CSRF handling, AJAX response formats, and permission logic remain intact. A later cleanup deliberately removed the audit-view route and import error-report endpoint while retaining internal security logging.

### Post-redesign visibility cleanup

The dashboard recent-activity/import-issue widgets, audit/history page and route, per-row import diagnostics, downloadable import-error report, and UI-visible audit/error logs were subsequently removed. Internal exception and security audit logging remains server-side only; normal validation and concise user feedback remain available.

## 13. Build command

```bash
npm ci
npm run build
```

The build uses Tailwind CLI for CSS and esbuild for minified page/application bundles.

## 14. Deployment instructions

Build in CI or on a development machine, then deploy the PHP application together with the committed `public/assets/dist/` directory. Production/shared hosting does not need Node.js. Preserve the current environment configuration and writable upload/storage directories, then smoke-test login, an authenticated dashboard request, a form submission, an export download, and the Google Maps callback. Full instructions are in `docs/FRONTEND_BUILD.md`.

## 15. Known remaining limitations

- Google Maps rendering still depends on a valid deployment key and access to Google Maps endpoints.
- The visual browser audit used local headless Chrome because the in-app browser connector was unavailable; Safari and Firefox were not automated in this environment.
- Leaflet, old CSS files, and old JavaScript vendor/application files remain on disk for backward-compatible public-asset contracts, but are not loaded by redesigned views.
- The CSP still permits inline styles for third-party Bootstrap/Google Maps compatibility, although the application views no longer contain inline CSS.
- Backup remains the existing authenticated JSON download route, enhanced with a clear SweetAlert confirmation rather than converted into a new backend page.
- The application has no dedicated error-view templates; shared alerts, validation feedback, empty-state components, and the existing global error handler cover current routes.
