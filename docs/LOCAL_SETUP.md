# Local setup

This guide covers a local XAMPP installation on Windows for Tunisia PC Advisor.
The application is plain PHP with a MySQL/MariaDB database and has no Composer,
Node.js, npm or build step.

All commands below assume the project is in the default XAMPP location:

```text
C:\xampp\htdocs\tunisia-pc-advisor
```

If the project lives elsewhere, replace that path with your own. Commands shown
for `cmd.exe` can also be run from Git Bash with the paths written using forward
slashes, for example `/c/xampp/php/php.exe`.

## 1. Required PHP version

- **PHP 8.2 or newer** is required. The application was tested with XAMPP PHP
  8.3.31.
- Use the PHP binary and Apache module that belong to the same XAMPP
  installation. On a Windows machine with another PHP installation on `PATH`,
  run CLI commands through `C:\xampp\php\php.exe` explicitly.

Check the version:

```bash
C:\xampp\php\php.exe -v
```

## 2. Required PHP extensions

| Extension | Required | Used for |
|---|---|---|
| `PDO` | Yes | Database abstraction |
| `pdo_mysql` | Yes | MySQL/MariaDB driver |
| `mbstring` | Yes | UTF-8 text handling and validation |
| `json` | Yes | JSON encoding/decoding and stored score breakdowns |
| `openssl` | Yes | TLS support for HTTPS requests and MySQL connections |
| `curl` | Yes | The optional retailer research and link-checking tools |

Check them with:

```bash
C:\xampp\php\php.exe -m
```

If `pdo_mysql` is missing, open `C:\xampp\php\php.ini`, remove the leading
semicolon from the `extension=pdo_mysql` line and restart Apache. The web server
uses its own `php.ini`; enabling an extension only for the CLI is not enough.

## 3. Apache requirements

- Apache 2.4 or newer.
- The XAMPP PHP module enabled for Apache (PHP 8.2+).
- `AllowOverride All` for the project directory, so the included `.htaccess`
  files are applied.
- `mod_authz_core` enabled, because the access rules use `Require all denied`.
- The site's `DocumentRoot` must point at the project directory or at the
  directory that contains it when using a subfolder URL.

The included `.htaccess` files:

- disable directory listing with `Options -Indexes`;
- deny direct HTTP access to dotfiles such as `.env`;
- deny direct HTTP access to SQL, Markdown, log, JSON, lock and backup files;
- deny direct access to `config/`, `classes/`, `database/`, `docs/`,
  `includes/` and `tools/`.

No `mod_rewrite` rule is required for normal operation.

If you use a virtual host, point it at the project and allow overrides:

```apache
<VirtualHost *:80>
    ServerName tpa.local
    DocumentRoot "C:/xampp/htdocs/tunisia-pc-advisor"

    <Directory "C:/xampp/htdocs/tunisia-pc-advisor">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Replace the path with the actual location on your machine. Add
`127.0.0.1 tpa.local` to your hosts file only if you choose to use that
hostname, then restart Apache.

## 4. MySQL requirements

- MySQL 8.0+ or MariaDB 10.4+. The application was tested with MariaDB 10.4.32.
- InnoDB tables, because the schema uses foreign keys.
- `utf8mb4` character set and `utf8mb4_unicode_ci` collation.
- The default database name is `tunisia_pc_advisor`.

The connection uses PDO with `PDO::ERRMODE_EXCEPTION`, native prepared
statements and the `utf8mb4` charset. The application sets the session time zone
to `+01:00` (Africa/Tunis).

## 5. Create the local database

`database/schema.sql` creates the database if it does not exist, so you normally
do not need to create it separately. If you prefer to create it first, use
phpMyAdmin or the MySQL CLI:

```sql
CREATE DATABASE IF NOT EXISTS tunisia_pc_advisor
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

In phpMyAdmin, open `http://localhost/phpmyadmin`, choose **New**, enter
`tunisia_pc_advisor`, select `utf8mb4_unicode_ci` and create it.

## 6. Import `database/schema.sql`

### Using phpMyAdmin

1. Open `http://localhost/phpmyadmin`.
2. Select the `tunisia_pc_advisor` database (or create it as described above).
3. Open **Import**, choose `database/schema.sql` from the project and import it.

### Using the MySQL CLI from the project root

```bash
C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
```

If your MySQL root account has a password, add `-p` and enter it when prompted.
PowerShell does not support the `<` redirection operator in the same way; use
`cmd.exe`, Git Bash or phpMyAdmin.

**Warning:** `database/schema.sql` drops and recreates the application tables.
Do not run it against a database whose data you need. Back up first.

## 7. Import `database/seed.sql`

Import `database/seed.sql` after `database/schema.sql`. It loads the reference
data, the 170 product listings and the initial price-history rows.

Through phpMyAdmin, select `tunisia_pc_advisor`, open **Import** and choose
`database/seed.sql`.

Through the CLI:

```bash
C:\xampp\mysql\bin\mysql.exe -u root -D tunisia_pc_advisor < database\seed.sql
```

`database/seed_base.sql` is the stable reference-data source used by
`tools/build_seed.php`. It is not a separate installation step; the generated
`database/seed.sql` already contains that data.

Verify the import:

```bash
C:\xampp\mysql\bin\mysql.exe -u root -D tunisia_pc_advisor -e "SELECT COUNT(*) AS products FROM products; SELECT COUNT(*) AS laptops FROM products WHERE product_category='laptop'; SELECT COUNT(*) AS desktops FROM products WHERE product_category='desktop';"
```

Expected result: 170 products, 127 laptops and 43 desktops.

## 8. Copy `.env.example` to `.env`

From the project root:

```bash
copy .env.example .env
```

In PowerShell:

