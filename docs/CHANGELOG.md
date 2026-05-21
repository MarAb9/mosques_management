# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

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
