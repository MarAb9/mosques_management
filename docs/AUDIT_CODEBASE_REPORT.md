# Codebase Audit Report

## Audit Boundary

This report is an audit of the current native PHP application as copied into the local workspace on 2026-05-21. The audit inspected first-party PHP, JavaScript, CSS, Docker/deployment files, Composer files, the tracked SQL dump schema, Git-tracked artifact categories, and the upload directory layout. No business code was changed during the audit.

Sensitive values are intentionally masked. The tracked SQL dump contains live-looking operational data and user credential hashes; this report describes that risk without reproducing those values.

## 1. Executive Summary

| Metric | Score |
| --- | --- |
| Project health | 4 / 10 |
| Security | 3 / 10 |
| Maintainability | 3 / 10 |
| Refactor risk | High |

The application is functional and already has several useful foundations: it uses PDO, most user-supplied query values are parameterized, image uploads have first-pass MIME/extension/image validation, Quran create/edit operations use transactions, and key mosque CRUD pages perform backend role checks. The current risk is not that the project must be rewritten. The risk is that production behavior is concentrated in long mixed-responsibility pages, security controls are inconsistent, and sensitive deployment artifacts are currently inside the web-facing repository layout.

### Biggest 10 Risks

1. `add_mosque.php:82` and `add_mosque.php:84` execute the same mosque `INSERT` twice in one POST request. This is the most likely source of duplicate mosque rows.
2. `mosques.national_code` is indexed but not unique in the SQL dump schema, so application duplicate checks have no database backstop.
3. `ezyro_40059332_mosques_berkane.sql` is tracked in the repository and lives in the Apache document-root copy path. It contains live-looking mosque data, personal data, and user password hashes.
4. Git tracks `vendor/` and 228 uploaded mosque images, and there is no root `.gitignore`. The Dockerfile copies the repository into `/var/www/html`.
5. `fix_coordinates.php` is a web-root database mutation script with no authentication or CSRF gate.
6. Mosque add/edit/import forms lack CSRF validation, and single mosque/Quran deletes are triggered by GET links.
7. `add_quran_mosque.php:8` reverses the create permission test. Admins are rejected when `canCreateMosque()` is true, while non-admin authenticated users can continue.
8. `ajax/get_mosques_for_map.php` and `ajax/get_mosque_stats.php` do not call `checkAuth()`.
9. Imported spreadsheet values can reach PHP string-built rows and JavaScript `innerHTML` rendering paths without a consistent output escaping contract.
10. User-visible error paths include exception messages from PDO and import/export operations, leaking implementation detail during failures.

## 2. Current Architecture Map

### Project Structure

Current first-party folders:

| Path | Current role | Audit notes |
| --- | --- | --- |
| `ajax/` | JSON endpoints for mosque details, search, map data, and statistics | Auth is inconsistent by endpoint. |
| `assets/css/` | Global and module CSS | Some pages also carry large inline CSS blocks. |
| `assets/js/` | Mosque list and mosque form JavaScript | `assets/js/mosque.js` is a large mixed-responsibility script. |
| `assets/images/` | Logo and login imagery | Used from public pages. |
| `docker/` | Apache and PHP upload config | Local Docker stack exists. |
| `includes/` | Config, auth helpers, shared header/footer, mosque form helpers | `config.php` currently owns too many responsibilities. |
| `uploads/mosques/` | Uploaded mosque images | Public folder is tracked and has no upload execution guard file in the repo. |
| `vendor/` | Composer dependencies | Tracked and web-root visible in current layout. |

Current folder-level file inventory excluding tracked dependency internals and individual uploaded images:

- `ajax/`: `get_mosque_details.php`, `get_mosque_stats.php`, `get_mosques_for_map.php`, `get_quran_mosque_details.php`, `search_mosques.php`.
- `assets/css/`: `footer.css`, `header.css`, `mosque.css`, `style.css`.
- `assets/js/`: `mosque.js`, `mosque_form.js`, `script.js`.
- `assets/images/`: `1.jpg`, `2.png`, `logo.png`.
- `docker/`: `apache/server-name.conf`, `php/uploads.ini`.
- `includes/`: `auth.php`, `auth_check.php`, `config.php`, `footer.php`, `header.php`, `mosque_functions.php`.
- Root support files: `.dockerignore`, `.env.example`, `composer.json`, `composer.lock`, `compose.yaml`, `Dockerfile`, `README.md`, `SECURITY.md`, and `ezyro_40059332_mosques_berkane.sql`.

Current root entry/action files:

| File | Role |
| --- | --- |
| `login.php`, `logout.php` | Session entry and exit |
| `index.php` | Dashboard |
| `mosques.php` | Mosque list, filters, search shell, list actions |
| `add_mosque.php`, `edit_mosque.php` | Mosque create/update forms and handlers |
| `delete_mosque.php`, `bulk_delete_mosques.php` | Mosque delete handlers |
| `quran_mosques.php` | Quran program list, filters, modals, delete shell |
| `add_quran_mosque.php`, `edit_quran_mosque.php` | Quran program create/update forms and handlers |
| `delete_quran_mosque.php` | Quran program delete handler |
| `mosque_maps.php` | Map page and map/filter payload setup |
| `import_export.php` | Excel import and export handler plus UI |
| `fix_coordinates.php` | One-off coordinate maintenance write script currently public |

