<p align="center">
  <img src="public/theme/logo.svg" width="92" alt="SHARTHAK Commerce logo">
</p>

<h1 align="center">SHARTHAK Commerce Platform</h1>

<p align="center">
  <strong>A complete commerce workspace for selling, managing, and scaling online.</strong><br>
  Storefront · Operations · Fulfillment · Growth
</p>

<p align="center">
  <a href="https://github.com/SH4RTH4K/e_commerce_marketplace"><img src="https://img.shields.io/badge/owner-SHARTHAK-f15a24?style=for-the-badge" alt="Owner SHARTHAK"></a>
  <img src="https://img.shields.io/badge/Laravel-13-ff2d20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/React-19-61dafb?style=for-the-badge&logo=react&logoColor=111827" alt="React 19">
  <img src="https://img.shields.io/badge/cPanel-ready-ff6c2c?style=for-the-badge&logo=cpanel&logoColor=white" alt="cPanel ready">
</p>

<p align="center">
  <a href="#what-is-included">Features</a> ·
  <a href="#imported-catalog-review">Catalog review</a> ·
  <a href="#quick-start">Quick start</a> ·
  <a href="#production-deployment">cPanel deployment</a> ·
  <a href="#security-before-launch">Security</a>
</p>

<br>

## What is included

| <div align="center">🛍️ Storefront</div> | <div align="center">⚙️ Operations</div> | <div align="center">📈 Growth</div> |
| :--- | :--- | :--- |
| Catalog, search, filters, cart, checkout, customer accounts, reviews, order tracking | Products, variants, inventory, orders, invoices, staff roles, permissions, courier delivery | Coupons, flash sales, banners, landing pages, SEO, analytics, branding, CRM |

<div align="center">

| Capability | Included |
| :--- | :---: |
| Cash on delivery and mobile banking | ✅ |
| Steadfast, Pathao, and RedX adapters | ✅ |
| Fraud controls by IP, device, phone, and courier history | ✅ |
| Abandoned checkout recovery | ✅ |
| Image/media management with upload validation | ✅ |
| Queue and scheduler support | ✅ |

</div>

## Imported catalog review

Imported products use a clear, repeatable publish decision process. This keeps the storefront clean while making large supplier catalogs fast to review.

```text
Search name/SKU
      ↓
Apply category, status, and rows-per-page filters
      ↓
Preview image → confirm price → confirm stock → confirm variants
      ↓
Publish approved products  |  Keep as draft  |  Unpublish when standards change
```

### Review controls

- **Image column:** click the thumbnail for a fast full-size preview and see the additional-image count.
- **Text search:** search by product name or SKU.
- **Explicit filtering:** edit the controls, then click **Apply filters** to fetch results.
- **Pagination:** choose 25, 50, or 100 rows per page; page navigation keeps active filters.
- **Decision actions:** publish, unpublish, sync, or update prices in bulk.

## Technology

<p>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777bb4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3 or newer">
  <img src="https://img.shields.io/badge/Inertia.js-3-9553e9?style=flat-square" alt="Inertia.js 3">
  <img src="https://img.shields.io/badge/Tailwind%20CSS-4-06b6d4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
  <img src="https://img.shields.io/badge/Vite-8-646cff?style=flat-square&logo=vite&logoColor=white" alt="Vite 8">
  <img src="https://img.shields.io/badge/MySQL-8.x-4479a1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL 8">
</p>

| Layer | Version / requirement |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | React 19, Inertia.js 3 |
| Styling and build | Tailwind CSS 4, Vite 8 |
| Database | MySQL 8.x or compatible MariaDB |
| Web server | Apache/LiteSpeed, Nginx, or Laravel development server |

Required PHP extensions include Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, `pdo_mysql`, Session, Tokenizer, and XML. GD or Imagick is recommended for image processing.

## Quick start

### Requirements

- PHP 8.3 or newer
- Composer 2.x
- Node.js 20.19+ or 22.12+
- MySQL 8.x or compatible MariaDB

### Install

```bash
composer install
npm ci
```

Create the environment file:

```bash
# Linux/macOS
cp .env.example .env

# Windows PowerShell
Copy-Item .env.example .env

php artisan key:generate
```

Configure the database in `.env`:

