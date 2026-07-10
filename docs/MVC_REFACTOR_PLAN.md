# MVC Refactor Plan

Working plan for migrating the application to a clean MVC architecture on branch
`refactor/mvc-architecture`, while preserving 100% of current behavior, URLs,
database schema, Arabic RTL UI, and shared-hosting deployment compatibility.

Companion documents:

- `docs/AUDIT_CODEBASE_REPORT.md` — full architecture/security/DB audit (Phase 1 deliverable).
- `docs/REFACTOR_CONTRACT_PLAN.md` — binding refactor principles (still apply).
- `docs/CHANGELOG.md` — per-phase change history.

## 1. Current State (baseline for this refactor)

Completed before this branch:

- Phase 1/1.1 — duplicate mosque insert fixed; `uq_mosques_national_code` unique key applied.
- Phase 2 — CSRF on write routes, POST-only deletes, session hardening, auth on all AJAX endpoints, safe error messages.
- Phase 3 — `.gitignore`, `config.example.php`, secrets untracked, setup/deployment docs.
- Phase 4.1 — shared helpers extracted (`includes/db.php`, `flash.php`, `redirect.php`, `helpers.php`).
- Guide imams feature — `guide_imams` table + `mosques.guide_imam_id` (see `database/migrations/`), Word export via PhpWord.

Verification baseline (all green at branch start):

- `php test_phase4.php` — 30/30 helper/bootstrap checks (run in app container).
- `php test_integration.php` — DB + function availability checks.
- `php test_guide_imams.php` — 9/9 normalization/data checks.
- `test_http.sh` (adapted to `localhost:8085`) — login, all pages 200, logout, auth redirect 302.

## 2. Route Inventory

Every public URL must keep working. "Target" names the controller action each URL
dispatches to once migrated; until then the legacy file serves it unchanged.

| URL | Methods | Key params | Access | CSRF | Target controller action |
| --- | --- | --- | --- | --- | --- |
| `login.php` | GET, POST | `username`, `password`, `login` | guest (redirects if logged in) | no (documented decision) | `Auth\LoginController@show/@login` |
| `logout.php` | GET | — | auth | no | `Auth\LoginController@logout` |
| `index.php` | GET | — | auth | — | `DashboardController@index` |
| `mosques.php` | GET | `page`, `sort`, `order`, `query`, `national_code`, `from_map`, `imam_registration`, `community`, `status`, `friday_prayer`, `guide_imam` | auth | — | `MosqueController@index` |
| `add_mosque.php` | GET, POST | mosque form fields, `main_image` file | admin (`canCreateMosque`) | POST yes | `MosqueController@create/@store` |
| `edit_mosque.php` | GET, POST | `id`, form fields, `main_image`, `remove_image` | admin (`canEditMosque`) | POST yes | `MosqueController@edit/@update` |
| `delete_mosque.php` | POST | `id` or `selected_mosques[]` | admin (`canDeleteMosque`) | yes | `MosqueController@destroy` |
| `bulk_delete_mosques.php` | POST | `selected_mosques[]` | admin | yes | `MosqueController@bulkDestroy` |
| `quran_mosques.php` | GET | filters, `page` | auth | — | `QuranProgramController@index` |
| `add_quran_mosque.php` | GET, POST | program + responsibles fields | admin | POST yes | `QuranProgramController@create/@store` |
| `edit_quran_mosque.php` | GET, POST | `id`, program + responsibles | admin (`canEditMosque`) | POST yes | `QuranProgramController@edit/@update` |
| `delete_quran_mosque.php` | POST | `id` or `selected_mosques[]` | admin | yes | `QuranProgramController@destroy` |
| `mosque_maps.php` | GET | `page` | auth | — | `MapController@index` |
| `import_export.php` | GET | — (page) | auth | — | `ImportExportController@index` |
| `import_export.php?export=1` | GET | filters, `no_location`, `group_by_guide`, `format=word` | auth | — | `ImportExportController@export` |
| `import_export.php` (import) | POST | `excel_file` upload | admin (`canImportData`) | yes | `ImportExportController@import` |
| `ajax/search_mosques.php` | GET | `search`, `page` | auth | — | `Ajax\MosqueAjaxController@search` |
| `ajax/get_mosque_details.php` | GET | `id` | auth | — | `Ajax\MosqueAjaxController@details` |
| `ajax/get_quran_mosque_details.php` | GET | `id` | auth | — | `Ajax\QuranAjaxController@details` |
| `ajax/get_mosques_for_map.php` | GET | — | auth | — | `Ajax\MapAjaxController@mosques` |
| `ajax/get_mosque_stats.php` | GET | — | auth | — | `Ajax\MosqueAjaxController@stats` |
| `fix_coordinates.php` | — | — | **remove from web root** | — | move to `scripts/` (maintenance, already-applied data fix) |