### Includes

| Include | Current role | Risk |
| --- | --- | --- |
| `includes/config.php` | Session start, CSRF token creation, environment lookup, DB constants, PDO connection, `checkAuth()` | Configuration, database access, session work, and auth redirect are coupled. Connection exceptions are exposed. |
| `includes/auth.php` | Login POST processing | Included by `login.php`; no rate limiting or session ID regeneration. |
| `includes/auth_check.php` | Role helpers | Role names and helper semantics are inconsistent with data and pages. |
| `includes/mosque_functions.php` | Input cleanup, GPS/year/phone parsing, image validation, mosque form shaping | Useful start for shared logic, but it mixes sanitization and validation semantics. |
| `includes/header.php`, `includes/footer.php` | Shared layout and frontend dependencies | Header carries inline JavaScript and exposes a CSRF meta tag to pages that include it. |

### Action and POST Handlers

Current POST/write handlers are page controllers rather than a dedicated `actions/` layer:

- `includes/auth.php` handles login POST.
- `add_mosque.php` and `edit_mosque.php` handle mosque POSTs inline.
- `delete_mosque.php` and `bulk_delete_mosques.php` handle delete POSTs; `delete_mosque.php` also deletes by GET.
- `add_quran_mosque.php` and `edit_quran_mosque.php` handle Quran program POSTs inline.
- `delete_quran_mosque.php` handles bulk POST deletes and single GET deletes.
- `import_export.php` handles Excel import POST and export GET.
- `fix_coordinates.php` performs a write on direct request.

### AJAX Endpoints

| Endpoint | Main data | Current auth status |
| --- | --- | --- |
| `ajax/search_mosques.php` | Paginated mosque search data | Calls `checkAuth()`. |
| `ajax/get_mosque_details.php` | Mosque details plus Quran programs and responsibles | Calls `checkAuth()`. |
| `ajax/get_quran_mosque_details.php` | Quran program details and responsibles | Calls `checkAuth()` through `includes/config.php`. |
| `ajax/get_mosques_for_map.php` | Coordinate-bearing mosques | Does not call `checkAuth()`. |
| `ajax/get_mosque_stats.php` | Mosque count aggregates | Does not call `checkAuth()`. |

### Composer, Assets, Uploads, and Public Files

- `composer.json` declares `phpoffice/phpspreadsheet` for Excel import/export.
- `vendor/` is present and tracked. Current Docker build runs Composer and then `COPY . .`, so tracked dependency contents remain part of the document-root tree unless server rules block access.
- `uploads/mosques/` holds uploaded image files. No first-party `.htaccess`, `web.config`, `index.php`, or equivalent upload execution guard was found under `uploads/`.
- `ezyro_40059332_mosques_berkane.sql` is both the Docker seed dump and a tracked public-root file.
- No root `.gitignore` exists. `.dockerignore` excludes `.env`, `.git`, `.gitignore`, `node_modules`, and `vendor`, but not the SQL dump or uploads.

Files that should not be directly public in a production deployment:

- Real configuration such as `includes/config.php` when it contains deployment defaults or secrets.
- SQL dumps and backups such as `ezyro_40059332_mosques_berkane.sql`.
- `vendor/` source trees and development metadata.
- One-off maintenance scripts such as `fix_coordinates.php`.
- Any uploaded file directory that can execute PHP or disclose directory listings.
- Future local `.env`, backup, log, export, and database files.

### Current Architectural Pattern

The application is a page-controller PHP application. Route files commonly:

1. Require config/auth includes.
2. Run SQL directly.
3. Handle POST or GET write behavior.
4. Build HTML and inline JavaScript/CSS in the same file.

This pattern is visible in `add_mosque.php`, `edit_mosque.php`, `import_export.php`, `quran_mosques.php`, `add_quran_mosque.php`, `edit_quran_mosque.php`, `index.php`, and `mosque_maps.php`. It keeps current routes simple but makes small refactors risky because view markup, validation, SQL, uploads, redirects, role checks, and messages are edited together.

## 3. File Size and Complexity Table

The audit thresholds were 300 and 500 lines. Files over 300 lines are listed below. Files over 500 lines are marked in the priority notes.

