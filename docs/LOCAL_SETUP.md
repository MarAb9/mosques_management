# Local Setup Guide

## Requirements

- PHP 8.1 or newer
- Extensions: `pdo_mysql`, `mbstring`, `gd`, and `zip`
- Composer 2
- MariaDB/MySQL
- Docker Desktop (recommended)

## Docker

1. Clone the repository.
2. Copy `.env.example` to `.env` only when local port or credential
   overrides are needed.
3. Obtain the authorized SQL seed and place it at
   `ezyro_40059332_mosques_berkane.sql` in the project root. The dump is
   intentionally ignored by Git.
4. Build and start:

   ```powershell
   docker compose up --build -d
   ```

5. Open the services:

   | Service | Default address |
   | --- | --- |
   | Application | `http://localhost:8080` |
   | phpMyAdmin | `http://localhost:8081` |
   | MariaDB | `localhost:3307` |

The application container receives its database settings from
`compose.yaml`; no PHP credential file is required for Docker.

The seed runs only when the `db_data` volume is empty. Running
`docker compose down -v` deletes the local database and must be treated as a
deliberate reset.

## Native PHP / XAMPP / WAMP

1. Install dependencies:

   ```powershell
   composer install
   ```

2. Create the private database override:

   ```powershell
   Copy-Item config/database.local.php.example config/database.local.php
   ```

3. Edit `config/database.local.php` with the local host, port, user,
   password, and database name. The file is ignored by Git.
4. Import the authorized database dump with phpMyAdmin or the MySQL CLI.
5. Configure the web server document root to:

   ```text
   <project>/public
   ```

   Do not expose the repository root. The `app/`, `config/`, `resources/`,
   `scripts/`, `storage/`, and `tests/` directories are private.
6. Ensure `public/uploads/mosques/` is writable by the PHP/web-server user.

## Configuration precedence

Database values are resolved in this order:

1. Defaults in `config/database.php`
2. Environment variables `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`,
   and `DB_NAME`
3. Values present in the optional ignored `config/database.local.php`

The local override is intended for hosts that cannot provide environment
variables. Never commit it.

## Tests

Run the full regression set from the project root:

```powershell
docker compose exec -T app php tests/foundation_test.php
docker compose exec -T app php tests/guide_imams_test.php
docker compose exec -T app php tests/mosque_crud_http_test.php
docker compose exec -T app php tests/quran_http_test.php
docker compose exec -T app php tests/import_export_http_test.php
docker compose exec -T app bash tests/smoke_http.sh
```

The write-path tests create temporary database rows and uploaded files, then
remove them before exiting.

## Protected local data

Git ignores database credentials, SQL dumps, generated archives, logs, caches,
and the contents of `public/uploads/mosques/`. Only the directory placeholder
is tracked. Keep production dumps and uploaded personal data out of commits and
build artifacts.
