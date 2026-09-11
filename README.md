# SHARTHAK Commerce Platform

> A production-oriented Laravel marketplace for catalog management, storefront sales, customer accounts, fulfillment, courier integrations, and operational administration.

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/React-19-61DAFB?style=flat-square&logo=react&logoColor=111827" alt="React 19">
  <img src="https://img.shields.io/badge/Inertia.js-3-9553E9?style=flat-square" alt="Inertia.js 3">
  <img src="https://img.shields.io/badge/Tailwind%20CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
  <img src="https://img.shields.io/badge/cPanel-ready-FF6C2C?style=flat-square&logo=cpanel&logoColor=white" alt="cPanel ready">
</p>

## Overview

SHARTHAK Commerce Platform combines a customer-facing online store with a complete administration workspace. It is designed for teams that need to manage products, inventory, orders, marketing content, customer communication, and delivery operations from one Laravel application.

The application is maintained by **SHARTHAK**. Visit [sharthak.com](https://sharthak.com) for the official website.

## Core capabilities

### Storefront

- Product catalog, category browsing, search, filters, and product detail pages
- Customer registration, authentication, profiles, order history, and password reset
- Cart, standard checkout, quick order, cash on delivery, and mobile banking options
- Coupons, flash sales, reviews, SEO metadata, storefront branding, and landing pages
- Order tracking, abandoned checkout recovery, and contact forms

### Administration

- Product, category, inventory, variant, banner, coupon, and landing-page management
- Order review, status management, invoices, courier shipment, and tracking
- Customer CRM, reviews, contact messages, fraud controls, and blocked identity lists
- Staff roles and server-side permissions
- Store settings, payment configuration, analytics, media management, and design controls

### Imported product review workflow

The imported-products screen provides a consistent publish/unpublish review process:

1. Search by product name or SKU and select category/status filters.
2. Click **Apply filters** to fetch the requested result. Changing rows per page does not fetch automatically.
3. Open the image preview and check the primary image and additional-image count.
4. Confirm price, stock, and mapped supplier variants.
5. Publish approved products, or leave them as drafts. Unpublish products that no longer meet the storefront standard.
6. Use pagination and the 25, 50, or 100 rows-per-page setting for larger catalogs.

## Technology

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | React 19, Inertia.js 3 |
| Styling | Tailwind CSS 4 |
| Build | Vite 8, Node.js 20.19+ or 22.12+ |
| Database | MySQL 8.x or compatible MariaDB |
| Web server | Apache/LiteSpeed, Nginx, or Laravel development server |
| Delivery integrations | Steadfast, Pathao, and RedX service adapters |

Required PHP extensions include Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, `pdo_mysql`, Session, Tokenizer, and XML. GD or Imagick is recommended for image processing.

## Quick start

### Requirements

- PHP 8.3 or newer
- Composer 2.x
- Node.js 20.19+ or 22.12+
- MySQL 8.x or compatible MariaDB

### 1. Install dependencies

```bash
composer install
npm ci
```

### 2. Configure the environment

Linux/macOS:

```bash
cp .env.example .env
php artisan key:generate
```

Windows PowerShell:

```powershell
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

Never reuse production keys, database passwords, courier credentials, or mail credentials from an old archive.

### 3. Prepare and run

```bash
php artisan migrate
php artisan storage:link
npm run build
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

- Storefront: `http://127.0.0.1:8000`
- Customer login: `http://127.0.0.1:8000/login`
- Administrator login: `http://127.0.0.1:8000/admin/login`

For frontend hot reloading, run `npm run dev` in a second terminal. Remove a stale `public/hot` file before using only the production build.

The database seeder is intended for private development environments only. Do not use `php artisan migrate --seed` on a public production database without reviewing and replacing all seeded credentials and demo data.

## Production deployment on cPanel

The complete deployment runbook is available in [DEPLOY.md](DEPLOY.md). The essential process is:

### 1. Clone or upload the application

Clone the `main` branch from the [e_commerce_marketplace repository](https://github.com/SH4RTH4K/e_commerce_marketplace), or upload an archive created from it.

Keep the application outside the public web root where possible:

```text
/home/CPANEL_USER/apps/sharthak/          Application root
/home/CPANEL_USER/apps/sharthak/public/   Domain document root
```

Do not use the complete Laravel project as the document root. Never expose `.env`, SQL exports, backup archives, logs, or private cache files.

### 2. Configure production `.env`

Copy [.env.production.example](.env.production.example) to `.env` on the server and set new production values:

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

The compiled frontend files are committed under `public/build`, so Node.js is not required on cPanel for a normal deployment. If frontend source files are changed, run `npm ci && npm run build` in a trusted build environment and commit the new build output.

For an existing store, import the database backup privately through phpMyAdmin or MySQL, then run migrations. Database exports must never be committed to this repository.

### 4. Permissions and operations

- Use `755` for directories and `644` for normal files.
- Ensure `storage/`, `bootstrap/cache/`, and `public/uploads/` are writable by the PHP/web-server user.
- Do not use recursive `777` permissions.
- Configure a queue worker for supplier and delivery jobs when the hosting plan supports it.
- Add the Laravel scheduler to cPanel Cron once per minute:

```cron
* * * * * cd /home/CPANEL_USER/apps/sharthak && php artisan schedule:run >> /dev/null 2>&1
```

Keep `DROPSHIPPING_ENABLED=false` until supplier credentials and sync drivers have been tested with production data.

## Security baseline

Before the first public request:

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Generate a unique production `APP_KEY`.
- Rotate cPanel, SSH/SFTP/FTP, database, administrator, SMTP, courier, payment, and analytics credentials.
- Configure `COURIER_WEBHOOK_SECRET`; production requests without a valid token are rejected.
- Use the `public` directory as the document root.
- Keep SQL dumps, archives, `.env` files, logs, and customer data outside web-accessible locations.
- Review administrator accounts, staff permissions, cron jobs, DNS records, and deployment hooks.
- Validate uploads and prevent executable content in upload directories.
- Force HTTPS and enable secure session cookies.
- Back up the database and uploaded media outside the public directory, then test restoration.
- Run dependency audits and automated tests before each release.

## Verification commands

```bash
composer validate --no-check-publish
composer check-platform-reqs
composer audit
npm audit
php artisan route:list
php artisan test
```

## Project structure

```text
app/                 Laravel application code, services, middleware, and models
bootstrap/            Framework bootstrap and cache directory
config/               Application and integration configuration
database/             Migrations, factories, and development seeders
public/               Web root, compiled assets, and uploaded media
resources/js/         React and Inertia pages and components
resources/views/      Blade entry points and server-rendered layouts
routes/               Web and console route definitions
tests/                Feature and unit tests
DEPLOY.md             Detailed cPanel deployment runbook
```

## License and ownership

No project-level license file is currently included. Ownership by SHARTHAK does not grant permission to copy, redistribute, resell, or operate this source code. Add a reviewed license file if specific commercial or open-source rights are intended.

---

Maintained by **SHARTHAK** · [sharthak.com](https://sharthak.com)