| File path | Lines | Main responsibility today | Problems | Suggested future split | Priority |
| --- | ---: | --- | --- | --- | --- |
| `assets/js/mosque.js` | 1562 | Mosque list UI, search, stats, details modal, map buttons, bulk delete UI, print UI | Multiple DOMContentLoaded blocks, repeated listener setup, string-built HTML, mixed data/UI logic | `mosque-search.js`, `mosque-details.js`, `mosque-bulk-actions.js`, `mosque-stats.js`, shared DOM helpers | High, over 500 |
| `mosque_maps.php` | 1482 | Map page UI, filters, map payload prep, styles, scripts | Page is a controller, query service, template, and frontend module at once; loads all coordinate rows for search | Page template plus map query helper, map JS asset, map CSS asset | High, over 500 |
| `quran_mosques.php` | 931 | Quran list, filters, aggregates, modal JS, row rendering, delete JS | SQL inside rendering, N+1 lookups, inline JS, unsafe string concatenation risk | Quran list query helper, row template, modal JS, delete action UI helper | High, over 500 |
| `edit_quran_mosque.php` | 922 | Quran edit form, transactions, related responsibles, inline styling | Handler and large form UI are coupled | Form template, `actions/quran_update.php`, Quran service/helper | High, over 500 |
| `add_quran_mosque.php` | 796 | Quran create form, transaction, related responsibles, inline styling | Reversed authorization check and mixed concerns | Form template, `actions/quran_create.php`, shared responsible validation | High, over 500 |
| `mosques.php` | 715 | Mosque list, filters, stats, table row renderer, bulk action JS | Duplicated national-code filter block, SQL inside view, unescaped row concatenation | Mosque list query helper, list template, row presenter, filters helper | High, over 500 |
| `edit_mosque.php` | 568 | Mosque edit handler, upload handling, form UI | Update, upload deletion, validation, markup, and inline data bootstrap are coupled | Form template, `actions/mosque_update.php`, upload helper, mosque service | High, over 500 |
| `index.php` | 528 | Dashboard queries and dashboard view | Many page-local aggregate queries and large inline styles | Dashboard query helper, dashboard template, dashboard CSS/JS assets | Medium, over 500 |
| `add_mosque.php` | 525 | Mosque create handler, upload handling, duplicate check, form UI | Double `INSERT` execute, missing CSRF, mixed create flow | Form template, `actions/mosque_create.php`, mosque service, upload helper | Critical, over 500 |
| `import_export.php` | 450 | Excel import/export handler and UI | File validation, import mapping, export generation, UI, and error handling in one page | `actions/mosque_import.php`, `actions/mosque_export.php`, import mapper, export builder | High |

Why these files are risky:

- The long PHP pages combine backend writes with user-facing markup. A markup edit can change POST behavior accidentally.
- The long list/map pages own both data retrieval and UI state. Query performance fixes and view fixes collide.
- The large JavaScript file repeats setup behavior and uses high-risk `innerHTML` construction with server data.
- Most large pages are public routes, so file moves during refactor can break URLs and includes unless wrappers remain.

## 4. Security Findings Table

| ID | Severity | File | Problem | Evidence | Risk | Recommended fix | Phase |
| --- | --- | --- | --- | --- | --- | --- | --- |
| SEC-01 | Critical | `ezyro_40059332_mosques_berkane.sql`, repository root | SQL dump with live-looking operational and credential data is tracked under current web-root layout | Dump includes `users` inserts with password hashes and mosque/responsible personal data; Dockerfile copies repo into `/var/www/html` | Backup/data disclosure if web server serves the dump or repository is shared | Remove public exposure after backup, stop tracking production dumps, keep sanitized seed strategy, ignore `*.sql`/backup paths | Phase 0 / 3 |
| SEC-02 | High | `fix_coordinates.php` | Public maintenance script mutates mosque coordinates without auth or CSRF | File only requires `includes/config.php`, selects rows, updates rows on direct request | Unauthenticated write access and repeatable operational mutation | Remove from public route set or gate behind CLI/admin maintenance flow after backup | Phase 2 or earlier hotfix |
| SEC-03 | High | `add_mosque.php`, `edit_mosque.php`, `import_export.php`, `login.php` | POST forms lack complete CSRF protection | Forms at `add_mosque.php:113`, `edit_mosque.php:181`, `import_export.php:280`, `login.php:200`; no matching token validation in those handlers | Cross-site write or import requests from an authenticated admin; login CSRF/session confusion | Centralize CSRF helper and validate every state-changing POST; decide login CSRF policy explicitly | Phase 2 |
| SEC-04 | High | `delete_mosque.php`, `delete_quran_mosque.php`, list pages | Single delete operations use GET | Links at `mosques.php:471` and `quran_mosques.php:452`; delete handlers delete when `$_GET['id']` exists | Link visit, prefetch, or CSRF-like navigation can delete data | Make deletes POST-only with CSRF and server-side permission checks | Phase 2 |
| SEC-05 | High | `add_quran_mosque.php` | Quran create authorization check is inverted | `if (canCreateMosque())` returns 403 at file start | Admin create flow is blocked and lower-privilege authenticated users can reach create logic | Correct authorization logic and add role regression tests before structure work | Phase 1 or 2 |
| SEC-06 | High | `mosques.php`, `quran_mosques.php`, `assets/js/mosque.js`, import path | Output escaping is inconsistent for database/imported values | PHP row renderers concatenate values into HTML; search/detail modals write values through `innerHTML`; Excel import stores row values directly | Stored or DOM XSS from imported or edited content | Establish output escaping helpers and DOM-safe rendering; validate spreadsheet text; test notes/name/address fields | Phase 2 / 7 / 8 |
| SEC-07 | Medium | `ajax/get_mosques_for_map.php`, `ajax/get_mosque_stats.php` | AJAX auth gate is missing | These endpoints include config/auth helper files but do not call `checkAuth()` | Unauthenticated exposure of mosque coordinate and aggregate data | Add route-level auth decision and tests; if public data is intended, document and minimize fields | Phase 2 |
| SEC-08 | Medium | `includes/config.php`, AJAX handlers, write handlers | Exception details are returned to users | `getMessage()` appears in JSON/user session paths and DB connection `die()` | Schema, path, and query detail leakage during failures | Log internal errors; return safe user messages and production error settings | Phase 2 |
| SEC-09 | Medium | `includes/config.php`, `includes/auth.php`, `logout.php` | Session and login hardening is minimal | `session_start()` without secure cookie policy; no `session_regenerate_id()` after login; no rate limit or lockout path found | Session fixation and brute-force exposure | Set secure session policy, regenerate on login, add login throttling/logging policy | Phase 2 |
| SEC-10 | Medium | `uploads/`, deployment layout | Upload directory has no repo-level execution guard | `uploads/mosques/` is public and no `.htaccess`/equivalent guard exists | Future upload validation mistakes can become code execution or data exposure | Deny script execution in uploads, keep MIME/image checks, use safe paths and permissions | Phase 7 / 9 |
| SEC-11 | Medium | Repository and Docker layout | Root `.gitignore` and public-file policy are missing | Git tracks 749 vendor files, 228 uploads, SQL dump, and config; `.dockerignore` is not deployment protection | Secrets/artifacts drift into commits and docroot | Add `.gitignore`, `config.example.php`, deployment exclusion rules, and backup policy | Phase 0 / 3 |
| SEC-12 | Low | Shared layout/server configuration | No first-party security header policy found | No header policy for CSP, frame ancestors/X-Frame-Options, referrer, nosniff, or HSTS in app/Docker Apache config | Browser hardening depends on hosting defaults | Add host-compatible security header baseline after XSS/inline script review | Phase 2 / 8 |

