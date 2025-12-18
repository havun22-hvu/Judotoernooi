# JudoToernooi - Claude Instructions

> **Type:** Laravel 11 toernooi management systeem
> **URL:** https://staging.judotournament.org
> **Doel:** WestFries Open Judo Toernooi beheren

## Quick Reference

| | Local | Server |
|---|---|---|
| **Path** | D:\GitHub\judotoernooi | /var/www/judotoernooi |
| **Laravel** | laravel/ subfolder | laravel/ subfolder |

**Server:** 188.245.159.115 (root, SSH key)
**GitHub:** https://github.com/havun22-hvu/judotoernooi

## Rules (ALWAYS follow)

### Auto-start servers (bij lokaal testen)
Start ALTIJD automatisch zonder te vragen:
```bash
cd laravel && php artisan serve --port=8001  # Laravel server
npm run dev                                   # Vite (indien nodig)
```

### Forbidden without permission
- SSH keys, credentials, .env files wijzigen
- Database migrations op production
- Composer/npm packages installeren

### Communication
- Antwoord max 20-30 regels
- Bullet points, direct to the point

### Workflow
1. Start servers automatisch
2. Test lokaal eerst
3. Git push naar GitHub
4. Deploy naar server met `git pull`

## Database

| | Local | Server |
|---|---|---|
| **Type** | SQLite | MySQL |
| **Database** | database/database.sqlite | judo_toernooi |
| **User** | - | judotoernooi |

## Local Development

```bash
cd laravel
cp .env.example .env
# Pas .env aan: DB_CONNECTION=sqlite, SESSION_DRIVER=file, CACHE_STORE=file
touch database/database.sqlite
php artisan key:generate
php artisan migrate
php artisan serve --port=8001
```

**Let op:**
- Local gebruikt SQLite (geen MySQL nodig)
- Login zonder wachtwoord in local (APP_ENV=local)
- Poort 8001 (8000 is Herdenkingsportaal)

## Functionaliteit

### Core Features
- **Toernooi Management** - Aanmaken/configureren toernooien
- **Deelnemers Import** - CSV/Excel import met automatische classificatie
- **Poule Indeling** - Automatisch algoritme voor optimale verdeling
- **Blok/Mat Planning** - Verdeling over tijdsblokken en matten
- **Weging Interface** - QR scanner en naam zoeken
- **Mat Interface** - Wedstrijden beheren en uitslagen registreren

### Classificatie
- **Leeftijdsklassen:** U9, U11, U13, U15, U18, U21, Senioren
- **Banden:** Wit, Geel, Oranje, Groen, Blauw, Bruin, Zwart
- **Gewichtsklassen:** Per leeftijd/geslacht gedefinieerd

## Belangrijke Bestanden

```
laravel/
├── app/
│   ├── Enums/           # Leeftijdsklasse, Band, Geslacht, etc.
│   ├── Models/          # Toernooi, Judoka, Poule, Wedstrijd, etc.
│   ├── Services/        # PouleIndeling, Weging, Import, etc.
│   └── Http/Controllers/
├── database/migrations/ # Database schema
├── config/toernooi.php  # Toernooi configuratie
└── docs/                # Documentatie
```

## Deploy Commands

```bash
# Op server
cd /var/www/judotoernooi/laravel
git pull
composer install --no-dev
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

## Import Data

CSV formaat voor judoka's:
```
Naam,Geboortedatum,Geslacht,Band,Club,Gewicht
Jan Jansen,2015-03-15,M,Oranje,Cees Veen,32.5
```

## Test Accounts (Local)

| Rol | URL | Code/PIN |
|-----|-----|----------|
| **Coach Havun** | `/school/2JzfLbjWXvuv` | PIN: `08130` |

## Documentatie

Zie `laravel/docs/` voor:
- INSTALLATIE.md - Server setup
- CONFIGURATIE.md - App configuratie
- GEBRUIKERSHANDLEIDING.md - Hoe te gebruiken
- DATABASE.md - Schema uitleg
- API.md - REST API documentatie
