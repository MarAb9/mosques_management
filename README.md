# Mosques Management

Arabic RTL mosque-management application built with native PHP 8.1+ and a
small MVC kernel. The web server exposes only `public/`; controllers,
services, repositories, configuration, views, tests, and maintenance scripts
remain outside the document root.

## Docker setup

1. Optionally copy `.env.example` to `.env` and adjust local ports or
   database credentials.
2. Place the authorized database seed dump at
   `ezyro_40059332_mosques_berkane.sql`.
3. Start the stack:

   ```powershell
   docker compose up --build -d
   ```

4. Open `http://localhost:8080` unless `APP_PORT` was changed.

Apache serves `public/`. MariaDB is exposed on host port `3307` by default
for local database tools, and phpMyAdmin is available on port `8081`.

The SQL dump is imported only when the `db_data` volume is empty. Do not
remove that volume unless discarding the local database is intentional.

## Local PHP setup

Install Composer dependencies, copy
`config/database.local.php.example` to `config/database.local.php`, and put
the local database credentials in that ignored file. Configure Apache or Nginx
with this project's `public/` directory as the document root and ensure
`public/uploads/mosques/` is writable by PHP.

See `docs/LOCAL_SETUP.md` for detailed setup and
`docs/DEPLOYMENT_FREEHOST.md` for production/shared-hosting layout guidance.

## Verification

With Docker running:

```powershell
docker compose exec -T app php tests/foundation_test.php
docker compose exec -T app php tests/guide_imams_test.php
docker compose exec -T app php tests/mosque_crud_http_test.php
docker compose exec -T app php tests/quran_http_test.php
docker compose exec -T app php tests/import_export_http_test.php
docker compose exec -T app bash tests/smoke_http.sh
```

Maintenance utilities are CLI-only:

```powershell
docker compose exec -T app php scripts/audit_schema.php
```
