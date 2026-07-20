# Shared-Hosting Deployment Guide

The application requires PHP 8.1+ and a public/private directory boundary.
Only the contents of `public/` may be web-accessible.

## Required PHP extensions

- `pdo_mysql`
- `mbstring`
- `gd`
- `zip`

Composer dependencies must be installed locally when the host does not provide
Composer:

```powershell
composer install --no-dev --optimize-autoloader
```

## Preferred layout

When the hosting control panel allows a custom document root, upload the whole
project and set the domain document root to:

```text
/path/to/mosques_management/public
```

The `app/`, `bootstrap/`, `config/`, `resources/`, `routes/`,
`storage/`, `vendor/`, and other private directories stay above it.

## Fixed `htdocs/` layout

For hosts where the public directory is permanently named `htdocs/`, use this
layout:

```text
account-root/
  app/
  bootstrap/
  config/
  resources/
  routes/
  storage/
  vendor/
  htdocs/              <- copy the contents of public/ here
    ajax/
    assets/
    uploads/
    index.php
    login.php
    mosques.php
    ...
```

The physical PHP shims preserve all existing URLs without requiring rewrite
rules. The included `.htaccess` supplies clean front-controller routing when
`mod_rewrite` is available and blocks directory listing. The upload-level
`.htaccess` denies script execution.

If the provider does not allow private application directories above
`htdocs/` and does not allow changing the document root, it cannot provide
the required source-code boundary and should not be used for this deployment.

## Database configuration

1. Create the MySQL/MariaDB database and import the authorized baseline dump.
2. Apply the SQL files under `database/migrations/` in numeric order when
   they are not already present in the baseline.
3. Copy:

   ```text
   config/database.local.php.example
   ```

   to:

   ```text
   config/database.local.php
   ```

4. Replace every placeholder with the hosting-panel host, port, username,
   password, and database name.

`database.local.php` is ignored by Git and must remain private. Environment
variables `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, and `DB_NAME`
can be used instead when the host supports them.

The application derives the writable upload path from the server document root.
Set `PUBLIC_PATH` only if the host reports an incorrect `DOCUMENT_ROOT`.

## Writable directories

The web-server/PHP user needs write permission for:

- `htdocs/uploads/mosques/` (or `public/uploads/mosques/`)
- `storage/logs/`
- `storage/cache/` when application caching is enabled

Preserve both supplied `.htaccess` files when uploading, including hidden
files in the FTP client.

## Do not upload into the public directory

- `.env` or `config/database.local.php`
- `.git/`
- SQL dumps or backups
- `app/`, `bootstrap/`, `config/`, `resources/`, `routes/`,
  `scripts/`, `storage/`, `tests/`, or `vendor/`
- Docker files and internal documentation

`vendor/` is required by PHP but belongs in the private application root,
not inside `htdocs/`.

## ArcGIS map configuration

Set `ARCGIS_ACCESS_TOKEN` to an ArcGIS API-key credential restricted to the production web origins and the static-basemap-tiles privilege. This key is intentionally browser-visible because the browser retrieves raster tiles directly.

Set `ARCGIS_ROUTING_TOKEN` separately and grant only the network-analysis privilege. It is server-only: the browser calls the authenticated, CSRF-protected `ajax/map_route.php` endpoint, and the application forwards the route request to ArcGIS. Never reuse or expose this token in HTML or client JavaScript.

The optional `ARCGIS_STREET_TILE_URL`, `ARCGIS_SATELLITE_TILE_URL`, `ARCGIS_SATELLITE_LABELS_URL`, and `ARCGIS_ROUTING_URL` values have official ArcGIS defaults in `.env.example`. Production must use HTTPS and allow outbound access to the configured ArcGIS hosts.

After configuration, verify street, satellite, and hybrid modes; zoom level 22 where the provider has coverage; clickable Esri attribution; geolocation permission handling; driving and walking routes; and the add/edit coordinate picker.

## Post-deployment checks

1. Open `login.php` and sign in.
2. Verify the dashboard, mosque list, add/edit forms, Quran list,
   import/export page, and map page.
3. Verify `ajax/get_mosque_stats.php` redirects guests and returns JSON after
   login.
4. Verify a real image under `uploads/mosques/` loads.
5. Request a fake `uploads/test.php`; the server must deny it.
6. Confirm `/app/`, `/config/`, `/scripts/`, and `/vendor/` are not
   reachable through the domain.

## Troubleshooting

| Problem | Check |
| --- | --- |
| HTTP 500 on every page | PHP is 8.1+, `vendor/autoload.php` exists, and private directories are in the expected parent location |
| Database connection error | Values in `config/database.local.php` match the hosting panel |
| Images do not upload | The public `uploads/mosques/` directory exists and is writable |
| CSS/JavaScript missing | The complete `public/assets/` directory was copied |
| Legacy URL returns 404 | The corresponding physical shim from `public/` is present |
| Clean route returns 404 | Enable `mod_rewrite` and `AllowOverride`, or use the physical `.php` URL |
