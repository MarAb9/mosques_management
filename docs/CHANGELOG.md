# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## MVC Phases 11-12 - Public Boundary and Final Cleanup

### Changed

- Made `public/` the Apache document root and moved all physical legacy URL
  shims, AJAX shims, static assets, and upload storage beneath it without
  changing public URLs.
- Added root/upload access controls: disabled directory listing, enabled
  front-controller fallback, and denied script execution in uploaded content.
- MVC views now receive CSRF/role state through the base controller and escape
  dynamic output through `App\Core\View`; the kernel no longer loads global
  helper functions or exports PDO globally.
- Added ignored `config/database.local.php` overrides for shared hosts and
  corrected the minimum PHP requirement to 8.1.
- Upgraded PhpSpreadsheet from 4.3.1 to 5.8.1 to clear six published security
  advisories, including the critical user-controlled filename issue.
- Removed the persistent Composer vendor overlay that could keep stale,
  vulnerable packages active after an otherwise successful image rebuild;
  Docker now persists only uploads and application storage.
- Replaced local/deployment documentation with the final public/private
  directory layout and current test commands.

### Removed

- Removed the remaining tracked `includes/` compatibility files and the
  duplicate root `index.php` entry point.

## MVC Phase 10 - Utilities and Legacy Cleanup

### Changed

- Moved the schema-audit and coordinate-fix utilities from the public root to
  `scripts/`; both now boot through the MVC application and reject web access.
- Consolidated the HTTP smoke script under `tests/` and ported the guide-imam
  normalization/data checks to `App\Helpers\Arabic`.

### Removed

- Removed superseded legacy authentication, layout, and mosque form helper
  includes after confirming all runtime consumers use controllers, services,
  repositories, middleware, and MVC views.
- Removed obsolete pre-MVC test scripts replaced by the current focused test
  suite, plus the generated `clean.xlsx` artifact.

## Guide Imams Normalization + Word Export

### Added

- **`guide_imams` table** (migration `database/migrations/002_create_guide_imams.sql`, already applied) — reference table of guide imams with `display_name` and Arabic-normalized `display_name_normalized`. `mosques.guide_imam_id` links each mosque to it; the legacy free-text `mosques.guide_imam` column is kept and used as fallback via `COALESCE`.
- **`normalizeArabic()`** in `includes/mosque_functions.php` — strips diacritics, unifies Alef forms and Teh Marbuta for consistent Arabic matching/sorting.
- **Guide imam dropdown** on add/edit mosque forms (replaces free-text input).
- **Word export** (`import_export.php?export=1&format=word`) — PHPWord-generated RTL document listing mosques without GPS location, grouped by guide imam. New Composer dependency `phpoffice/phpword`.
- **Export filters** — `no_location=1` (mosques missing coordinates) and `group_by_guide=1` (order by guide imam).
- **`database/migrations/`** — records of schema changes applied on top of the baseline dump (001 unique national code, 002 guide imams).
- **Test scripts** — `test_phase4.php`, `test_integration.php`, `test_guide_imams.php` (CLI, run inside the app container), `test_http.sh` (HTTP smoke tests), `audit_schema.php` (schema inspection).

### Changed

- Mosque list, search AJAX, details AJAX, dashboard, map page, and Excel export now `LEFT JOIN guide_imams` and display `COALESCE(gi.display_name, m.guide_imam)`.
- Guide imam filters accept either a numeric `guide_imams.id` or a name string (normalized LIKE match) for backward compatibility with old URLs.

## Phase 4.1 - Extract Shared Helpers

### Added

- **`includes/db.php`** — extracted PDO connection logic from `config.php`. Defines DB constants and creates global `$pdo`. Safe error handling with `error_log()`.
- **`includes/flash.php`** — flash message helpers (`set_flash`, `get_flash`, `has_flash`, `clear_flash`, `flash_message`). Uses same `$_SESSION` keys as existing code.
- **`includes/redirect.php`** — redirect helpers (`redirect_to`, `redirect_with_flash`). Wraps existing `header(Location)+exit` pattern.
- **`includes/helpers.php`** — output and utility helpers (`e()`, `safe_trim()`, `selected()`, `checked()`). Complements domain-specific helpers in `mosque_functions.php`.

### Changed

- **`includes/config.php`** — now delegates DB connection to `db.php` and loads all helper files via `require_once`. Session bootstrap, `appEnv()`, `checkAuth()`, and CSRF loading remain in place. Full backward compatibility preserved.
- **`includes/config.example.php`** — updated to match new `config.php` structure.

### Not changed

- No public routes moved or renamed.
- No database schema changes.
- No UI redesign.
- No business logic changes.
- No Arabic RTL text changes.

## Phase 3 - Config and Environment Cleanup

### Added

- **Root `.gitignore`** — protects `.env`, `vendor/`, `uploads/mosques/*`, `*.sql`, backups, logs, cache, OS/IDE files from accidental commits.
- **`includes/config.example.php`** — safe config template with placeholder credentials. Developers copy this to `config.php` and fill in local values.
- **`docs/LOCAL_SETUP.md`** — full local setup guide covering Docker and local PHP/XAMPP paths, Composer install, DB import, and secret avoidance.
- **`docs/DEPLOYMENT_FREEHOST.md`** — Freehost deployment guide covering what to upload, DB configuration, upload handling, vendor management, and security hardening.
- **`uploads/.gitkeep`** and **`uploads/mosques/.gitkeep`** — directory placeholders so folder structure is preserved in fresh clones.

### Changed

- **`.env.example`** — added documentation header clarifying values are local Docker defaults only.

### Removed from Git tracking (files remain on disk)

- `vendor/` — 749 Composer dependency files (install via `composer install`).
- `uploads/mosques/*` — 228 uploaded mosque images (user-generated content).
- `ezyro_40059332_mosques_berkane.sql` — SQL dump containing credentials and personal data.
- `includes/config.php` — application config with database credentials.

### Not changed

- No files deleted from disk.
- No database schema changes.
- No project structure refactor.
- No business logic changes.
- No UI changes.

## Phase 2 - Security Foundation

- Added centralized CSRF helpers and protected the touched write forms/actions.
- Disabled GET-based mosque and Quran delete writes; list delete buttons now submit POST requests with CSRF tokens.
- Fixed Quran program create authorization so authorized admins can create and unauthorized users are blocked.
- Added authentication checks to map and mosque-stats AJAX endpoints.
- Hardened baseline session handling with HttpOnly/SameSite cookies, HTTPS-only secure cookies, and session ID regeneration after login.
- Reduced raw exception display in touched files and moved internal details to `error_log()`.

### Fixed

- **Duplicate mosque creation on single form submit** (`add_mosque.php`)
  - Root cause: The prepared `INSERT` statement was executed twice in sequence (lines 82 and 84 in the original file). A single POST request created two mosque rows before the success redirect.
  - Fix: Removed the duplicate `$stmt->execute()` call. One prepare, one execute, one row.
  - The existing national-code duplicate pre-check, image upload, validation, POST→Redirect→GET flow, and permission checks are unchanged.
  - Phase: 1 (from `docs/REFACTOR_CONTRACT_PLAN.md`)
  - Note: The database does not yet enforce `UNIQUE` on `national_code`. That protection is deferred to a later phase after data profiling and backup.

## Phase 1.1 - Database duplicate protection

- Replaced duplicate placeholder national codes for affected mosque records.
- Verified no duplicate `national_code` values remain.
- Added unique database key: `uq_mosques_national_code`.
- Confirmed Quran program references still match exactly one mosque.
