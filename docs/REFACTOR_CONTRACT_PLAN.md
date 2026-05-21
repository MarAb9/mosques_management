# Safe Refactor Contract Plan

## Contract Status

This document is the working contract for refactoring the native PHP mosque management system after the audit. It applies to production-style refactor work on the copied local system. The work is not a rewrite and must keep the application functional after each phase.

## 1. Refactor Principles

The following rules are binding for every phase:

1. Do not rewrite the application from zero.
2. Work one phase at a time.
3. Keep one logical commit per change.
4. Keep existing UI, routes, forms, Arabic RTL behavior, database behavior, and role behavior stable unless the phase explicitly changes them.
5. Keep the database stable unless a migration is planned, backed up, reviewed, and tested.
6. Test every POST action touched by a phase.
7. Test every security fix, including rejected requests.
8. Do not refactor and redesign in the same change.
9. Do not move files and change behavior in the same commit unless it is unavoidable and documented.
10. Keep public route compatibility while extracting internals.
11. Do not delete production data or files as a cleanup shortcut.
12. Never expose real credentials, password hashes, or private operational data in documentation or commits.

## 2. Branch Strategy

| Branch | Contract role |
| --- | --- |
| `main` | Stable branch. Only validated work reaches it. |
| `backup/freehost-live` | Immutable branch representing the copied Freehost baseline. |
| `refactor-safe` | Integration branch for approved safe phases. |
| `feature/fix-duplicate-mosque` | Phase 1 duplicate prevention work. |
| `feature/security-csrf` | CSRF and minimum security foundation. |
| `feature/structure-cleanup` | Helper extraction and planned structure moves. |
| `feature/upload-hardening` | Upload storage and validation hardening. |
| `feature/docs` | Documentation work. |

Branch rules:

- Do not develop directly on `main`.
- Create a feature branch from the currently accepted stable refactor base.
- Merge only after phase acceptance criteria pass.
- Keep DB migrations in branches that state the DB effect in commit and release notes.

## 3. Commit Rules

Commit format must describe the actual logical change:

- `audit: add codebase audit report`
- `fix: prevent duplicate mosque creation`
- `security: add CSRF validation to mosque forms`
- `refactor: extract mosque create logic`
- `docs: add local setup guide`
- `db: add unique index for mosque national code`

Commit discipline:

- One commit should have one primary reason to exist.
- A behavior fix commit should not also move unrelated files.
- A DB migration commit must include migration notes, backup prerequisite, and rollback notes.
- A security commit must include the negative test it enforces.
- Do not commit real `.env` files, production dumps, uploads batches, local logs, or secret-bearing config.

## 4. Phase 0 - Baseline Backup

### Goal

Protect the current system before behavior changes.

### Scope

- Confirm Git baseline and branch setup.
- Confirm local database restore path.
- Confirm copied Freehost version remains untouched.
- Establish artifact and secret policy.
- Confirm local app can run.

### Tasks

- Confirm Git status before refactor work.
- Create or verify `backup/freehost-live`.
- Confirm a local DB backup exists outside public deployment paths.
- Confirm upload backup exists if image loss would be unacceptable.
- Confirm `.gitignore` protects real config, `.env`, uploads, vendor, dumps, backups, and logs.
- Confirm `config.example.php` exists or is planned before real config cleanup.
- Confirm local Docker or local PHP/MySQL run path works.

### Acceptance Criteria

- App runs locally.
- Stable and backup branches exist.
- DB backup exists and restore ownership is clear.
- No secrets are staged.
- No SQL dump is staged for a production refactor commit unless an explicitly approved sanitized seed is in scope.

### Rollback

- Stop work.
- Return to the preserved source branch.
- Restore database and uploads from baseline backups if any setup step changed them.

### Testing Checklist

- Login page loads.
- Admin login works locally.
- Mosque list loads.
- Database seed/restore path is documented enough to repeat.

## 5. Phase 1 - Fix Duplicate Mosque Creation

### Goal

Fix the duplicate insert bug before structure refactor.

### Scope

