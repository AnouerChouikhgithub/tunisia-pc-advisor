# Installation and local setup

This guide targets a standard XAMPP installation on Windows, with notes for Apache virtual hosts, subfolder deployment and PHP's built-in development server.

For the GitHub-ready environment-variable setup, database import steps and
connection troubleshooting, see [LOCAL_SETUP.md](LOCAL_SETUP.md). This guide
adds virtual-host, maintenance and scheduling notes.

## 1. Requirements

| Component | Minimum | Tested |
|---|---|---|
| PHP | 8.2 | 8.3.31 (XAMPP) |
| PHP extensions | PDO, pdo_mysql, curl, mbstring, json, openssl | All present |
| Database | MySQL 8+ or MariaDB 10.4+ | MariaDB 10.4.32 |
| Web server | Apache 2.4 with `.htaccess` support, or PHP built-in server | PHP built-in server for HTTP tests |

The system PHP on the test machine did not include `pdo_mysql`; the XAMPP PHP binary did. Use `C:\xampp\php\php.exe` for CLI commands and XAMPP Apache/PHP for the web application.

Check the CLI modules:

```bash
C:\xampp\php\php.exe -m | findstr /I "pdo_mysql curl mbstring openssl"
```

If `pdo_mysql` is missing, enable it in `C:\xampp\php\php.ini` by removing the leading semicolon from `extension=pdo_mysql`, then restart Apache.

## 2. Place the project

### Option A — XAMPP htdocs (simplest)

Copy the whole project folder to:

```text
C:\xampp\htdocs\tunisia-pc-advisor
```

Open `http://localhost/tunisia-pc-advisor/`. The application detects its base path from `DOCUMENT_ROOT`.

### Option B — keep the project where it is

Configure an Apache virtual host, or use the PHP built-in server described in section 6. A virtual host example:

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

Replace the path with the actual project location if it is not in XAMPP's
`htdocs` directory.

Add `127.0.0.1 tpa.local` to `C:\Windows\System32\drivers\etc\hosts` if you use the hostname, then restart Apache and open `http://tpa.local/`.

`AllowOverride All` is required for the included `.htaccess` rules. `Options -Indexes` disables directory listing, and the per-directory rules deny direct HTTP access to `.env`, `config/`, `classes/`, `database/`, `docs/`, `includes/` and `tools/`.

## 3. Start MySQL/MariaDB

Start **MySQL** in the XAMPP Control Panel and confirm it accepts a connection:

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT VERSION();"
```

For a default XAMPP installation the root account has no password. If you set a root password, use it in `.env` below.

## 4. Create and seed the database

### Through phpMyAdmin

1. Open `http://localhost/phpmyadmin`.
2. Choose **Import**.
3. Import `database/schema.sql`. It creates the `tunisia_pc_advisor` database and all tables.
4. Import `database/seed.sql`. It loads activities, workloads, brands, retailers, all 170 products and their initial price snapshots.

### Through the MySQL CLI

```bash
cd "C:\xampp\htdocs\tunisia-pc-advisor"
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root < database/seed.sql
```

**Warning:** `schema.sql` drops and recreates the application tables. Do not run it against a database containing records you need. Back up first.

Verify the import:

```bash
C:\xampp\mysql\bin\mysql.exe -u root -D tunisia_pc_advisor -e "
SELECT COUNT(*) AS products FROM products;
SELECT COUNT(*) AS laptops FROM products WHERE product_category='laptop';
SELECT COUNT(*) AS desktops FROM products WHERE product_category='desktop';
SELECT COUNT(*) AS price_rows FROM product_price_history;
"
```

Expected: 170 products, 127 laptops, 43 desktops and 170 initial price rows.

## 5. Configure the connection

Copy `.env.example` to `.env` in the project root:

```ini
APP_ENV=local
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=tunisia_pc_advisor
DB_USER=root
DB_PASS=
```

- Leave `APP_URL` unset for normal XAMPP or built-in-server use; it is auto-detected from the request.
- Set `APP_URL` only when the detected path is wrong, for example behind a proxy or with a non-standard public URL.
- Never commit `.env`. It is already listed in `.gitignore`.
- `APP_DEBUG` should remain `false` for anything beyond local development.

## 6. Run the application

### Apache

Start Apache from XAMPP and open:

```text
http://localhost/tunisia-pc-advisor/
```

### PHP built-in server

From the project root:

```bash
C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php
```

Open `http://127.0.0.1:8080/`.

Do **not** run `php -S ...` without `router.php` for this project. PHP's built-in server otherwise serves `.env` and SQL files as static content. The router applies the same sensitive-path policy as the Apache rules.

If port 8080 is in use, choose another port (for example `8081`) and adjust the URL.

## 7. Create the administrator

There is deliberately no seeded admin account and no default password.

Interactive creation:

```bash
C:\xampp\php\php.exe tools\create_admin.php --username=admin --name="Project Administrator"
```

The command prompts for a password and confirmation. Passwords must be at least 10 characters. For a scripted local setup:

