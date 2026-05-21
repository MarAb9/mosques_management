# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Fixed

- **Duplicate mosque creation on single form submit** (`add_mosque.php`)
  - Root cause: The prepared `INSERT` statement was executed twice in sequence (lines 82 and 84 in the original file). A single POST request created two mosque rows before the success redirect.
  - Fix: Removed the duplicate `$stmt->execute()` call. One prepare, one execute, one row.
  - The existing national-code duplicate pre-check, image upload, validation, POST→Redirect→GET flow, and permission checks are unchanged.
  - Phase: 1 (from `docs/REFACTOR_CONTRACT_PLAN.md`)
  - Note: The database does not yet enforce `UNIQUE` on `national_code`. That protection is deferred to a later phase after data profiling and backup.
