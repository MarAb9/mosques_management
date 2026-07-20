# Institutional UI Audit

Audit date: 2026-07-15  
Branch: `fix/institutional-ui-polish`  
Scope: presentation files only (`resources/views`, frontend assets, build documentation)

## Current application surface

The application has 18 PHP view files and exposes the following authenticated workspaces:

- Dashboard
- Mosque directory, details modal, create and edit forms
- Quran program directory, details modal, create and edit forms
- Geographic mosque map
- Excel/Word import and export
- Data quality and JSON backup actions
- Standalone login

All legacy public URLs, field names, methods, CSRF inputs, permission checks, AJAX response formats, and controller-supplied variables are presentation contracts and must remain unchanged.

## Inline asset inventory

Before the redesign the views contained:

- 6 embedded `<style>` blocks: login, dashboard, Quran create, Quran edit, Quran print markup, and map.
- 11 executable inline `<script>` blocks: shared layout, dashboard, mosque list, mosque edit bootstrap data, Quran directory/create/edit, and map configuration/behavior.
- 57 `style="..."` attributes, including dynamic progress widths, animation delays, map dimensions, conditional visibility, and form-map panels.

The redesign moves visual rules to `resources/css`, behavior to `resources/js` or existing external modules, and dynamic values to `data-*` attributes or safely encoded JSON data blocks where a large map payload makes an attribute inappropriate.

## Dependency audit

| Dependency | Decision | Reason |
| --- | --- | --- |
| Bootstrap RTL | Keep, isolate | Existing modals, tabs, collapse, dropdowns and validation behavior depend on it. Tailwind Preflight is disabled and all Tailwind utilities are prefixed. |
| Font Awesome | Keep | It is the only icon system in use and is self-hosted. |
| jQuery | Keep conditionally | Select2 and the current Quran form interactions depend on it. |
| Select2 | Keep, restyle | Mosque and Quran selectors rely on it. A replacement would expand scope and regression risk. |
| SweetAlert2 | Keep, restyle | Used for destructive and logout confirmation; it receives institutional tokens. |
| Chart.js | Keep conditionally | Used by the mosque quick-statistics modal and loaded only where needed. |
| Leaflet + Leaflet.markercluster | Keep conditionally | Both are bundled locally for the map workspace and coordinate picker; ArcGIS supplies the configured raster basemaps. |
| Animate.css | Remove from runtime | Replaced by a small internal transform/opacity motion layer with reduced-motion support. |
| Hover.css | Remove from runtime | Replaced by component focus/hover states in the design system. |

## Selector and contract risks

The most important selectors preserved by the redesign are:

- Shell/auth: `#logoutForm`, `#logoutButton`, CSRF meta tag.
- Mosque list: `#searchForm`, `#liveSearch`, `#clearSearch`, `#selectAll`, `#deleteSelected`, `.mosque-checkbox`, `.view-mosque-btn`, `.view-on-map`, `#mosqueDetailsModal`, chart canvas IDs.
- Mosque forms: all existing input names/IDs, `#mapContainer`, `#map`, `#showMapBtn`, `#getCurrentLocationBtn`, image preview IDs, administrative hierarchy IDs, and `data-guard-unsaved`.
- Quran: `#quranForm`, step and responsible selectors, `#mosque_registration_number`, list bulk-selection IDs, and `#quranMosqueDetailsModal`.
- Map: `#globalSearch`, filter IDs, `#map`, map state panels, marker/list hooks, and pagination links.
- Import/export: `#importPreviewForm`, `#import_file`, `#filterModal`, and every export field name.

## Usability and accessibility findings

- The horizontal navigation becomes crowded in Arabic and has weak active-page context.
- Page headings, actions, statistics, and filters use unrelated visual patterns.
- Rainbow gradients weaken status meaning and institutional tone.
- Large tables are the default mobile experience on Quran and administrative pages.
- Several icon-only controls lack consistent focus treatment.
- Dynamic progress uses inline width styles and counters start at zero without an immediate non-animated value.
- Map and Quran logic is embedded in templates, making CSP maintenance and testing difficult.
- Motion is supplied by a full library and appears on many unrelated elements.
- The login page uses a large photographic background and an embedded stylesheet.

## Baseline evidence and constraints

- Docker serves the project on `http://127.0.0.1:8085` and the login endpoint returns HTTP 200.
- The login response was 7,174 bytes and contained one embedded style block before redesign.
- Automated in-app browser capture was attempted before changes, but the environment rejected the browser connection before a tab could be opened. Runtime HTTP, rendered-HTML, build, test, overflow-oriented CSS checks, and a later browser retry are used as fallback evidence.
- The pre-existing `.env.example` modification is unrelated user work and is excluded from redesign commits.

## Responsive risks to verify

- Sidebar/off-canvas behavior at 320, 375, and 430 px.
- Page action wrapping and search/filter controls at 768 px.
- Table-to-card transitions and no horizontal page overflow.
- Long Arabic mosque names, national codes, role labels, and audit metadata.
- Map panel height and mobile filter drawer behavior.
- Sticky form actions without obscuring the final fields.