Only duplicate prevention and directly required tests/documentation are in scope.

### Tasks

- Inspect `add_mosque.php` and related `assets/js/mosque_form.js`.
- Remove the duplicate mosque insert execution path.
- Confirm only one normal form submission path exists.
- Preserve POST Redirect GET success behavior.
- Keep server-side duplicate feedback.
- Add safe duplicate protection at DB level only after backup and data checks.
- Clean existing duplicate rows only after separate approval, backup, identity decision, and relation impact review.

### Acceptance Criteria

- Adding one mosque creates exactly one row.
- Refreshing after success does not create another row.
- Double-clicking submit does not create duplicates once server/DB protections are in place.
- Existing valid add flow still works.
- Existing validation and image upload behavior still works.
- Duplicate national-code error handling is user-safe.
- Commit created with focused scope.

### Rollback

- Revert the focused code commit.
- Restore DB backup if a migration or duplicate cleanup was applied.

### Testing Checklist

- Add mosque without image.
- Add mosque with valid image.
- Duplicate national code request.
- Success refresh.
- Repeated submit.
- Admin permission direct URL test.

## 6. Phase 2 - Security Foundation

### Goal

Add minimum security protection without changing the UI design.

### Scope

Route protections, session/login baseline, safe error responses, and direct authorization defects.

### Tasks

- Centralize CSRF helper behavior.
- Add CSRF tokens to state-changing forms.
- Validate CSRF on state-changing POST routes.
- Convert delete writes away from GET.
- Fix direct authorization defects, including Quran create route policy.
- Harden session cookie settings compatible with deployment.
- Regenerate session ID after login.
- Mask production-facing exception details.
- Confirm auth checks on protected pages and AJAX endpoints.
- Confirm admin-only actions are blocked server-side.
- Decide and document login throttling/account-lockout strategy.

### Acceptance Criteria

- Every state-changing POST in scope has CSRF validation.
- Invalid CSRF requests fail with 403 or a safe equivalent.
- Delete links no longer mutate data by GET.
- Login/logout still work.
- Admin/editor permission matrix still works and documented defects are fixed.
- Protected AJAX endpoint policy is explicit and tested.
- No visual redesign is introduced.

### Rollback

- Revert focused security commits in reverse order.
- Restore route behavior only if rollback is approved and release notes flag the security regression.

### Testing Checklist

- Valid/invalid CSRF on add/edit/delete/import/Quran writes.
- Direct URL access logged out/admin/editor.
- Login success/failure/logout.
- AJAX endpoints with and without session as policy requires.

## 7. Phase 3 - Config and Environment Cleanup

### Goal

Remove hardcoded or tracked secret risk from application configuration.

### Scope

Config files, environment examples, ignore rules, deployment documentation.

### Tasks

- Create `includes/config.example.php`.
- Keep real `includes/config.php` protected by Git policy if it can carry deployment secrets.
- Optionally introduce `.env` loading only if it remains Freehost-compatible.
- Document local config and Freehost config.
- Ensure Git does not track secrets, local dumps, uploaded file batches, and generated dependency trees by default.

### Acceptance Criteria

- Fresh clone can be configured from examples and docs.
- Real credentials are not committed.
- App connects to local DB.
- Freehost deployment remains possible.
- Secret handling is documented without real secret values.

### Rollback

- Revert config bootstrap change.
- Restore prior config from secure local backup, not from a public document.

### Testing Checklist

- Local DB connection.
- Login route.
- Config missing/failure message is safe.
- Freehost-compatible include paths are reviewed.

## 8. Phase 4 - Extract Shared Helpers

### Goal

Reduce duplicated code safely before large module cleanup.

### Scope

Shared bootstrap/helpers only. Public pages remain available.

### Tasks

- Extract DB connection from config bootstrap.
- Extract flash message helper.
- Extract redirect helper.
- Extract output/sanitization helper with clear input-vs-output rules.
- Extract validation helper.
- Extract upload helper.
- Extract auth and permission helper policy.
- Keep old behavior unchanged while callers migrate gradually.

### Acceptance Criteria

