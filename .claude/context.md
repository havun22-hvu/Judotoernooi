# Context - JudoToernooi

> Technische details en specificaties

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

## Project Structuur

```
laravel/
├── app/
│   ├── Enums/           # Leeftijdsklasse, Band, Geslacht, etc.
│   ├── Models/          # Toernooi, Judoka, Poule, Wedstrijd, etc.
│   ├── Services/        # PouleIndeling, Weging, Import, etc.
│   └── Http/Controllers/
├── database/migrations/ # Database schema
├── config/toernooi.php  # Toernooi configuratie
└── docs/                # Uitgebreide documentatie
```

## Database

| Omgeving | Type | Database | User |
|----------|------|----------|------|
| Local | SQLite | database/database.sqlite | - |
| Server | MySQL | judo_toernooi | judotoernooi |

## Local Development

```bash
cd laravel
cp .env.example .env
# .env: DB_CONNECTION=sqlite, SESSION_DRIVER=file, CACHE_STORE=file
touch database/database.sqlite
php artisan key:generate
php artisan migrate
php artisan serve --port=8001
```

**Let op:**
- Local: SQLite (geen MySQL nodig)
- Local: Login zonder wachtwoord (APP_ENV=local)
- Poort 8001 (8000 is Herdenkingsportaal)

## Import Data

CSV formaat voor judoka's:
```csv
Naam,Geboortedatum,Geslacht,Band,Club,Gewicht
Jan Jansen,2015-03-15,M,Oranje,Cees Veen,32.5
```

## Test Accounts (Local)

| Rol | URL | PIN |
|-----|-----|-----|
| Coach Havun | `/school/2JzfLbjWXvuv` | `08130` |

## Deploy Commands

```bash
cd /var/www/judotoernooi/laravel
git pull
composer install --no-dev
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

## Documentatie

Uitgebreide docs in `laravel/docs/`:
- INSTALLATIE.md - Server setup
- CONFIGURATIE.md - App configuratie
- GEBRUIKERSHANDLEIDING.md - Hoe te gebruiken
- DATABASE.md - Schema uitleg
- API.md - REST API documentatie
