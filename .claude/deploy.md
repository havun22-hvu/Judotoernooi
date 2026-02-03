# Deploy Instructies

## Local Development

```bash
cd laravel
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve --port=8007
```

## Deploy (Staging)

```bash
ssh root@188.245.159.115
cd /var/www/staging.judotoernooi/laravel

git pull origin main
composer install --no-dev
npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```

## Deploy (Production)

```bash
ssh root@188.245.159.115
cd /var/www/judotoernooi/laravel

git pull origin main
composer install --no-dev
npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```

## One-liner (copy-paste ready)

**Staging:**
```bash
cd /var/www/staging.judotoernooi/laravel && git pull origin main && composer install --no-dev && npm run build && php artisan migrate --force && php artisan optimize:clear && php artisan optimize
```

**Production:**
```bash
cd /var/www/judotoernooi/laravel && git pull origin main && composer install --no-dev && npm run build && php artisan migrate --force && php artisan optimize:clear && php artisan optimize
```

> **Let op:** Zonder cache:clear kunnen gebruikers in redirect loop komen bij login!

## Backup Systeem (Automatisch)

| Type | Frequentie | Locatie | Retentie |
|------|-----------|---------|----------|
| **Wedstrijddag** | Elke 1 min | `/var/backups/havun/wedstrijddag/` | 60 min |
| **Hot backup** | Elke 5 min | `/var/backups/havun/hot/` | 2 uur |
| **Dagelijks** | 03:00 | Hetzner Storage Box | Onbeperkt |

**Databases in backup:**
- `judo_toernooi` (production)
- `staging_judo_toernooi` (staging)

**Herstellen:**
```bash
# Laatste hot backup
gunzip < /var/backups/havun/hot/judo_toernooi_TIMESTAMP.sql.gz | mysql judo_toernooi

# Dagelijkse backup (van Hetzner)
# Download eerst via sftp van u510616.your-storagebox.de
```
