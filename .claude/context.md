# JudoToernooi Context

> Multi-tenant judo toernooi platform

## Overzicht

| Aspect | Waarde |
|--------|--------|
| **Type** | Laravel 11 + Blade + Alpine.js + Tailwind |
| **URL** | judotournament.org |
| **Status** | Production (live) |

> "WestFries Open" is een specifiek toernooi van klant Cees Veen, niet de naam van het platform.

## Omgevingen

| Omgeving | URL | Pad | Database |
|----------|-----|-----|----------|
| **Local** | localhost:8007 | `D:\GitHub\JudoToernooi\laravel` | SQLite |
| **Staging** | (geen domein) | `/var/www/staging.judotoernooi/laravel` | MySQL |
| **Production** | judotournament.org | `/var/www/judotoernooi/laravel` | MySQL |

```bash
php artisan serve --port=8007
```

## Documentatie Structuur

| Bestand | Inhoud |
|---------|--------|
| `context.md` | Dit bestand - overzicht |
| `features.md` | Functionaliteit, classificatie, auth |
| `mollie.md` | Betalingen configuratie |
| `deploy.md` | Deploy instructies |
| `handover.md` | Laatste sessie info |
| `smallwork.md` | Kleine fixes (archief in `archive/`) |
| `handover/` | Gedetailleerde sessie handovers |

## Core Features

- **Toernooi Management** - Aanmaken/configureren
- **Deelnemers Import** - CSV/Excel met auto-classificatie
- **Poule Indeling** - Automatisch algoritme
- **Mat Interface** - Wedstrijden en uitslagen
- **Eliminatie** - Double elimination

## Gerelateerde Docs

- `laravel/docs/` - Project documentatie
- `HavunCore/docs/kb/patterns/mollie-payments.md`