```powershell
Copy-Item .env.example .env
```

In Git Bash:

```bash
cp .env.example .env
```

Edit `.env`, not `.env.example`. The `.env` file is ignored by Git and is also
blocked from direct HTTP access. Keep `.env.example` free of real credentials.

If `.env` is missing, the application uses its built-in local XAMPP fallbacks
(`127.0.0.1:3306`, database `tunisia_pc_advisor`, user `root`, empty password).
Creating `.env` is still recommended so the configuration is explicit.

## 9. Configure the database credentials

The application reads these variables in `config/database.php` and
`config/app.php` through the lightweight loader in `bootstrap.php`:

| Variable | Purpose | Built-in fallback |
|---|---|---|
| `APP_ENV` | Local environment label | `local` |
| `APP_DEBUG` | Debug flag; keep `false` locally and on shared machines | `false` |
| `APP_URL` | Public base URL without a trailing slash; empty means auto-detect | Detected from the request |
| `DB_HOST` | MySQL/MariaDB host | `127.0.0.1` |
| `DB_PORT` | MySQL/MariaDB port | `3306` |
| `DB_NAME` | Database name | `tunisia_pc_advisor` |
| `DB_USER` | Database user | `root` |
| `DB_PASS` | Database password; may be empty for a default XAMPP root account | Empty |

Loader rules:

- Real process environment variables take precedence over `.env` values.
- Values may be enclosed in single or double quotes.
- Inline comments after a value are not supported; put comments on their own
  lines.
- Leading and trailing whitespace is trimmed.
- An empty value falls back to the default shown above.

For a standard XAMPP installation, the default root account has no password, so
`DB_USER=root` and `DB_PASS=` are enough. If you set a root password, put it in
`DB_PASS`. For anything beyond a local teaching machine, use a dedicated
database user instead of root:

```sql
CREATE USER 'tpa_app'@'localhost' IDENTIFIED BY 'replace-with-a-local-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON tunisia_pc_advisor.* TO 'tpa_app'@'localhost';
FLUSH PRIVILEGES;
```

Then set `DB_USER=tpa_app` and `DB_PASS=replace-with-a-local-password` in
`.env`. The schema itself should still be imported by an account with the
rights to create tables.

Do not commit `.env`, and do not paste a real password into an issue, a
screenshot or `.env.example`.

## 10. Start Apache and MySQL with XAMPP

1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Confirm that MySQL accepts a connection:

   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT VERSION();"
   ```

5. If Apache or MySQL fails to start, check the XAMPP logs and confirm that
   ports 80 and 3306 are not already in use.

Do not expose the local XAMPP installation to an untrusted network.

## 11. Open the application

With the project in `C:\xampp\htdocs\tunisia-pc-advisor`, open:

```text
http://localhost/tunisia-pc-advisor/
```

The base path is detected from the Apache document root. If the URL or asset
paths are wrong, set `APP_URL` in `.env` to the exact public URL without a
trailing slash, then reload the page.

The administration area is at:

```text
http://localhost/tunisia-pc-advisor/admin/login.php
```

No administrator account is seeded. Create one from the project root:

```bash
C:\xampp\php\php.exe tools\create_admin.php --username=admin --name="Project Administrator"
```

The command prompts for a password of at least 10 characters. Sign in and change
the password from **My account** if needed.

For a local preview without Apache, use the included router:

```bash
C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php
```

Then open `http://127.0.0.1:8080/`. Do not start the built-in server without
`router.php`; the router blocks `.env`, SQL files and the private application
directories.

## 12. Common database connection errors

| Error or symptom | Cause and fix |
|---|---|
| `SQLSTATE[HY000] [2002] No connection could be made` | MySQL/MariaDB is not running, or `DB_HOST`/`DB_PORT` is wrong. Start MySQL in XAMPP and confirm the port. |
| `SQLSTATE[HY000] [1045] Access denied for user` | `DB_USER` or `DB_PASS` is wrong. Check the account in phpMyAdmin and update `.env`. A default XAMPP root account usually has an empty password. |
| `SQLSTATE[HY000] [1049] Unknown database` | The schema was not imported, or `DB_NAME` does not match `tunisia_pc_advisor`. Import `database/schema.sql`. |
| `SQLSTATE[HY000] [1044] Access denied ... to database` | The database user exists but lacks privileges on `tunisia_pc_advisor`. Grant the required privileges or use the account that imported the schema. |
| `could not find driver` | `pdo_mysql` is not enabled for the PHP binary used by Apache or the CLI. Enable it in `C:\xampp\php\php.ini` and restart Apache. |
| `The database is unavailable. Import database/schema.sql...` | The application caught a PDO connection error. Check the XAMPP MySQL status, the `.env` values and the PHP/Apache error log. |
| `Base table or view not found` | The database exists but the schema was not imported, or the import targeted another database. Import `database/schema.sql` and then `database/seed.sql`. |
| Homepage loads but the catalogue is empty | `database/seed.sql` was not imported, or it was imported into a different database. Check `DB_NAME` and run the count query in section 7. |
| Port 3306 is already in use | Another MySQL/MariaDB instance is running. Stop it, or change the XAMPP MySQL port and set the matching `DB_PORT` in `.env`. |
| CSS or JavaScript returns 404 | The detected base path is wrong. Set `APP_URL` to the exact URL, including any subfolder, without a trailing slash. |
| Admin login rejects the account | Confirm the account exists with `SELECT username, is_active FROM admin_users;` and reset it with `tools\create_admin.php --username=admin --force`. |
| `.env` or SQL files return 403/404 under Apache | This is expected: the included `.htaccess` blocks them. If you use another web server, reproduce those access rules or keep the project outside the public document root. |
