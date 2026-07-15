---
title: Migraties, caching, debugging en deployment
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Migraties, caching, debugging en deployment

> Onderdeel van [Ontwikkelaar Gids](../ONTWIKKELAAR.md).

## Database Migraties

### Nieuwe Migratie

```bash
php artisan make:migration add_column_to_judokas_table
```

### Rollback

```bash
php artisan migrate:rollback --step=1
```

## Caching

Voor productie:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Debugging

### Logs

```bash
tail -f storage/logs/laravel.log
```

### Tinker

```bash
php artisan tinker

>>> $toernooi = Toernooi::first();
>>> $toernooi->judokas()->count();
>>> $toernooi->poules()->sum('aantal_wedstrijden');
```

## Deployment

Zie [INSTALLATIE.md](../../1-GETTING-STARTED/INSTALLATIE.md) voor volledige instructies.

Checklist:
1. `composer install --no-dev`
2. `npm run build`
3. `php artisan migrate --force`
4. `php artisan config:cache`
5. `php artisan route:cache`
6. `php artisan view:cache`