- No public route is broken.
- Existing pages render.
- Existing forms submit.
- Existing role checks remain effective.
- Manual regression test for touched routes passes.

### Rollback

- Revert helper extraction commit by commit.
- Avoid partial include path rewrites during rollback.

### Testing Checklist

- Header/footer include path checks.
- Forms touched by helper migration.
- Direct role checks.
- Safe output checks for fields touched by helper changes.

## 9. Phase 5 - Mosque Module Refactor

### Goal

Clean mosque CRUD without changing approved behavior.

### Scope

Mosque list/create/update/delete internals, validation, upload coordination, duplicate protection.

### Tasks

- Separate mosque create/update/delete logic from page templates.
- Move database logic into `includes/mosque_functions.php` or a service file agreed for the phase.
- Keep UI pages mostly as views and compatibility routes.
- Add clear validation boundaries.
- Keep duplicate protection in service and DB paths.
- Add transaction or compensating cleanup where multi-step behavior needs it.
- Keep image upload behavior working.
- Define Quran-linked mosque delete policy.

### Acceptance Criteria

- Mosque list works.
- Add works.
- Edit works.
- Delete behavior matches approved policy.
- Image upload/replace/remove works.
- Permissions work.
- Duplicate prevention works.
- Public route URLs remain usable.

### Rollback

- Revert extracted action/service commits while keeping DB migration rollback separately controlled.

### Testing Checklist

- Full mosque checklist from audit report.
- Upload file lifecycle.
- Search/filter/modal detail path.
- Relationship behavior on delete.

## 10. Phase 6 - Quran Module Refactor

### Goal

Clean Quran mosque/program logic safely.

### Scope

Quran list/create/update/delete internals and relationship consistency.

### Tasks

- Identify all Quran module files and route dependencies.
- Separate form display from POST processing.
- Validate related mosque references.
- Keep transaction for program plus responsible rows.
- Protect delete/update actions.
- Improve error handling.
- Remove list-page N+1 query behavior where safe.
- Decide whether one program per mosque is a DB invariant.

### Acceptance Criteria

- Add/edit/delete Quran program works.
- Linked data remains consistent.
- No orphan responsible records.
- Permissions are enforced.
- List/detail totals remain correct.

### Rollback

- Revert code changes in order.
- Roll back Quran-specific DB constraints only under backed-up migration plan.

### Testing Checklist

- Add one and multiple responsible rows.
- Edit and remove responsible rows.
- Transaction failure behavior.
- Program detail modal.
- Admin/editor direct route policy.

## 11. Phase 7 - Import/Export Hardening

### Goal

Make import/export safer and clearer.

### Scope

Excel file validation, mapping, duplicate policy, transactions, authorization, operator feedback.

### Tasks

- Validate import file type and size server-side.
- Validate workbook headers and required fields.
- Use clear transaction and rollback policy.
- Handle duplicates clearly.
- Add import preview only as a later optional enhancement if scope allows.
- Improve import error report.
- Restrict import/export according to approved role/data policy.
- Review export content for sensitive fields and spreadsheet formula injection risk.

### Acceptance Criteria

- Valid import works.
- Invalid import fails safely.
- Duplicate rows are handled clearly.
- Export works for intended roles.
- Unauthorized users are blocked.
- Import errors are actionable without exposing internals.

### Rollback

- Revert importer/exporter changes.
- Restore DB backup if import test data polluted the test database.

### Testing Checklist

- Valid workbook.
- Wrong extension/MIME/oversized file.
- Missing required columns.
- Duplicate national code rows.
- Export all and filtered data.

## 12. Phase 8 - Frontend and Assets Cleanup

### Goal

Clean CSS/JS safely without changing workflows unexpectedly.

### Scope

Asset organization, duplicate handlers, inline script/style extraction, browser regression checks.

### Tasks

- Identify unused JS/CSS before removal.
- Remove only proven unused code.
- Move inline scripts gradually.
- Fix duplicate submit/listener behavior.
- Keep Bootstrap RTL stable.
- Keep Arabic UI text unchanged unless separately approved.
- Keep map scripts and CDN-dependent behavior working.
- Replace unsafe DOM string rendering patterns in touched paths.