Positive security observations:

- Login uses `password_verify()` in `includes/auth.php`.
- No first-party `md5()` or `sha1()` password path was found in reviewed application files.
- Most user-supplied SQL values in reviewed first-party routes use PDO placeholders.
- Image upload validation checks extension, MIME type, file size, and `getimagesize()` in `includes/mosque_functions.php`.

## 5. Code Quality Findings Table

| ID | Severity | File | Problem | Why it matters | Recommended refactor | Phase |
| --- | --- | --- | --- | --- | --- | --- |
| CQ-01 | Critical | `add_mosque.php` | Prepared mosque insert is executed twice | One submitted mosque can create two records before redirect | Remove duplicate execute first and add regression coverage | Phase 1 |
| CQ-02 | High | Long route files | HTML, SQL, auth, validation, upload logic, messages, and scripts are mixed | Changes have large blast radius and are hard to test | Extract actions and helpers gradually while keeping public route wrappers | Phase 4-9 |
| CQ-03 | High | `assets/js/mosque.js` | Multiple page concerns and repeated DOM setup | Repeated listeners and divergent frontend behavior become likely | Split by behavior and keep one initialization entry per page | Phase 8 |
| CQ-04 | Medium | `delete_mosque.php`, `bulk_delete_mosques.php` | Overlapping bulk deletion routes | Security and behavior can diverge | Choose one protected bulk delete path and keep compatibility wrapper if needed | Phase 5 / 9 |
| CQ-05 | High | `includes/auth_check.php`, Quran add route, DB roles | Role terminology is inconsistent | Helpers mention `user`, SQL schema uses `editor`, some code equates create/edit/delete with admin only | Define role matrix and route policy before refactor | Phase 2 |
| CQ-06 | High | `includes/config.php` | Config include also starts sessions, generates CSRF token, opens PDO, and defines auth redirect | Every page include has hidden side effects | Split config, DB connection, session bootstrap, CSRF, auth helper | Phase 3 / 4 |
| CQ-07 | Medium | List/dashboard/map pages | Direct SQL is embedded in views | Performance and output changes are coupled | Move query assembly/aggregation into helpers/services | Phase 4-6 |
| CQ-08 | Medium | `mosques.php` | `national_code` filter is appended twice | Search behavior and parameter count are harder to reason about | Collapse filter construction into one tested builder | Phase 5 |
| CQ-09 | Medium | `import_export.php` | Import catches row-level PDO errors and continues without a detailed reject report | Operators cannot tell data validation failures from SQL failures | Add importer validation report and duplicate/error policy | Phase 7 |
| CQ-10 | Medium | Repeated inline CSS/JS | Frontend behavior and styles are spread across route files | Asset cleanup and CSP hardening become difficult | Move page CSS/JS gradually after behavior tests | Phase 8 |

## 6. Database Findings Table