Static content served directly (URLs must not change): `assets/css/*`, `assets/js/*`,
`assets/images/*`, `uploads/mosques/*`.

## 3. Feature Inventory

| Feature | Legacy file(s) | Notes |
| --- | --- | --- |
| Authentication + session | `login.php`, `includes/auth.php`, `logout.php` | `password_verify`, session regeneration on login |
| Authorization / roles | `includes/auth_check.php` | roles `admin`/`editor`; helpers `canCreateMosque` etc. (admin-only writes). `canViewSensitiveData` references nonexistent role `user` — behavior preserved as-is |
| Dashboard statistics | `index.php` | aggregate counts, latest-5 table, Chart.js |
| Mosque list/filter/sort/pagination | `mosques.php` | allowlisted sort, LIKE filters, guide-imam id/name filter |
| Mosque live search | `ajax/search_mosques.php`, `assets/js/mosque.js` | paginated JSON |
| Mosque details modal | `ajax/get_mosque_details.php` | includes Quran programs + responsibles |
| Mosque create/update | `add_mosque.php`, `edit_mosque.php`, `includes/mosque_functions.php` | shared form processing, GPS/phone/year validation |
| Image upload | `includes/mosque_functions.php` (`validateImageUpload`), add/edit pages | ext+MIME+getimagesize+2MB, `uniqid` names, `uploads/mosques/` |
| Mosque delete (single/bulk) | `delete_mosque.php`, `bulk_delete_mosques.php` | POST+CSRF+admin |
| Quran program list | `quran_mosques.php` | N+1 row queries (preserve results; may batch later) |
| Quran program create/edit/delete | `add_quran_mosque.php`, `edit_quran_mosque.php`, `delete_quran_mosque.php` | transactions over programs + responsibles |
| Map | `mosque_maps.php`, `ajax/get_mosques_for_map.php` | Leaflet, all-coordinates payload |
| Excel import | `import_export.php` | PhpSpreadsheet, transaction, duplicate check by national_code |
| Excel export | `import_export.php?export=1` | filterable spreadsheet |
| Word export | `import_export.php?export=1&format=word` | PhpWord, RTL, grouped by guide imam |
| Guide imams | `guide_imams` table, dropdowns, `normalizeArabic()` | id-or-name filter compatibility |
| Flash messages | `includes/flash.php` | `$_SESSION['success'|'error']` keys preserved |
| CSRF | `includes/csrf.php` | token in session, `csrf_field()`, meta tag |
| Shared layout | `includes/header.php`, `includes/footer.php` | Bootstrap RTL, CDN assets, navbar with role-based links |

## 4. Database Dependency Map

Schema is frozen (no renames, no type changes). Live dev DB = baseline dump + `database/migrations/001..002`.

