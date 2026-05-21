# Mosques Management

## Docker local setup

The local stack runs the PHP app with Apache and a MariaDB database seeded from
`ezyro_40059332_mosques_berkane.sql`.

1. Optionally copy `.env.example` to `.env` and change the local ports or
   database passwords.
2. Start the stack:

   ```powershell
   docker compose up --build -d
   ```

3. Open the app at `http://localhost:8080` unless `APP_PORT` was changed.

MariaDB is exposed on host port `3307` by default for local database tools.
The app connects to the database container through the internal Docker host
name `db`.

The SQL dump is imported only when the `db_data` volume is empty. To re-import
the dump, remove the stack and its volumes with `docker compose down -v`, then
start it again.