| Table/column/query | Problem | Risk | Recommended fix | Migration needed | Phase |
| --- | --- | --- | --- | --- | --- |
| `mosques.national_code` | Indexed twice in dump schema but not unique | Duplicate mosque identity and ambiguous Quran FK target | Audit existing duplicates, decide null/blank policy, then add unique protection if business rules confirm it | Yes | Phase 1 |
| `mosques.registration_number` | Primary key and auto increment | Stable technical identifier already exists but user workflows use national code for relations | Keep as technical ID; do not repurpose it during first refactor | No | Phase 1-5 |
| `quran_memorization_programs.mosque_registration_number` | Name suggests registration number but stores `mosques.national_code` | Ambiguous code and migrations are easy to get wrong | Document semantic mismatch; rename only in a planned migration later | Yes if renamed | Phase 6 or later |
| Quran FK to `mosques.national_code` | FK references a non-unique business column | Duplicate mosque codes make joins and relations ambiguous | Make mosque identity constraint explicit before Quran structural changes | Yes | Phase 1 / 6 |
| `quran_memorization_programs.mosque_registration_number` | No unique rule limiting one program row per mosque | UI filters existing programs, but races/direct writes can create duplicates | Decide whether one Quran program per mosque is invariant and add unique protection if yes | Yes | Phase 6 |
| `mosques.construction_date` | Stored as `varchar(50)` in schema | Date parsing, sorting, and import consistency are fragile | Delay type migration until data is profiled and tests exist | Yes | Later planned DB phase |
| Import duplicate check | `SELECT COUNT(*) WHERE national_code = ?` before insert only | Race conditions and direct double execute are not stopped | Use DB uniqueness plus importer duplicate policy | Yes | Phase 1 / 7 |
| Mosque create/update + image file | DB write and file operations are not coordinated transactionally | Failed write can leave orphan files; failed unlink can desync image path | Add controlled upload helper and cleanup rules | No schema migration | Phase 4 / 5 |
| Mosque delete vs Quran FK | Mosque delete does not show relationship handling policy | FK may reject delete or related data handling becomes unclear | Define delete policy: restrict with message, cascade by plan, or require Quran cleanup | Maybe | Phase 5 / 6 |
| Filter/search queries | Schema dump only shows mosque national-code indexes for common list filters | `community`, `status`, `friday_prayer`, names, and `%LIKE%` scans may become slow | Measure real query plans, then add targeted indexes or search strategy | Yes if indexes added | Performance phase |

### Database Usage Notes

- Reviewed first-party SELECT/INSERT/UPDATE/DELETE call sites are concentrated in root route files and `ajax/`.
- User-driven list filters generally use prepared statements; dynamic sort columns in `mosques.php` and `quran_mosques.php` are allowlisted.
- Static `$pdo->query()` calls remain widely embedded in dashboard/list view files.
- Quran add/edit and import flows use transactions. Mosque create/edit do not need multi-row DB transactions today, but their file-system work still needs failure policy.
- `quran_mosques.php` performs additional responsible/status queries while rendering each row, an N+1 pattern.

## 7. Form and POST Handling Table

| Form/page | Action file | CSRF status | Validation status | Permission check status | Redirect after POST status | Duplicate submit risk | Priority |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Login form, `login.php:200` | `includes/auth.php` via same page POST | Missing | Username/password only trimmed; password verification exists | Public by design | Redirect on success; render same page on failure | Brute-force and repeat attempts not throttled | High |
| Add mosque form, `add_mosque.php:113` | Same page | Missing | Required field helper, national-code pre-check, image validation | Backend `canCreateMosque()` | Redirects on success | Critical: same `INSERT` executes twice; race/double-click not DB-protected | Critical |
| Edit mosque form, `edit_mosque.php:181` | Same page | Missing | Required field helper and image validation | Backend `canEditMosque()` | Redirects on success | Repeated POST can re-run update/upload/remove behavior | High |
| Mosque single delete link | `delete_mosque.php?id=...` | Missing because GET write | ID passed by GET | Backend `canDeleteMosque()` | Redirects | Navigation/prefetch/delete replay risk | High |
| Mosque bulk delete JS form | `bulk_delete_mosques.php` | Present | Selected IDs checked for non-empty list | Backend `canDeleteMosque()` | Redirects | Better than GET path; overlaps with `delete_mosque.php` bulk logic | Medium |
| Quran add form, `add_quran_mosque.php:366` | Same page POST | Present | Some filtering; related mosque and enum/business validation need tightening | Reversed at file start | Redirects on success | DB lacks one-program-per-mosque protection | High |
| Quran edit form, `edit_quran_mosque.php:398` | Same page POST | Present | Some filtering; transaction replaces responsibles | Backend `canEditMosque()` | Redirects on success | Repeated POST rewrites responsible rows | Medium |
| Quran single delete link | `delete_quran_mosque.php?id=...` | Missing because GET write | ID passed by GET | Backend `canDeleteMosque()` | Redirects | Navigation/prefetch/delete replay risk | High |
| Quran bulk delete JS form | `delete_quran_mosque.php` | Present | Selected ID list used in placeholders | Backend `canDeleteMosque()` | Redirects | Protected POST path exists | Medium |
| Excel import form, `import_export.php:280` | Same page | Missing | Browser `accept` only; importer checks required row columns and duplicate national code | Backend `canImportData()` | Redirects | Repeat upload can reprocess file; DB uniqueness absent | High |
| Excel export GET form/link | `import_export.php?export=1` | N/A for read export | Filter allowlist for WHERE fields | Authenticated page; non-admin export appears intended | Streams file | Data volume/sensitive export policy risk | Medium |
| Mosque/Quran search forms | Same list pages GET | N/A | Query/filter allowlists exist | Pages call `checkAuth()` | GET render | No write risk | Low |

