# SHARTHAK Laravel E-Commerce Platform

A Laravel, Inertia.js, React, and Tailwind CSS e-commerce application for online stores, with product management, checkout, customer accounts, courier integrations, fraud controls, marketing tools, and an administration dashboard.

## Ownership

- Owner and maintainer: **SHARTHAK**
- Official website: [https://sharthak.com](https://sharthak.com)

No ownership, support, activation, telemetry, or remote-access rights are granted to any previous supplier by this project documentation.

## Security status

> **Production deployment is currently blocked pending security hardening.**

A read-only review on 4 September 2026 found no obvious hidden vendor callback, webshell, shell-execution code, or unauthorized administrator in the active application. That does not guarantee that the application is impossible to compromise.

The following items must be corrected before the application is exposed to the internet:

1. Make the courier webhook reject every request unless a valid secret/signature is configured.
2. Enforce staff permissions on the server with Laravel gates, policies, or permission middleware. Hiding menu items is not authorization.
3. Rotate all credentials that may have been known to a previous supplier, including hosting, SSH/SFTP/FTP, database, administrator, SMTP, courier API, and application keys.
4. Remove secrets from database exports and remove every archive or SQL dump from web-accessible directories.
5. Validate uploaded file contents and MIME types, reject active SVG content, and store uploads outside the webroot where practical.
6. Remove public development artifacts such as `.DS_Store`, obsolete `.user.ini` files, and `capture_helper.html`.
7. Remove unsafe raw-HTML rendering or sanitize it with a maintained allow-list sanitizer, then tighten the Content Security Policy.
8. Replace predictable seeded passwords before the first public request.

Never publish a vulnerability report containing passwords, API keys, database dumps, or other secret values.

## Technology

| Component | Version / requirement |
| --- | --- |
| Laravel | 13.x (currently 13.18.0) |
| PHP | 8.3 or later |
| Database | MySQL 8.x or a compatible MariaDB release |
| Frontend | React 19, Inertia.js 3, Tailwind CSS 4, Vite 8 |
| Node.js | 20.19 or later, or 22.12 or later |
| Composer | 2.x |
| Web server | Apache/LiteSpeed, Nginx, or the local Laravel server |

Required PHP extensions include Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, `pdo_mysql`, Session, Tokenizer, and XML. GD or Imagick is recommended for image processing.

## Main features

- Product, category, inventory, coupon, flash-sale, banner, and landing-page management
- Customer registration, login, password reset, profiles, order history, and invoices
- Cart, standard checkout, quick order, cash on delivery, and mobile-banking configuration
- Steadfast, Pathao, and RedX courier integrations
- Order tracking, abandoned-checkout recovery, reviews, and contact messages
- Fake-order controls using IP, device, phone, and BD Courier information
- Store branding, SEO, analytics, legal pages, and storefront customization
- Staff roles and permission definitions (server-side enforcement is required before production)

## Local installation

### 1. Install dependencies

```bash
composer install
npm ci
```

### 2. Create the environment file

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

Never reuse a production `APP_KEY`, database password, or API key from an old archive.

### 3. Create and configure MySQL

Create an empty database and a dedicated database user. Do not use a MySQL administrator account in production.

```env
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

### 4. Prepare the application

```bash
php artisan migrate
php artisan storage:link
npm run build
php artisan optimize:clear
```

Use `php artisan migrate --seed` only for a private development environment. The supplied seeder contains predictable development credentials that must never remain active on a public website.

### 5. Start locally

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

- Storefront: `http://127.0.0.1:8000`
- Customer login: `http://127.0.0.1:8000/login`
- Administrator login: `http://127.0.0.1:8000/admin/login`

For frontend hot reloading, run `npm run dev` in a second terminal. Delete a stale `public/hot` file before using only the production build; otherwise Laravel may try to load an unavailable Vite development server and display a blank page.

## MySQL/DBeaver troubleshooting

### `Public Key Retrieval is not allowed`

For a local MySQL 8 connection in DBeaver:

1. Edit the connection.
2. Open **Driver properties**.
3. Set `allowPublicKeyRetrieval` to `true`.
4. For localhost only, set `useSSL` to `false` if SSL is not configured.
5. Confirm the host is `127.0.0.1`, port is `3306`, and test the connection.

Equivalent local JDBC options are:

```text
allowPublicKeyRetrieval=true&useSSL=false
```

Do not use this localhost workaround as the production security design. Use TLS for remote database administration, restrict MySQL access by IP, and keep the website database host private or on `localhost`.

## Secure cPanel deployment

### Required directory layout

Configure the domain document root to point to the Laravel `public` directory, for example:

```text
/home/CPANEL_USER/apps/sharthak/          Laravel application (not public)
/home/CPANEL_USER/apps/sharthak/public/   Domain document root
```

Do not make the complete Laravel project the public document root. An `.htaccess` fallback is not an equal substitute for a correctly configured document root.

Never place these items in `public_html` or any other web-accessible directory:

- `.env`, `.env.production`, or credential notes
- SQL/database exports
- ZIP/RAR/TAR backups
- `vendor` source listings, tests, logs, or cache files
- cPanel backups or copies of an older project

### Production environment

Create `.env` directly on the server. Use new values and never commit or distribute it.

```env
APP_NAME="SHARTHAK"
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

Generate `APP_KEY` on the production server:

```bash
php artisan key:generate
```

Use `TRUSTED_PROXIES=*` only when the hosting/proxy architecture genuinely requires trusting every proxy. Prefer explicit proxy addresses.

### Deployment commands

Build assets locally or in a trusted build environment:

```bash
npm ci
npm run build
```

On the server:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Restart queue workers after each deployment. Configure a supervised queue worker where cPanel supports it, or use an appropriate scheduled queue command for the hosting plan.

### Permissions

- Normal directories: `755`
- Normal files: `644`
- `storage` and `bootstrap/cache`: writable by the PHP/web-server user, commonly `775`
- Never solve permission problems by recursively applying `777`
- Disable directory listing and PHP execution inside upload directories at the web-server level

### HTTPS and server configuration

- Force HTTPS and enable automatic certificate renewal.
- Set PHP `display_errors=Off` and `expose_php=Off` in production.
- Disable remote MySQL unless it is required; if enabled, allow only trusted IP addresses.
- Use a Web Application Firewall such as ModSecurity when available.
- Back up the database and uploads outside the public directory and test restoration.
- Keep PHP, Composer packages, Node packages, and cPanel software patched.

## Credential handover checklist

When taking ownership from a previous developer or company:

- [ ] Change the cPanel and hosting-provider passwords.
- [ ] Remove unknown cPanel team members, FTP accounts, SSH keys, and API tokens.
- [ ] Review cron jobs, email forwarders, DNS records, subdomains, and deployment hooks.
- [ ] Create a new MySQL user/password and remove old database users.
- [ ] Change every administrator password and remove unknown staff/customer accounts.
- [ ] Rotate SMTP, courier, analytics, payment, and fraud-service credentials.
- [ ] Generate a new production `APP_KEY` before launch.
- [ ] Search the server for old ZIP files, SQL dumps, `.env` copies, and public backups.
- [ ] Review access and error logs after deployment.

Changing the visible brand or copyright text does not revoke access. Credentials, accounts, keys, scheduled jobs, DNS, and hosting permissions must all be reviewed.

## Pre-launch checklist

- [ ] All security blockers listed above are fixed and tested.
- [ ] The domain document root is the `public` directory only.
- [ ] No secrets, dumps, archives, logs, or development artifacts are publicly accessible.
- [ ] `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] HTTPS is forced and session cookies are secure.
- [ ] The webhook secret is present and invalid/missing signatures receive `401` or `403`.
- [ ] Every staff role is tested against direct URLs and write requests.
- [ ] Uploads are checked by content, MIME type, extension, size, and authorization.
- [ ] Seeded/default accounts and passwords have been removed or changed.
- [ ] `composer audit` and `npm audit` complete without unresolved high-risk advisories.
- [ ] Automated tests pass against an isolated testing database.
- [ ] Database backups and restore procedures have been tested.

## Maintenance commands

```bash
composer audit
npm audit
php artisan test
php artisan about
php artisan route:list
php artisan optimize
```

Run security and dependency checks regularly, especially before deployments and after framework upgrades.

## License

No project-level license file is currently included in this repository. Ownership by SHARTHAK does not automatically grant third parties permission to copy, redistribute, or resell the source code. Add a reviewed license file if specific commercial or open-source rights are intended.

---

Owned and maintained by **SHARTHAK** — [https://sharthak.com](https://sharthak.com)
