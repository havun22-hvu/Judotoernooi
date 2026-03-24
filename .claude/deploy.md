# Deploy Instructies

## Serverstructuur (24 mrt 2026)

Beide omgevingen gebruiken een **symlink** naar een volledige git repo clone:

```
/var/www/judotoernooi/
├── repo-prod/          ← git clone (volledige repo)
│   └── laravel/        ← Laravel app
├── repo-staging/       ← git clone (volledige repo)
│   └── laravel/        ← Laravel app
├── laravel -> repo-prod/laravel     ← symlink (nginx root)
└── staging -> repo-staging/laravel  ← symlink (nginx root)
```

**Deploy = `git pull` in de repo map, NIET in de symlink.**

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
cd /var/www/judotoernooi/repo-staging
git pull origin main
cd laravel
composer install --no-dev
npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```

## Deploy (Production)

```bash
ssh root@188.245.159.115
cd /var/www/judotoernooi/repo-prod
git pull origin main
cd laravel
composer install --no-dev
npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```

## One-liner (copy-paste ready)

**Staging:**
```bash
ssh root@188.245.159.115 "cd /var/www/judotoernooi/repo-staging && git pull && cd laravel && php artisan optimize:clear && php artisan optimize"
```

**Production:**
```bash
ssh root@188.245.159.115 "cd /var/www/judotoernooi/repo-prod && git pull && cd laravel && php artisan optimize:clear && php artisan optimize"
```

**Quick deploy (alleen views/code, geen migratie):**
```bash
ssh root@188.245.159.115 "cd /var/www/judotoernooi/repo-staging && git pull && cd laravel && php artisan view:clear && php artisan cache:clear"
```

> **Let op:** Zonder cache:clear kunnen gebruikers in redirect loop komen bij login!

## Backup Systeem (Automatisch)

| Type | Frequentie | Locatie | Retentie |
|------|-----------|---------|----------|
| **Wedstrijddag** | Elke 1 min | `/var/backups/havun/wedstrijddag/` | 60 min |
| **Hot backup** | Elke 5 min | `/var/backups/havun/hot/` | 2 uur |
| **Dagelijks** | 03:00 | Hetzner Storage Box | Onbeperkt |
| **Milestones** | Handmatig | `/var/backups/havun/milestones/` | Onbeperkt |

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