## 8. Duplicate Mosque Creation Investigation

### All Mosque Insert Locations Found

| File | Insert path |
| --- | --- |
| `add_mosque.php:72` | Interactive mosque create page |
| `import_export.php:77` | Excel import loop |

### Duplicate Mosque Creation — Root Cause Candidates

| Candidate | File / location | Risk | Evidence | Suggested fix for later phase |
| --- | --- | --- | --- | --- |
| Same prepared insert executed twice | `add_mosque.php:82` and `add_mosque.php:84` | Critical | Two adjacent `$stmt->execute(array_values($formData));` calls follow one prepared mosque `INSERT` | Remove the duplicate execute first and add regression tests for one POST -> one row |
| No DB uniqueness on business identity | SQL dump `ALTER TABLE mosques` index block | High | `national_code` has ordinary indexes, not a unique key | Profile existing data, define blank/null policy, add unique protection when safe |
| Race between duplicate pre-check and insert | `add_mosque.php:29-35` and importer duplicate check | Medium | Application checks then inserts later; no DB lock/unique key | Keep user-friendly duplicate check but rely on unique index and duplicate-key handling |
| Import can create identity duplicates under concurrency or bad identity policy | `import_export.php:22-108` | Medium | Import checks national code row by row, but schema does not enforce uniqueness | Make importer duplicate policy explicit and let DB constraint protect final write |
| Double click/repeated request | Add mosque form and submit UI | Medium | No submit-disable/idempotency control found; DB protection absent | Add frontend submit guard only after server/DB protections; keep PRG |
| Refresh/resubmit after successful create | `add_mosque.php:87` | Low for success path | Success path already redirects to `mosques.php`; duplicate happens before redirect today | Preserve PRG; test refresh after success |
| JavaScript submits add form through a second AJAX path | `assets/js/mosque_form.js` | Low based on audit | Form script adds file-size validation submit listener only; no add-mosque AJAX write path found | Keep one submission path and test after JS cleanup |

### Most Likely Root Cause

The most likely root cause is the duplicate `execute()` in `add_mosque.php`. A single successful request can insert two rows before the success redirect. The current national-code pre-check runs before both execute calls, so it cannot stop the second insert in the same request.

### Recommended Safe Fix

1. Back up the database.
2. Remove the duplicate execute in `add_mosque.php` only.
3. Verify one successful POST inserts one row.
4. Verify double-click, refresh after success, and duplicate national-code error behavior.
5. Add database protection only after checking existing duplicate/null national-code data and Quran FK impact.

### Recommended DB Protection

If `national_code` is confirmed to be mandatory and unique for every mosque, add a unique index on normalized non-empty `mosques.national_code` after duplicate cleanup and backup. If blank/null codes are valid, define a safe uniqueness policy before migration. `registration_number` remains the technical primary key.

## 9. Focused Audit Notes

### Architecture and Maintainability

- Direct SQL appears in view pages such as `index.php`, `mosques.php`, `quran_mosques.php`, and `mosque_maps.php`.
- Database connection code is centralized today through `includes/config.php`, but config is over-coupled to session and auth behavior.
- Validation and upload helper reuse exists for mosque forms, but import and Quran forms use separate validation/filtering patterns.
- Role checks are repeated at route tops and UI hides admin controls, but backend checks are not uniform across all write/public endpoints.
- Hardcoded relative paths exist for includes, uploads, assets, AJAX URLs, and route redirects. This makes later file moves risky.

### Authentication and Authorization

| Area | Current status | Risk |
| --- | --- | --- |
| Login | Uses PDO lookup and `password_verify()` | No CSRF decision, throttling, audit log, or session regeneration path found |
| Logout | Destroys session and redirects | GET logout; cookie invalidation policy not explicit |
| Protected pages | Main route pages call `checkAuth()` | Maintenance and some AJAX endpoints miss the gate |
| Admin-only mosque CRUD | Add/edit/delete/import have server-side role checks | CSRF and GET delete issues remain |
| Quran create | Permission check is reversed | Role violation and broken admin workflow |
| Role matrix | SQL role enum is `admin`/`editor`; helper also refers to `user` | Permission intent is unclear and needs documentation before refactor |

### Upload and File Handling

| Check | Current state |
| --- | --- |
| Allowed extensions | Mosque images allow jpg/jpeg/png in `validateImageUpload()` |
| MIME validation | `finfo` is used |
| Image validation | `getimagesize()` is used |
| File size | 2 MB application limit in mosque forms |
| File name handling | New images use `uniqid('mosque_')` plus validated extension |
| Storage path | `uploads/mosques/` under public tree |
| Executable upload guard | Not found in repo |
| Old file replacement | Edit flow unlinks prior DB-stored image path |
| File delete boundary | Old image path is not constrained to an upload root before `unlink()` |