```dotenv
APP_NAME="SHARTHAK"
APP_ENV=local
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Prepare and run the application:

```bash
php artisan migrate
php artisan storage:link
npm run build
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

| URL | Purpose |
| --- | --- |
| `http://127.0.0.1:8000` | Storefront |
| `http://127.0.0.1:8000/login` | Customer login |
| `http://127.0.0.1:8000/admin/login` | Administrator login |

For hot reloading, run `npm run dev` in a second terminal. Remove a stale `public/hot` file before using only the production build.

> [!WARNING]
> The database seeder is for private development only. Do not run `php artisan migrate --seed` on a public production database without reviewing and replacing seeded credentials and demo data.

## Production deployment

The full cPanel runbook is in [DEPLOY.md](DEPLOY.md). The short version follows.

### 1. Clone the repository

Clone the `main` branch from [e_commerce_marketplace](https://github.com/SH4RTH4K/e_commerce_marketplace) using cPanel Git Version Control, or upload an archive created from the repository.

Use this layout whenever the host allows it:

```text
/home/CPANEL_USER/apps/sharthak/          Application root
/home/CPANEL_USER/apps/sharthak/public/   Domain document root
```

The domain must point to Laravel's `public` directory. Do not expose the complete application root.

### 2. Configure production environment

Copy [.env.production.example](.env.production.example) to `.env` on the server and set new secrets:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpanel_database
DB_USERNAME=cpanel_database_user
DB_PASSWORD=GENERATE_A_NEW_STRONG_PASSWORD

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

COURIER_WEBHOOK_SECRET=GENERATE_A_LONG_RANDOM_SECRET
TRUSTED_PROXIES=127.0.0.1
```

### 3. Prepare Laravel

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The production frontend is already compiled under `public/build`, so Node.js is not required on cPanel for a normal deployment. If frontend source changes, run `npm ci && npm run build` in a trusted build environment and commit the updated build output.

For an existing store, import the database backup privately through phpMyAdmin or MySQL, then run migrations. Never commit database exports, credentials, or customer data.

### 4. Configure operations

- Use `755` for directories and `644` for normal files.
- Ensure `storage/`, `bootstrap/cache/`, and `public/uploads/` are writable by the PHP user.
- Configure a queue worker for supplier and delivery jobs when supported by the hosting plan.
- Add the scheduler to cPanel Cron once per minute:

```cron
* * * * * cd /home/CPANEL_USER/apps/sharthak && php artisan schedule:run >> /dev/null 2>&1
```

Keep `DROPSHIPPING_ENABLED=false` until supplier credentials and sync drivers are tested with production data.

## Security before launch

> [!CAUTION]
> A successful deployment is not the same as a secure launch. Complete this checklist before accepting public traffic.

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] A unique production `APP_KEY` has been generated
- [ ] cPanel, SSH/SFTP/FTP, database, admin, mail, courier, payment, and analytics credentials are rotated
- [ ] `COURIER_WEBHOOK_SECRET` is configured; missing/invalid production tokens receive `401`
- [ ] The domain document root is Laravel's `public` directory
- [ ] `.env`, SQL dumps, archives, logs, backups, and customer data are not web-accessible
- [ ] HTTPS is forced and secure session cookies are enabled
- [ ] Staff roles and direct URL permissions have been tested
- [ ] Upload directories cannot execute server-side scripts
- [ ] Database and upload backups are stored outside the public directory
- [ ] Queue workers and scheduler are monitored
- [ ] Dependency audits and automated tests pass

## Verification commands

```bash
composer validate --no-check-publish
composer check-platform-reqs
composer audit
npm audit
php artisan route:list
php artisan test
```

## Project map

```text
app/                 Backend code, services, middleware, and models
bootstrap/            Framework bootstrap and cache directory
config/               Application and integration configuration
database/             Migrations, factories, and development seeders
public/               Web root, compiled assets, and uploaded media
resources/js/         React and Inertia pages and components
resources/views/      Blade entry points and layouts
routes/               Web and console routes
tests/                Feature and unit tests
DEPLOY.md             Detailed cPanel deployment runbook
```

## Ownership and license

Maintained by **SHARTHAK** · [sharthak.com](https://sharthak.com)

No project-level license file is currently included. Ownership by SHARTHAK does not grant permission to copy, redistribute, resell, or operate this source code. Add a reviewed license file if specific commercial or open-source rights are intended.
