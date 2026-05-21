# Freehost Deployment Guide

How to deploy the Mosques Management application to Freehost (InfinityFree/ezyro)
shared hosting.

## What to Upload

Upload these files and directories to your Freehost `htdocs/` (or public root):

| Path | Required | Notes |
|------|----------|-------|
| `*.php` (root files) | Yes | All page controllers: `index.php`, `login.php`, `mosques.php`, etc. |
| `includes/` | Yes | `config.php`, `auth.php`, `auth_check.php`, `csrf.php`, `header.php`, `footer.php`, `mosque_functions.php` |
| `ajax/` | Yes | All AJAX endpoint files |
| `assets/` | Yes | CSS, JS, and static images |
| `uploads/` | Yes | Create the directory structure; upload existing images if migrating |
| `vendor/` | Yes | Composer dependencies (run `composer install --no-dev` locally first) |
| `composer.json` | Optional | Only needed if you can run Composer on the host |
| `composer.lock` | Optional | Only needed if you can run Composer on the host |

## What NOT to Upload

> **Warning:** Never upload these to a public hosting environment.

| Path / Pattern | Reason |
|----------------|--------|
| `.git/` | Repository history — exposes source and secrets |
| `.env`, `.env.*` | Environment secrets |
| `*.sql` | Database dumps with credentials and personal data |
| `backups/` | Backup archives |
| `docs/` | Internal project documentation |
| `docker/`, `Dockerfile`, `compose.yaml` | Local Docker config — not applicable |
| `.dockerignore` | Docker config |
| `.gitignore` | Git config |
| `README.md`, `SECURITY.md` | Project docs |
| `fix_coordinates.php` | Maintenance script — unauthenticated DB writes |

## Database Configuration

### 1. Create the database

Use the Freehost control panel (e.g., InfinityFree Vista Panel) to create a
MySQL database. Note the:

- **Database host** (e.g., `sql123.ezyro.com`)
- **Database name** (e.g., `ezyro_40059332_mosques_berkane`)
- **Database username**
- **Database password**

### 2. Import data

Use phpMyAdmin (available in the hosting control panel) to import your SQL
dump file. Upload the `.sql` file through the phpMyAdmin import tab.

### 3. Configure the application

Edit `includes/config.php` on the server and set the real credentials:

```php
define('DB_HOST', appEnv('DB_HOST', 'sql123.ezyro.com'));
define('DB_PORT', appEnv('DB_PORT', '3306'));
define('DB_USER', appEnv('DB_USER', 'ezyro_40059332'));
define('DB_PASS', appEnv('DB_PASS', 'your_real_password'));
define('DB_NAME', appEnv('DB_NAME', 'ezyro_40059332_mosques_berkane'));
```

> **Important:** Since Freehost does not support `.env` files or environment
> variables, you must set credentials directly in the `config.php` fallback
> values. Keep this file private — do not share or commit it.

## Handling Uploads

### Directory structure

Ensure the `uploads/mosques/` directory exists on the server and is writable:

```
htdocs/
  uploads/
    mosques/
```

Most shared hosts make the web directory writable by default. If image uploads
fail, check the directory permissions in the hosting file manager.

### Migrating existing images

If you have existing mosque images from a previous deployment, upload them
to `uploads/mosques/` using the hosting file manager or FTP.

## Protecting Sensitive Files

### SQL dumps

- **Never leave `.sql` files in the public web root.**
- If you must store a backup on the server, place it outside `htdocs/` or in
  a directory protected by `.htaccess`:

```apache
# backups/.htaccess
Order deny,allow
Deny from all
```

### Vendor directory

The `vendor/` directory must be accessible to PHP (it's loaded via
`require`), but its internal files should not be browseable. Most shared
hosts disable directory listings by default. If not, add to your root
`.htaccess`:

```apache
Options -Indexes
```

### Configuration files

- `includes/config.php` contains database credentials. PHP files are
  interpreted server-side and not served as text, so credentials are safe
  as long as:
  - PHP is running (the host has not misconfigured the server)
  - The file is not downloadable via direct URL tricks

### Upload execution guard

Add an `.htaccess` file in `uploads/` to prevent PHP execution in the
uploads directory:

```apache
# uploads/.htaccess
<FilesMatch "\.php$">
    Order deny,allow
    Deny from all
</FilesMatch>
```

## PHP Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP version | 8.0 |
| Extensions | `pdo_mysql`, `mbstring`, `gd`, `zip` |

Most Freehost/InfinityFree plans provide PHP 8.0+ with these extensions
enabled by default. Check the hosting control panel PHP settings if the
application shows errors.

## Composer Dependencies

Freehost typically does not support running Composer on the server. Instead:

1. Run `composer install --no-dev` on your local machine.
2. Upload the resulting `vendor/` directory to the server.

The main dependency is `phpoffice/phpspreadsheet` for Excel import/export.

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Database connection error | Verify host/user/pass/name in `includes/config.php` match the hosting panel values |
| Images not uploading | Check `uploads/mosques/` exists and is writable |
| Import/export not working | Ensure `vendor/` is uploaded with PhpSpreadsheet |
| Blank page / 500 error | Check PHP version is 8.0+; check `error_log` in hosting panel |
| Session errors | Ensure PHP sessions are enabled in hosting PHP settings |
