# Deploying Looks Jhorna

Two apps, deployed separately:

| App | What it is | Runs as |
|---|---|---|
| `apps/backend` | Laravel API + Filament admin | PHP 8.4-FPM behind Nginx, plus a queue worker and the scheduler |
| `apps/storefront` | Next.js storefront | `next start` (Node 22+) behind Nginx, or Vercel |

A typical setup is one VPS (2 vCPU / 4 GB is plenty to start) with `api.example.com` for the backend and `example.com` for the storefront. Both need HTTPS (Let's Encrypt via certbot).

## 1. Server packages

- PHP 8.4 with `bcmath, curl, gd, intl, mbstring, mysql, redis, sqlite3, xml, zip` and Composer
- MySQL 8 (or MariaDB 10.11+), Redis 7
- Node 22 + pnpm 10 (for the storefront)
- Nginx, Supervisor, certbot

## 2. Database

```sql
CREATE DATABASE looks_jhorna CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'looks_jhorna'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL ON looks_jhorna.* TO 'looks_jhorna'@'localhost';
```

Bangla text needs `utf8mb4`. Timestamps are stored in UTC; reports convert to `SHOP_TIMEZONE` with `CONVERT_TZ` and a fixed offset, so MySQL's timezone tables are not required.

## 3. Backend

```bash
cd apps/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
```

Set in `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com

DB_CONNECTION=mysql
DB_DATABASE=looks_jhorna
DB_USERNAME=looks_jhorna
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

STOREFRONT_URL=https://example.com
STOREFRONT_REVALIDATE_SECRET=<openssl rand -hex 32>   # same value as the storefront's REVALIDATE_SECRET
SHOP_TIMEZONE=Asia/Dhaka

ADMIN_NAME="..."
ADMIN_EMAIL=...
ADMIN_PASSWORD=...   # first super admin, created by RoleSeeder; change it after first login
# ADMIN_REQUIRE_2FA defaults to true when APP_ENV=production: every staff member sets up an authenticator app on first login
# SEARCH_DRIVER=database   # the built-in typo-tolerant search; no search server needed
```

Then:

```bash
php artisan migrate --force
php artisan db:seed --force   # roles + first admin, districts/thanas/zones, default menus and pages
php artisan storage:link
php artisan optimize          # config, routes, events, views
php artisan filament:optimize # icons + components
```

The demo catalogue is seeded only when `APP_ENV=local`.

Courier, SMS, payment numbers, tracking pixels, the Anthropic key and every storefront text are set in the admin (`/admin`), not in `.env`.

### Queue worker and scheduler

Supervisor program (`/etc/supervisor/conf.d/looks-jhorna-worker.conf`):

```ini
[program:looks-jhorna-worker]
command=php /var/www/looks-jhorna/apps/backend/artisan queue:work redis --sleep=1 --tries=3 --max-time=3600
user=www-data
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=60
```

Cron (`crontab -u www-data -e`):

```cron
* * * * * cd /var/www/looks-jhorna/apps/backend && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs: `courier:sync` (every 30 min), `flash-sales:refresh` (every minute), `carts:remind` (every 10 min, abandoned-cart SMS 9am–9pm), a nightly storefront refresh at 00:01 for date-driven copy (Ramadan mode), `model:prune` and `sanctum:prune-expired` (daily).

### Nginx (backend)

Standard Laravel config with `root /var/www/looks-jhorna/apps/backend/public;` and `client_max_body_size 20m;` (product image uploads).

## 4. Storefront

```bash
cd apps/storefront
pnpm install --frozen-lockfile
cp .env.example .env.local
```

```dotenv
NEXT_PUBLIC_SITE_URL=https://example.com
NEXT_PUBLIC_API_URL=https://api.example.com/api/v1
NEXT_PUBLIC_ASSET_ORIGIN=https://api.example.com
REVALIDATE_SECRET=<same as backend STOREFRONT_REVALIDATE_SECRET>
```

```bash
rm -rf .next/cache   # always, when an API response shape changed
pnpm build
pnpm start --port 3000   # run under Supervisor or PM2; don't add --hostname (see below)
```

The backend must be reachable while the storefront builds (pages are prerendered from the API). Don't pass `--hostname 127.0.0.1` to `next start`: with it every Bangla page (the unprefixed default locale) redirects to itself forever. Keep port 3000 closed in the firewall instead and let Nginx be the only way in. Nginx proxies `example.com` to `127.0.0.1:3000`.

**Vercel instead:** set the same four env vars, root directory `apps/storefront`. `NEXT_PUBLIC_ASSET_ORIGIN` must be the public backend origin so `next/image` may load product photos.

## 5. After the first deploy

1. Log in at `https://api.example.com/admin`, change the admin password, scan the 2FA QR code with an authenticator app and keep the recovery codes somewhere safe.
2. সেটিংস → সাইট / ব্র্যান্ড ও থিম: name, logo, hotline, colours.
3. সেটিংস → কুরিয়ার, SMS, পেমেন্ট: provider credentials and bKash/Nagad numbers.
4. সেটিংস → ট্র্যাকিং: Meta pixel + CAPI token, GA4, TikTok, Google Ads, Search Console code.
5. কনটেন্ট → হোমপেজ বিল্ডার, মেনু, পেজ (privacy, terms, return policy).
6. Place one COD test order end to end, then cancel it.

## 6. Every later deploy

```bash
git pull
cd apps/backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force   # new admin permissions
php artisan optimize && php artisan filament:optimize
php artisan queue:restart

cd ../storefront
pnpm install --frozen-lockfile
rm -rf .next/cache && pnpm build
# restart the storefront process
```

## 7. CI

`.github/workflows/ci.yml` runs on every push to `main` and every pull request: backend tests, Pint, an API-types drift check, then the storefront type check, lint, build and Playwright E2E against a seeded SQLite backend. Keep it green before deploying.

## 8. Backups and monitoring

- Nightly `mysqldump` plus `apps/backend/storage/app/public` (product images) to off-server storage.
- Health checks: `GET https://api.example.com/up` (Laravel) and `GET https://api.example.com/api/v1/health`.
- Logs: `apps/backend/storage/logs/laravel.log` (set up `logrotate` or `LOG_STACK=daily`).