```bash
C:\xampp\php\php.exe tools\create_admin.php --username=admin --name="Project Administrator" --password="use-a-long-local-password"
```

Avoid putting a real production password into shell history. The interactive prompt is safer on a shared machine.

Sign in at:

```text
http://localhost/tunisia-pc-advisor/admin/login.php
```

After the first sign-in, use **My account** to change the password.

To reset a forgotten password:

```bash
C:\xampp\php\php.exe tools\create_admin.php --username=admin --force
```

`--force` resets the password of the existing username and reactivates the account.

## 8. Optional maintenance commands

Run all commands from the project root with the XAMPP PHP binary.

```bash
:: Recheck up to 50 unchecked product links, politely spaced
C:\xampp\php\php.exe tools\check_links.php --limit=50 --offset=0 --delay=1200

:: Delete anonymous recommendation sessions older than 180 days
C:\xampp\php\php.exe tools\prune_sessions.php --days=180 --yes

:: Regenerate the data quality report from the live database
C:\xampp\php\php.exe tools\data_quality.php

:: Run the automated test suite
C:\xampp\php\php.exe tools\run_tests.php
```

Research regeneration is optional and should be reviewed against each retailer's current terms and `robots.txt`:

```bash
C:\xampp\php\php.exe tools\scrape_tunisianet.php
C:\xampp\php\php.exe tools\verify_manual.php
C:\xampp\php\php.exe tools\enrich_images.php
C:\xampp\php\php.exe tools\build_seed.php
```

`build_seed.php` only writes `database/seed.sql`; it does not import it. Import the file after reviewing the generated summary.

## 9. Scheduled maintenance

On Windows, create a Task Scheduler task or a small `.bat` wrapper for monthly link checks and session pruning. Example `maintenance.bat`:

```bat
@echo off
cd /d "C:\xampp\htdocs\tunisia-pc-advisor"
C:\xampp\php\php.exe tools\check_links.php --limit=40 --delay=1500
C:\xampp\php\php.exe tools\prune_sessions.php --days=180 --yes
C:\xampp\php\php.exe tools\data_quality.php
```

The link checker marks only HTTP 404/410 as `broken`. HTTP 403/429 and network failures remain `unchecked`, because a blocked response does not prove that a product page is gone. Price and stock are never changed automatically.

## 10. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `could not find driver` | The active PHP binary does not have `pdo_mysql`. Enable it in `php.ini` and restart Apache. Use `C:\xampp\php\php.exe` rather than the system PHP for CLI. If Apache still fails while CLI works, check `phpinfo()`: a machine-wide `PHP_INI_SCAN_DIR` may point at another PHP installation (for example Scoop). Clear that variable for Apache or remove the conflicting scan directory, and confirm `Loaded Configuration File` is `C:\xampp\php\php.ini` and `extension_dir` is `C:\xampp\php\ext`. |
| `SQLSTATE[HY000] [1045] Access denied` | Set the correct `DB_USER`/`DB_PASS` in `.env`, or reset the XAMPP MySQL root password. |
| `Unknown database 'tunisia_pc_advisor'` | Import `database/schema.sql` before `database/seed.sql`. |
| Homepage loads but no products | The seed import failed or targeted another database. Check `DB_NAME` and run the verification query in section 4. |
| CSS/JS 404s | The detected base path is wrong. Set `APP_URL` in `.env` to the exact public URL, including the subfolder. |
| Apache 500 with `.htaccess` | Ensure `mod_authz_core` is enabled and `AllowOverride All` is set for the project directory. If the host does not allow overrides, move the `.htaccess` files or use the built-in server router. |
| Built-in server exposes `.env` | You started it without `router.php`. Stop it and use `php -S 127.0.0.1:8080 router.php`. |
| Admin login always fails | Check the account exists (`SELECT username, is_active FROM admin_users;`) and reset it with `tools/create_admin.php --force`. Login throttling blocks after six failures in ten minutes; wait or restart the session. |
| Mytek/Wiki link checks return 403 | Expected Cloudflare challenge. Do not bypass it. Review the page manually and leave the link `unchecked` unless it is genuinely gone. |
| Prices look out of date | Freshness labels are based on `verified_at`. Use the admin edit form after manually checking the retailer page; do not change a price without evidence. |
| Images do not load | The catalogue references remote retailer image URLs. A retailer may block hotlinking or remove an image; the interface falls back to a local placeholder. |
| `php tools/...` says “CLI only” | Run the command from the terminal, not through the browser. |

## 11. Security notes for a shared machine

- Keep `.env` out of version control and out of the web root if using a non-Apache host that ignores `.htaccess`.
- Use a dedicated MySQL user with only the permissions this application needs for anything beyond local coursework.
- Set `APP_DEBUG=false`.
- Do not expose the local development server to an untrusted network.
- Change the administrator password after the first sign-in.
- The app stores anonymous recommendation sessions but no email address, account or IP address. The privacy page explains this.
