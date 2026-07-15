# Security Policy

## Supported Versions

Only the current production release is supported. Older deployments must be
upgraded before a security fix can be guaranteed.


## Application Security Baseline

The app is expected to run with `public/` as the only document root. Keep
configuration, controllers, repositories, services, views, tests, scripts, SQL
dumps, and Composer dependencies outside the web root.

Baseline controls implemented in the codebase include CSRF protection for
state-changing requests, POST-only destructive actions, session cookie
hardening, login throttling, output escaping, upload MIME/extension validation,
private upload execution blocking, a nonce-based Content Security Policy,
self-hosted frontend vendor assets, security response headers, and audit logs
for important administrative mutations.

Production still needs environment-specific controls outside this repository:
unique strong credentials, HTTPS, regular backups, least-privilege database
users, central log retention, and an independent security review before public
launch.

## Reporting a Vulnerability

Do not open a public issue for a suspected vulnerability. Report it privately
to the project owner through the organization's approved internal security
channel. Include the affected URL, reproduction steps, impact, and any proof
of concept without including real citizen or employee data.

The receiving team should acknowledge a report within two working days,
provide a triage status within five working days, and coordinate disclosure
only after a fix has been deployed. Production credentials and personal data
must never be attached to a report.