### Acceptance Criteria

- No known console errors on tested pages.
- Layout is unchanged or explicitly approved.
- Forms still work.
- Map still works.
- No missing assets.
- Stored/imported content renders safely in touched views.

### Rollback

- Revert asset extraction commits one behavior group at a time.

### Testing Checklist

- Desktop and mobile smoke check.
- Add/edit mosque form hierarchy selects.
- Mosque live search and modal.
- Quran modal.
- Map markers and filters.

## 13. Phase 9 - Project Structure Cleanup

### Goal

Improve folder structure without breaking public behavior.

### Target Structure

```text
assets/
  css/
  js/
  images/

includes/
  config.php
  config.example.php
  db.php
  auth.php
  auth_check.php
  csrf.php
  helpers.php
  flash.php
  upload.php
  mosque_functions.php

actions/
  mosque_create.php
  mosque_update.php
  mosque_delete.php
  quran_create.php
  quran_update.php
  quran_delete.php

ajax/
  get_mosques_for_map.php
  search_mosques.php

docs/
  AUDIT_CODEBASE_REPORT.md
  REFACTOR_CONTRACT_PLAN.md
  LOCAL_SETUP.md
  DEPLOYMENT_FREEHOST.md
  DATABASE.md

uploads/
  .htaccess
  .gitkeep
```

### Rules

- Do not move public route files all at once.
- Keep compatibility wrappers when routes move internally.
- Move logic first, then move routes later.
- Update includes carefully.
- Test after each file move.

### Acceptance Criteria

- All routes still work.
- No broken includes.
- No broken assets.
- No broken uploads.
- No broken map.
- No broken import/export.

### Rollback

- Revert file-move commits in reverse order.
- Keep wrappers until all links/tests prove route compatibility.

### Testing Checklist

- Route link crawl by manual main navigation.
- Include path smoke checks.
- Upload path and image display checks.
- AJAX URL checks.

## 14. Phase 10 - Documentation

### Goal

Make the project understandable and maintainable.

### Deliverables

- `README.md`
- `docs/LOCAL_SETUP.md`
- `docs/DATABASE.md`
- `docs/DEPLOYMENT_FREEHOST.md`
- `docs/SECURITY_CHECKLIST.md`
- `docs/TESTING_CHECKLIST.md`
- `docs/CHANGELOG.md`

### Acceptance Criteria

- A new developer can install the project locally.
- DB import steps are clear.
- Config steps are clear.
- Freehost deployment steps are clear.
- Testing checklist is clear.
- Documentation does not expose secrets or private data.

### Rollback

- Documentation rollback is normal Git revert unless docs are tied to released migration instructions.

### Testing Checklist

- Follow local setup docs on a clean local copy.
- Verify file names and commands against current repo.

## 15. Phase 11 - Final Production Readiness

### Goal

Prepare a stable version for deployment.

### Tasks

- Run full manual regression test.
- Check Git status.
- Check no secrets committed.
- Check no SQL dump committed unintentionally.
- Check no debug code.
- Check no production `display_errors` leakage.
- Check file permissions.
- Check upload protections.
- Check DB backup.
- Prepare release notes.

### Acceptance Criteria

- App works locally.
- Planned DB migrations are complete and documented.
- Main flows tested.
- Security checklist passed.
- Deployment steps and rollback steps are ready for manual Freehost release.

### Rollback

- Restore deployed source from last accepted release.
- Restore DB only when the release contained a DB migration or data cleanup that must be undone.
- Restore uploads only when release work changed upload contents or storage policy.

### Final Testing Checklist

- Auth/logout and direct URL role matrix.
- Dashboard and navigation.
- Mosque list, add, edit, delete, details, search, filters, image handling.
- Quran list, add, edit, delete, details, relationship integrity.
- Map page and endpoint auth.
- Import/export valid and invalid paths.
- CSRF rejection paths.
- Error/logging safety.
- Git/artifact/secret check.