### Import and Export

- Import uses `PhpSpreadsheet\IOFactory::load()` on the uploaded temporary file without a server-side file extension/MIME/size policy in the handler.
- Import uses a transaction, skips rows missing mosque name or national code, and counts duplicates by national code.
- Import catches per-row PDO exceptions and continues. Operators lose detail about which rows failed and why.
- Import mapping writes spreadsheet text directly to DB fields without reusing mosque normalization helpers.
- Export is available from `import_export.php` to authenticated users according to current UI. It includes personal/operational columns and needs an explicit authorization/data-minimization decision.
- Formula injection or spreadsheet output sanitation was not addressed in the current export builder.

### Frontend

- Bootstrap RTL is used, but pages also carry substantial inline styles and route-local scripts.
- Arabic RTL layout is the dominant UI convention. Asset and script cleanup must not change labels or DOM IDs casually.
- `assets/js/mosque.js` repeats page setup work and uses inline event attributes in generated HTML.
- Map UI and list modal behavior rely on CDN libraries and shared header dependencies.
- The audit did not run a browser console session; static code review already found duplicate listener setup risk and DOM string-rendering risk.

### Error Handling and Logging

- Several handlers use try/catch, but many user messages concatenate `$e->getMessage()`.
- Only selected paths call `error_log()`. There is no consistent audit log for create/update/delete/login/import events.
- Production `display_errors` policy is not explicit in first-party app config.
- `includes/config.php` fails with a raw DB connection message.

### Git and Deployment

| Check | Current state |
| --- | --- |
| Root `.gitignore` | Missing |
| `.env.example` | Present |
| `config.example.php` | Missing |
| README | Present but local Docker setup only |
| DB setup docs | Minimal README instructions tied to tracked dump |
| Freehost deployment docs | Missing |
| SQL dump tracked | Yes |
| Uploaded images tracked | Yes |
| Vendor tracked | Yes |
| Config tracked | Yes |

### Performance

- `quran_mosques.php` renders row-level status and responsible queries inside the list loop.
- `mosque_maps.php` paginates display data but also loads all coordinate-bearing mosques for search.
- Dashboard/list pages issue repeated aggregates from route files.
- Many search predicates use `%LIKE%`; schema indexing is sparse for current filter fields.
- Frontend assets are large or CDN-heavy; `assets/js/mosque.js` is especially large for every page that includes it.

## 10. Functional Risk Audit

| Feature | Current status from code audit | Files involved | Main risks | Required tests before refactor |
| --- | --- | --- | --- | --- |
| Login/logout | Present | `login.php`, `includes/auth.php`, `logout.php` | Session hardening and brute-force controls missing | Valid login, invalid login, logout, protected route redirect |
| Dashboard | Present | `index.php` | Many inline queries/styles | Admin/editor dashboard render and counts |
| Mosque list/search/filter | Present | `mosques.php`, `assets/js/mosque.js`, `ajax/search_mosques.php` | Mixed live search/server filtering, XSS/render risk, duplicate filter logic | Search names/codes, filters, sorting, pagination, modal details |
| Add mosque | Present but duplicate bug | `add_mosque.php`, `includes/mosque_functions.php`, `assets/js/mosque_form.js` | Double insert, missing CSRF, upload/file rollback | Required fields, duplicate national code, add with/without image, one row only |
| Edit mosque | Present | `edit_mosque.php`, shared mosque helpers | Missing CSRF, image replacement/removal paths | Edit fields, replace image, remove image, permissions |
| Delete mosque | Present | `delete_mosque.php`, `bulk_delete_mosques.php`, `mosques.php` | GET delete and Quran relationship policy | Single delete blocked/migrated to POST, bulk delete, FK behavior |
| Mosque details | Present | `ajax/get_mosque_details.php`, `assets/js/mosque.js` | Error disclosure and modal output escaping | Open detail modal with notes/person data and Quran links |
| Quran list/details | Present | `quran_mosques.php`, Quran AJAX endpoint | N+1 and output escaping | Filter, paginate, modal detail, totals |
| Add Quran program | Present but permission bug | `add_quran_mosque.php` | Reversed role check, relation uniqueness | Admin create, editor blocked, responsibles transaction |
| Edit Quran program | Present | `edit_quran_mosque.php` | Replace-responsibles transaction needs regression tests | Edit mosque link, responsibles add/remove, rollback on invalid input |
| Delete Quran program | Present | `delete_quran_mosque.php`, list JS | GET delete | Single/bulk delete POST with CSRF after fix |
| Map/GIS | Present | `mosque_maps.php`, map AJAX | All coordinate rows loaded and AJAX auth gap | Map markers, filters, map links, auth on endpoints |
| Import/export | Present | `import_export.php`, Composer dependency | Validation/error/data-exposure risks | Valid import, invalid file, duplicate rows, export filters, permissions |
| Image upload | Present | Mosque forms and helper | Public upload folder and file cleanup | JPG/PNG valid, invalid MIME/ext, size limit, overwrite check |
| Roles | Present but inconsistent | `auth_check.php`, header, route tops | `editor` vs `user`, reversed Quran add | Direct URL access matrix for admin/editor |