| Table | Read by (legacy) | Written by (legacy) | Target repository |
| --- | --- | --- | --- |
| `mosques` | index, mosques, mosque_maps, import_export, ajax/* , edit/add forms | add_mosque, edit_mosque, delete_mosque, bulk_delete_mosques, import_export, fix_coordinates | `MosqueRepository` |
| `guide_imams` | add/edit forms (dropdown), mosques, import_export, ajax/search, ajax/details, index, mosque_maps | (managed manually / future feature) | `GuideImamRepository` |
| `quran_memorization_programs` | quran_mosques, ajax/get_mosque_details, ajax/get_quran_mosque_details | add/edit/delete_quran_mosque | `QuranProgramRepository` |
| `quran_program_responsibles` | quran_mosques, ajax details | add/edit/delete_quran_mosque (transactional) | `QuranProgramRepository` |
| `users` | includes/auth.php | (user management not implemented in UI) | `UserRepository` |

## 5. Target Structure

```text
app/
  Controllers/            # thin: request -> validate -> service -> view/json
    Ajax/
  Models/                 # plain domain objects / row wrappers (introduced as needed)
  Services/               # AuthService, MosqueService, UploadService, ImportService,
                          # ExportService, QuranProgramService, DashboardService, ...
  Repositories/           # all PDO access, prepared statements only
  Middleware/             # Authenticate, Guest, RequireRole, VerifyCsrf, SessionSecurity
  Validators/             # MosqueValidator, QuranProgramValidator, ImportFileValidator
  Helpers/                # moved function helpers (e(), normalizeArabic(), ...)
  DTO/                    # form-data objects where useful
  Exceptions/             # HttpException, ValidationException, ...
  Core/                   # Router, Request, Response, View, Session, Database,
                          # Config, ErrorHandler, Controller (base)
bootstrap/app.php         # builds config + container, registers error handler
config/                   # app.php, database.php, session.php, uploads.php, paths.php
routes/web.php            # single route table (legacy filename -> controller action)
database/migrations/      # applied schema-change records
public/                   # final web root: index.php front controller + legacy-URL
                          # shims + assets/ + uploads/   (flip happens LAST)
resources/views/          # layouts/, components/, auth/, mosques/, quran/,
                          # dashboard/, import_export/, maps/, errors/
storage/logs/             # error log target (gitignored)
scripts/                  # fix_coordinates.php and future maintenance scripts
tests/                    # existing smoke scripts moved here + new ones
includes/                 # legacy compatibility layer, shrinks over time
```

## 6. Migration Strategy (behavior-preserving)

Key rules:

1. **Legacy URLs are physical files, and stay physical files.** Each migrated page's
   root file becomes a 3-line shim: `require bootstrap/app.php` + dispatch by script
   name through the router. No mod_rewrite dependency, works on any host.
2. **Docroot flip to `public/` is the LAST structural step.** Until then the repo root
   remains the web root and `assets/`/`uploads/` stay put. The flip moves them into
   `public/` together with the shims, and updates the Dockerfile; URLs are unchanged
   because the relative paths are identical under the new docroot.
3. **One module per commit**, tests run after each: CLI checks + HTTP smoke script
   (login, page 200s, POST flows, logout, guest redirect).
4. **Repositories return exactly what the legacy SQL returned** (same columns, same
   ordering, same LIMIT math). SQL strings are moved, not rewritten, except for
   provable duplicates.
5. **Views are moved, not redesigned.** Arabic text, DOM IDs, classes, and inline JS
   hooks stay byte-compatible wherever possible (JS in `assets/js/*` depends on them).
6. Shared hosting without `.env`: config layer reads env vars with the same
   defaults/fallback chain as today (`appEnv()` semantics), and `includes/config.php`
   keeps working during migration.

Module order (each = one commit, app runnable after each):

1. Foundation (Core classes, bootstrap, config, routes, error handler) — no behavior change.
2. Authentication (login/logout + Authenticate/Guest middleware + auth view).
3. Shared layout (header/footer -> layout view; pages still legacy).
4. Mosque list + search AJAX (controller/service/repository/views).
5. Mosque create/edit/delete + upload service.
6. Dashboard.
7. Import/export (Excel + Word) services.
8. Quran module (list, create, edit, delete, details AJAX).
9. Maps (page + AJAX).
10. Remaining utilities, `fix_coordinates.php` -> `scripts/`.
11. Docroot flip to `public/` (assets/uploads move + shims + Dockerfile + deploy docs).
12. Cleanup: remove emptied legacy includes, update README/setup/deployment docs,
    final compatibility notes.

## 7. Risk Register

| Risk | Mitigation |
| --- | --- |
| Breaking AJAX URLs used by `assets/js/mosque.js` | AJAX endpoints stay physical files (shims); JSON shape asserted before/after |
| Upload path breakage on docroot flip | `config/uploads.php` absolute path from one source of truth; flip commit tested with upload/replace/remove |
| Session/CSRF regressions | Session bootstrap centralized in one middleware with identical cookie params; CSRF token key unchanged (`$_SESSION['csrf_token']`) |
| Shared-hosting incompatibility (no rewrite, no .env) | physical shims, env fallbacks, `public/` contents deployable flat into `htdocs` |
| Legacy include side effects double-run | `includes/config.php` becomes a delegating wrapper to the new bootstrap once modules migrate |
| View drift breaking JS selectors | views moved verbatim; HTTP smoke diffs page sizes/markers per module |
