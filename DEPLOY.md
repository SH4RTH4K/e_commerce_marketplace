<div align="center">

# 🚀 e_commerce_marketplace Production Deployment

### A complete cPanel deployment guide for the e_commerce_marketplace Laravel application

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Deployment](https://img.shields.io/badge/Deployment-cPanel-FF6C2C?style=flat-square&logo=cpanel&logoColor=white)

**Upload → Configure → Launch**

</div>

> [!IMPORTANT]
> Complete the **security checklist** before making the store public. Rotate every supplied credential and never keep the default administrator password.

---

## 📦 Package contents

| Included | Location | Purpose |
| :--- | :--- | :--- |
| Laravel application | Project root | Storefront, API, and admin panel |
| Production assets | `public/build/` | Pre-built JavaScript and CSS |
| Uploaded media | `public/uploads/` | Existing product and site images |
| Database setup | `database/migrations/` | Reproducible schema via Laravel migrations |
| Environment template | `.env.production.example` | Production configuration template |

## ✅ Before you begin

Make sure your hosting account provides:

- **PHP 8.3+** with the required Laravel extensions
- **MySQL 8.x** or a compatible MariaDB version
- **SSL/HTTPS** for your domain
- cPanel **File Manager** and **phpMyAdmin**
- cPanel **Terminal/SSH** access, if available

## 🧭 Deployment roadmap

| Step | Task | Result |
| :---: | :--- | :--- |
| 01 | [Upload the application](#01--upload-and-extract) | Project files are on the server |
| 02 | [Create a database](#02--create-the-database) | Database and user are ready |
| 03 | [Prepare the database](#03--prepare-the-database) | Tables are created from migrations |
| 04 | [Configure the environment](#04--configure-the-environment) | Laravel can connect to production services |
| 05 | [Prepare Laravel](#05--prepare-laravel) | Key, links, and caches are ready |
| 06 | [Set permissions](#06--set-file-permissions) | Writable directories work correctly |
| 07 | [Configure the web root](#07--configure-the-document-root) | Requests reach the Laravel application |
| 08 | [Verify and secure](#08--verify-the-deployment) | The store is ready for final checks |

---

## 01 · Upload and extract

1. Sign in to **cPanel → File Manager**.
2. Open `public_html/`, or the document folder assigned to your domain.
3. Select **Upload** and upload an application archive created from this repository.
4. Right-click the archive, select **Extract**, and extract it into `public_html/`.

Your directory should look like this:

```text
public_html/
|-- .htaccess           # Root redirect; do not delete
|-- .env.production.example # Copy to .env in Step 04
|-- app/
|-- artisan
|-- bootstrap/
|-- config/
|-- database/
|-- public/             # Apache/LiteSpeed web root
|   |-- .htaccess
|   |-- index.php
|   `-- build/
|-- resources/
|-- routes/
|-- storage/
`-- vendor/
```

> [!TIP]
> Enable **Show Hidden Files (dotfiles)** in File Manager so that `.env` and `.htaccess` are visible.

---

## 02 · Create the database

1. Open **cPanel → MySQL Databases**.
2. Create a database, for example `youruser_ecommerce_marketplace`.
3. Create a dedicated database user with a strong, unique password.
4. Add the user to the database and grant **All Privileges**.

Keep the database name, username, and password nearby. You will add them to `.env` in Step 04.

---

## 03 · Prepare the database

The repository uses Laravel migrations as the source of truth for the production schema.
After configuring `.env`, run the migration command in Step 05. It creates the required
tables in the database you created above.

> [!NOTE]
> Do not publish database dumps, customer data, credentials, or local backup archives in GitHub.

---

## 04 · Configure the environment

In File Manager, copy `.env.production.example` to `.env`. Open it and replace every value marked `← EDIT`.

```dotenv
APP_NAME="e_commerce_marketplace"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=youruser_ecommerce_marketplace
DB_USERNAME=youruser_dbuser
DB_PASSWORD=your_strong_database_password

SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true

MAIL_HOST=mail.yourdomain.com
MAIL_USERNAME=no-reply@yourdomain.com
MAIL_PASSWORD=your_smtp_password
MAIL_FROM_ADDRESS=no-reply@yourdomain.com

COURIER_WEBHOOK_SECRET=use_a_long_random_secret
```

> [!CAUTION]
> Never share or commit `.env`. It contains database, email, courier, and application secrets.

---

## 05 · Prepare Laravel

Open cPanel **Terminal**, move into the application directory, and run:

```bash
cd /home/CPANEL_USER/public_html

# Use cPanel's PHP 8.3 binary explicitly; `php` may point to another version.
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
COMPOSER_BIN="$(command -v composer)"

"$PHP83" -v
"$PHP83" "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader
"$PHP83" artisan key:generate --force
"$PHP83" artisan migrate --force
"$PHP83" artisan storage:link
"$PHP83" artisan optimize:clear
"$PHP83" artisan config:cache
"$PHP83" artisan route:cache
"$PHP83" artisan view:cache
```

The PHP version should report `8.3.x`. If Composer is not found, enable Composer in
cPanel and verify it with `command -v composer`. If a PHP extension is missing, enable it
under **Select PHP Version → Extensions**.

### Queue and scheduler workers

Supplier sync actions are queued and must not be enabled without a durable worker. Keep
`DROPSHIPPING_ENABLED=false` until an evidence-backed supplier driver and credentials have
been configured and tested.

### In-app Git deployment

Once the server is a Git checkout, administrators with `settings.manage` permission can use
`/admin/system/git-repository` to test the connection, review incoming commits, and deploy a
fast-forward update. Configure the repository URL to match the server's Git remote. Private
repository credentials are encrypted in the database and are never displayed after saving.

The deployment workflow preserves untracked uploads and runtime files. It blocks when tracked
server changes or diverged branch history are detected. The explicit discard option resets only
tracked files, and source rollback does not reverse database migrations.

On a server with Supervisor or a hosting process manager, run one long-lived worker from the
application directory:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

Restart workers after each deployment so they load the new code:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan queue:restart
```

Add the Laravel scheduler to cPanel Cron (or the host's equivalent) once per minute:

```cron
* * * * * cd /home/CPANEL_USER/public_html && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Verify the schedule and worker before enabling supplier sync:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:list
/opt/cpanel/ea-php83/root/usr/bin/php artisan queue:work database --once
```

The dropshipping schedules use database-backed locks, skip overlapping supplier runs, and
retain terminal sync-run history according to `DROPSHIPPING_RUN_RETENTION_DAYS`.

### No Terminal or SSH?

Use cPanel's **PHP Script Runner**, if available, or ask the hosting provider to run the commands. You can generate an application key locally with:

```bash
php artisan key:generate --show
```

Copy the generated value—including the `base64:` prefix—into `APP_KEY` in `.env`.

---

## 06 · Set file permissions

Laravel must be able to write to `storage/` and `bootstrap/cache/`.

| Directory | Permission | Apply to |
| :--- | :---: | :--- |
| `storage/` | `755` | All subdirectories and files |
| `bootstrap/cache/` | `755` | Directory and its contents |

Or use SSH:

```bash
chmod -R 755 storage bootstrap/cache
```

> [!WARNING]
> Avoid `777` permissions. If `755` does not work, ask the host to correct file ownership instead of making the directories world-writable.

---

## 07 · Configure the document root

The preferred document root is:

```text
public_html/public
```

If cPanel allows it, open **Domains**, select your domain, and set its document root to that path.

If you cannot change the document root, keep the root `.htaccess` file in place. It forwards requests to `public/` automatically.

---

## 08 · Verify the deployment

Open your production URLs:

| Area | URL |
| :--- | :--- |
| Storefront | `https://yourdomain.com` |
| Admin panel | `https://yourdomain.com/admin` |

Sign in with the private administrator credentials supplied to the site owner:

```text
Username: admin
Password: [supplied privately]
```

> [!CAUTION]
> Change the administrator email and password **before the site receives its first public visitor**. Remove or disable every unused demo and staff account.

### Final smoke test

- [ ] Storefront and admin panel load over HTTPS
- [ ] Administrator sign-in works with a newly changed password
- [ ] Product images and compiled styles load correctly
- [ ] Cart, checkout, and order creation work
- [ ] Email delivery works
- [ ] Courier integration and webhook authentication work
- [ ] No errors appear in `storage/logs/laravel.log`

---

## 🛠️ Troubleshooting

| Symptom | What to check |
| :--- | :--- |
| **500 Server Error** | Review `storage/logs/laravel.log`, then confirm the PHP version and folder permissions. |
| **Blank page** | Check the Laravel log. Enable `APP_DEBUG=true` only briefly and never expose debug output publicly. |
| **Styles or scripts return 404** | Confirm that `public/build/manifest.json` exists and the document root is correct. |
| **Images do not appear** | Run `php artisan storage:link` and confirm that uploaded files exist. |
| **Database connection fails** | Recheck `DB_HOST`, database name, username, password, and user privileges. |
| **Login/session problems** | Confirm `APP_URL`, `SESSION_DOMAIN`, HTTPS settings, and writable session storage; then run `php artisan optimize:clear`. |
| **Configuration changes do not appear** | Run `php artisan optimize:clear` followed by `php artisan config:cache`. |
| **413 Content Too Large during backup restore** | Raise the cPanel PHP upload/request limits below, or upload and restore the database and media archives separately. A 413 is generated by PHP, Apache/LiteSpeed, or ModSecurity before Laravel receives the request. |

### 413 backup restore limit

The System Health page accepts restore archives up to 500 MB. In cPanel open
**MultiPHP INI Editor**, select the application domain, and set:

```ini
upload_max_filesize = 512M
post_max_size = 520M
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
```

`post_max_size` must be larger than `upload_max_filesize`. If cPanel or the hosting
provider does not allow these values, the web server or ModSecurity request limit must
also be raised by the provider. As a lower-limit fallback, restore the `.sql.gz` database
archive and `.media.tar.gz` media archive separately, or ask the provider to restore the
private archive from the server filesystem. Never place backup archives in a public web
directory.

> [!TIP]
> After troubleshooting, always restore `APP_DEBUG=false` and rebuild the configuration cache.

---

## 🔐 Production security checklist

### Application

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] A unique `APP_KEY` has been generated
- [ ] Default administrator and seeded account passwords have been replaced
- [ ] Staff permissions are enforced server-side
- [ ] `COURIER_WEBHOOK_SECRET` is set and webhook signatures are enforced

### Infrastructure

- [ ] SSL is active and HTTP redirects to HTTPS
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Database and SMTP credentials are unique to production
- [ ] `storage/` and `bootstrap/cache/` are writable without `777`
- [ ] `.env`, SQL dumps, archives, and logs are not publicly accessible
- [ ] Old ZIP and SQL files have been moved outside the web root after deployment
- [ ] Hosting, cPanel, SSH/SFTP, database, SMTP, and courier credentials have been rotated

---

<div align="center">

**Deployment complete? Back up the database, remove installation archives, and monitor the Laravel log.**

</div>
