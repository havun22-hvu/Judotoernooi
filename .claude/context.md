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

## Mollie Betalingen (Januari 2026)

### Architectuur
- **Mollie Connect** - Organisatoren koppelen eigen Mollie account via OAuth
- **Platform fallback** - Voor organisatoren zonder eigen account (toekomst)
- **Simulatie mode** - Testen zonder echte Mollie keys (staging)

### Bestanden
```
app/
├── Services/MollieService.php      # Dual mode: Connect + Platform
├── Http/Controllers/
│   ├── MollieController.php        # OAuth flow + webhook + simulatie
│   └── CoachPortalController.php   # Afrekenen flow
├── Models/Betaling.php             # Payment records
└── Console/Commands/ResetStaging.php
resources/views/pages/
├── betaling/simulate.blade.php     # iDEAL simulatie pagina
└── toernooi/edit.blade.php         # Mollie Connect UI
```

### Database velden
**toernooien tabel:**
- `betaling_actief` - boolean
- `inschrijfgeld` - decimal(8,2)
- `mollie_mode` - enum (connect/platform)
- `mollie_access_token` - encrypted
- `mollie_refresh_token` - encrypted
- `mollie_token_expires_at` - datetime
- `mollie_organization_id` - string
- `mollie_organization_name` - string
- `mollie_onboarded` - boolean
- `platform_toeslag` - decimal (niet in gebruik)

**betalingen tabel:**
- `toernooi_id`, `club_id`
- `mollie_payment_id` - unique
- `bedrag`, `aantal_judokas`
- `status` - enum (open/pending/paid/failed/expired/canceled)
- `betaald_op` - timestamp

**judokas tabel:**
- `betaling_id` - nullable foreign key
- `betaald_op` - timestamp

### Routes
```
GET  /toernooi/{id}/mollie/authorize  → OAuth start
GET  /mollie/callback                 → OAuth callback
POST /toernooi/{id}/mollie/disconnect → Ontkoppelen
POST /mollie/webhook                  → Payment updates (CSRF excluded!)
GET  /betaling/simulate               → Simulatie pagina
POST /betaling/simulate               → Simulatie afronden
```

### Env variabelen (nog niet ingesteld)
```
MOLLIE_KEY=live_xxx              # Platform fallback
MOLLIE_CLIENT_ID=xxx             # OAuth app
MOLLIE_CLIENT_SECRET=xxx         # OAuth app
```

### Status (3 januari 2026)
- ✅ Migration gedraaid
- ✅ MollieService met dual mode
- ✅ OAuth flow UI
- ✅ Simulatie mode werkend
- ✅ Coach afrekenen flow
- ⏳ Woensdag: Cees' Mollie test account koppelen

## Documentatie

Uitgebreide docs in `laravel/docs/`:
- INSTALLATIE.md - Server setup
- CONFIGURATIE.md - App configuratie
- GEBRUIKERSHANDLEIDING.md - Hoe te gebruiken
- DATABASE.md - Schema uitleg
- API.md - REST API documentatie
