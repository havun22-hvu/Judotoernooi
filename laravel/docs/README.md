# WestFries Open JudoToernooi - Documentatie

Welkom bij de documentatie van het WestFries Open JudoToernooi Management Systeem.

## Structuur

```
docs/
├── 1-GETTING-STARTED/     # Installatie en configuratie
├── 2-FEATURES/            # Feature documentatie
│   └── ELIMINATIE/        # Double elimination systeem
├── 3-TECHNICAL/           # API, database, ontwikkelaar
├── 4-PLANNING/            # Toekomstige features
├── 5-REGLEMENT/           # JBN reglementen
├── 6-INTERNAL/            # Interne docs, lessons learned
└── archive/               # Verouderde documentatie
```

## Inhoud

### 1. Getting Started
- [Installatie](./1-GETTING-STARTED/INSTALLATIE.md) - Server setup
- [Configuratie](./1-GETTING-STARTED/CONFIGURATIE.md) - App configuratie

### 2. Features
- [Gebruikershandleiding](./2-FEATURES/GEBRUIKERSHANDLEIDING.md) - Handleiding
- [Blokverdeling](./2-FEATURES/BLOKVERDELING.md) - Categorieën → blokken
- [Wedstrijdschema](./2-FEATURES/WEDSTRIJDSCHEMA.md) - Punten en kruisfinales
- [Eliminatie Systeem](./2-FEATURES/ELIMINATIE/README.md) - Double elimination
  - [Formules](./2-FEATURES/ELIMINATIE/FORMULES.md)
  - [Slot Systeem](./2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md)
  - [Test Matrix](./2-FEATURES/ELIMINATIE/TEST-MATRIX.md)

### 3. Technical
- [API Documentatie](./3-TECHNICAL/API.md) - REST endpoints
- [Database Schema](./3-TECHNICAL/DATABASE.md) - Tabelstructuur
- [Ontwikkelaar Gids](./3-TECHNICAL/ONTWIKKELAAR.md) - Dev info

### 4. Planning
- [Authenticatie Systeem](./4-PLANNING/PLANNING_AUTHENTICATIE_SYSTEEM.md)
- [Noodplan](./4-PLANNING/PLANNING_NOODPLAN.md)

### 5. Reglement
- [JBN Reglement 2026](./5-REGLEMENT/JBN-REGLEMENT-2026.md)

### 6. Internal
- [Lessons Learned](./6-INTERNAL/LESSONS-LEARNED-AI-SAMENWERKING.md) - AI samenwerking
- [Rollen Hierarchie](./6-INTERNAL/ROLLEN_HIERARCHIE.md) - Gebruikersrollen

## Snelle Start

```bash
# Clone
git clone https://github.com/havun22-hvu/judotoernooi.git
cd judotoernooi/laravel

# Install
composer install
npm install

# Configure
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Run
php artisan serve --port=8001
```

## Changelog

Zie [CHANGELOG.md](./CHANGELOG.md) voor wijzigingsgeschiedenis.

## Architectuur

```
laravel/
├── app/
│   ├── Enums/           # Band, Geslacht, Leeftijdsklasse
│   ├── Http/Controllers/
│   ├── Models/          # Eloquent models
│   └── Services/        # Business logic (EliminatieService, etc.)
├── config/toernooi.php  # Toernooi configuratie
├── database/migrations/
├── docs/                # Deze documentatie
├── resources/views/     # Blade templates
└── routes/web.php
```

## Contact

- **Organisatie**: Judoschool Cees Veen
- **Toernooi**: WestFries Open JudoToernooi
- **GitHub**: https://github.com/havun22-hvu/judotoernooi
