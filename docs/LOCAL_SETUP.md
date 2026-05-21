# Local Setup Guide

How to set up the Mosques Management application on your local machine.

## Prerequisites

- **PHP 8.0+** with extensions: `pdo_mysql`, `mbstring`, `gd`, `zip`
- **MySQL 5.7+ or MariaDB 10.4+**
- **Composer** (https://getcomposer.org)
- **Docker** (optional — simplifies setup)

## Option A: Docker Setup (Recommended)

Docker handles PHP, Apache, MariaDB, and phpMyAdmin automatically.

### 1. Clone the repository

```bash
git clone <repository-url>
cd mosques_management
```

### 2. Configure environment

```bash
cp .env.example .env
```

Edit `.env` if you need to change default ports or database credentials.
The defaults work out of the box for local development.

### 3. Create application config

```bash
cp includes/config.example.php includes/config.php
```

For Docker, the default values in `config.example.php` work because
the application reads credentials from environment variables set in
`compose.yaml`. You only need to ensure `config.php` exists.

### 4. Obtain a database dump

The SQL dump file is **not included in the repository** for security reasons.
Obtain `ezyro_40059332_mosques_berkane.sql` from a team member or your backup
location and place it in the project root. Docker Compose mounts this file
to seed the database automatically on first start.

### 5. Start the stack

```bash
docker compose up --build -d
```

### 6. Access the application

| Service      | URL                          |
|--------------|------------------------------|
| Application  | http://localhost:8080         |
| phpMyAdmin   | http://localhost:8081         |
| MariaDB      | localhost:3307 (for DB tools)|

Ports can be changed in your `.env` file.

### 7. Re-seed the database

The SQL dump is imported only when the `db_data` Docker volume is empty.
To re-import:

```bash
docker compose down -v
docker compose up --build -d
```

> **Warning:** `docker compose down -v` deletes all database data.

---

## Option B: Local PHP / XAMPP / WAMP / MAMP

### 1. Clone the repository

```bash
git clone <repository-url>
cd mosques_management
```

### 2. Install Composer dependencies

```bash
composer install
```

This creates the `vendor/` directory with required libraries
(e.g., PhpSpreadsheet for Excel import/export).

### 3. Create application config

```bash
cp includes/config.example.php includes/config.php
```

Edit `includes/config.php` and set your local database credentials:

```php
define('DB_HOST', appEnv('DB_HOST', '127.0.0.1'));
define('DB_PORT', appEnv('DB_PORT', '3306'));
define('DB_USER', appEnv('DB_USER', 'your_actual_db_user'));
define('DB_PASS', appEnv('DB_PASS', 'your_actual_db_password'));
define('DB_NAME', appEnv('DB_NAME', 'your_actual_db_name'));
```

### 4. Import the database

Obtain the SQL dump from a team member or your backup location, then import:

```bash
mysql -u root -p your_db_name < ezyro_40059332_mosques_berkane.sql
```

Or use phpMyAdmin to import the `.sql` file.

### 5. Configure your web server

Point your Apache/Nginx document root to the project root directory.
Ensure the `uploads/` directory is writable by the web server:

```bash
chmod -R 775 uploads/
```

On Windows (XAMPP/WAMP), the directory is typically writable by default.

### 6. Access the application

Open `http://localhost/` (or your configured virtual host URL).

---

## Uploads Directory

The `uploads/mosques/` directory stores mosque images uploaded through the
application. This directory is **not tracked by Git** — only `.gitkeep`
placeholder files are committed to preserve the folder structure.

Uploaded images remain on your local disk and are not affected by Git
operations.

---

## Avoiding Secret Leaks

The project `.gitignore` prevents accidental commits of:

- `.env` files (local environment config)
- `includes/config.php` (database credentials)
- `vendor/` (Composer dependencies)
- `*.sql` files (database dumps)
- `uploads/mosques/*` (uploaded images)
- Backup archives (`*.zip`, `*.rar`, `*.7z`)
- Log files and cache

**Never commit:**
- Real database passwords
- Production SQL dumps
- `.env` files with real credentials

If `git status` shows any of these files as untracked, that is correct —
they should stay untracked.
