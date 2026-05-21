# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

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