## 11. Refactor Opportunities

### Quick Wins

- Remove the duplicate mosque insert execute after backup.
- Replace GET deletes with POST-only delete actions and CSRF.
- Fix the Quran create permission inversion.
- Gate unauthenticated AJAX endpoints or document their public status explicitly.
- Stop exception messages from reaching users.
- Add root `.gitignore`, config example policy, and upload/dump tracking policy.

### Medium Refactors

- Centralize CSRF/session/redirect/flash helpers.
- Move mosque create/update/delete logic out of page templates.
- Reuse validation and duplicate policy between add and import.
- Split `assets/js/mosque.js` by behavior.
- Move inline CSS/JS from the largest pages after behavior tests exist.

### Large Refactors

- Introduce action endpoints while keeping existing public routes as wrappers.
- Rework Quran list aggregation to remove row-level query loops.
- Plan DB identity/constraint migration for mosque and Quran relationships.
- Establish a deployment-safe public/private file boundary.

### Risky Refactors to Delay

- Renaming route files or changing public URLs before wrappers and regression tests exist.
- Changing DB column types such as `construction_date` before data profiling.
- Redesigning the Bootstrap RTL UI while backend security/refactor work is in flight.
- Replacing native PHP architecture wholesale.

## 12. Regression Test Checklist

### Auth and Role Matrix

- Log in as admin with valid credentials.
- Log in as editor with valid credentials.
- Reject invalid login without exposing internals.
- Confirm direct access to protected pages redirects when logged out.
- Confirm admin-only add/edit/delete/import routes reject editor direct URL requests.
- Confirm corrected Quran create route accepts admin and rejects editor.
- Log out and confirm session-protected routes no longer render.

### Mosque Module

- List mosques with pagination.
- Search by name, national code, imam, and address/live search.
- Filter by community, status, Friday prayer, and guide imam.
- Open mosque detail modal.
- Add a mosque without image.
- Add a mosque with valid JPG/PNG image.
- Reject invalid image extension, MIME, and oversized file.
- Reject duplicate national code.
- Confirm one add request creates one row.
- Double-click submit and confirm one row after Phase 1 protections.
- Refresh after success redirect and confirm no extra row.
- Edit text fields and GPS coordinates.
- Replace image and remove image.
- Delete single mosque through protected POST flow.
- Bulk delete through protected POST flow.
- Verify Quran-linked mosque delete policy.

### Quran Module

- List Quran programs and verify totals.
- Search/filter Quran programs.
- Open Quran detail modal.
- Add Quran program with one responsible.
- Add Quran program with multiple responsibles.
- Edit linked mosque/program fields and responsible rows.
- Delete single and bulk Quran programs through protected POST flow.
- Verify rollback leaves no orphan responsible rows on failed save.

### Import, Export, Map

- Import valid workbook.
- Reject invalid file type and oversized input after hardening.
- Report skipped and duplicate rows clearly.
- Confirm failed import rolls back when policy requires rollback.
- Export all data and filtered data with intended role.
- Load map page, filters, marker detail links, and endpoint auth.

### Deployment and Security

- Verify no `.env`, real config secret, SQL dump, uploaded file batch, or backup file is staged unexpectedly.
- Verify production upload directory cannot execute PHP.
- Verify invalid CSRF requests fail safely on every write route.
- Verify user-facing errors are safe and developer logs capture internal detail.

## 13. Final Recommendation

Fix first:

1. Take a DB backup and preserve the copied Freehost baseline.
2. Fix the duplicate insert in `add_mosque.php`.
3. Define safe unique-identity migration rules for `national_code` before adding a unique index.
4. Fix high-risk security boundaries before structure cleanup: CSRF, GET deletes, Quran create authorization, unauthenticated AJAX/data mutation endpoints, error disclosure.

Do not touch yet:

- Do not rename or move public route files in the first fix phase.
- Do not redesign the RTL UI while duplicate and security fixes are being stabilized.
- Do not change the Quran FK or date column types until duplicates, backup, data quality, and regression tests are understood.

Back up before changes:

- Local database.
- Current uploaded images.
- The copied Freehost source snapshot.
- Current SQL dump if it is the only available restore source, then move it out of public/tracked production paths by plan.

Database migrations needed:

- Likely unique protection for mosque identity if `national_code` is confirmed reliable and existing duplicates/blanks are resolved.
- Optional uniqueness for one Quran program per mosque if that is a business invariant.
- Later targeted indexes after query measurement.
- Later semantic/type cleanup for Quran relation naming and `construction_date` only under migration plan.

Test after every phase:

- All state-changing POST paths in scope.
- Direct URL permission matrix.
- Main list/detail/map/import/export flows.
- Upload and relationship behavior touched by the phase.
