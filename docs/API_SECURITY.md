# Public API security and privacy

## Privacy boundary

The API repository uses one explicit SELECT list. It never uses `SELECT *` and does not join personnel or user tables.

Allowed output is limited to public mosque identity, address/community, administrative type, status, public program flags, construction year, validated coordinates and a validated public image URL.

The following are intentionally excluded and protected by a recursive regression deny-list:

- imam, preacher and muezzin names, phones and registration identifiers
- administrative attachments and internal notes
- user accounts, passwords, sessions and CSRF tokens
- deleted-row archives, backups and audit data
- database/server details, filesystem paths and stack traces

Personnel fields require a separate privacy decision and explicit approval before any future API version may expose them.

## Request and query controls

- Only GET, HEAD and OPTIONS routes exist.
- Query parameters are allow-listed, scalar, length-bounded and duplicate-checked.
- Page size is capped at 100.
- SQL values use prepared statements.
- Sort names are mapped through a fixed server-side column allow-list.
- Resource identifiers accept only 1-50 digits.
- Image paths must be a real file directly under the configured mosque upload directory; otherwise the public default image is returned.
- API failures are logged internally and always return generic JSON, even when `APP_DEBUG=true`.

Existing authenticated AJAX endpoints retain their `Authenticate` middleware and are not reused by this API.

## Authentication modes

`public` mode is intended for direct browser calls. It combines the limited public field set, exact-origin CORS and rate limiting.

`key` mode is intended for server-to-server calls. The raw API key is supplied only by the consumer backend. The server stores a `password_hash()` result and verifies it with `password_verify()`, which provides timing-safe password-hash verification.

Generate a deployment hash without committing the raw key:

```bash
php -r "echo password_hash('replace-with-a-random-key', PASSWORD_DEFAULT), PHP_EOL;"
```

An API key in JavaScript, HTML, a mobile bundle or a public repository is not secret. Use public mode for browser-to-API calls.

## CORS and preflight

`API_ALLOWED_ORIGINS` is a comma-separated exact allow-list. Approved origins receive their own origin value plus `Vary: Origin`; unknown origins receive no allow header. `Access-Control-Allow-Origin: *` is never used. Preflight permits only GET and OPTIONS headers needed by this API.

CORS is a browser control, not authentication. Server-side clients can call the API regardless of Origin and must use key mode when access restriction is required.

## Rate limiting and proxies

The limiter stores hashed-IP counters under `storage/cache/api-rate-limits`, outside the public document root, and uses an exclusive file lock for concurrent requests. Expired entries are reset and periodically removed.

The direct `REMOTE_ADDR` is used by default. Forwarded IP headers are not trusted. If a trusted reverse proxy is introduced, add an explicit trusted-proxy policy before deriving client IPs from proxy headers.

## Operations

- Keep `.env` private and leave `API_KEY_HASH` empty until a real hash is deployed.
- Use HTTPS; terminate TLS only at a trusted proxy.
- Give the web process write access only to required upload, log and cache directories.
- Monitor repeated `401`, `429` and safe `500` events without logging raw API keys.
- Rotate a compromised key by replacing its hash and updating only the authorized server-side consumer.
- Keep `APP_URL` canonical so response links and image URLs cannot be influenced by untrusted Host headers.

Run the API regression suite after every schema, upload-path, routing or privacy-field change.
