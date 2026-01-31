# JudoToernooi - Documentatie

Welkom bij de documentatie van het JudoToernooi Management Systeem.

## Structuur

```
docs/
├── 1-GETTING-STARTED/     # Installatie en configuratie
├── 2-FEATURES/            # Feature documentatie
│   ├── BETALINGEN.md      # Mollie integratie
│   └── ELIMINATIE/        # Double elimination systeem
├── 3-TECHNICAL/           # API, database, ontwikkelaar
├── 4-PLANNING/            # Toekomstige features
├── 5-REGLEMENT/           # JBN reglementen
└── 6-INTERNAL/            # Interne docs, lessons learned
```

## Inhoud

### 1. Getting Started
- [Installatie](./1-GETTING-STARTED/INSTALLATIE.md) - Server setup
- [Configuratie](./1-GETTING-STARTED/CONFIGURATIE.md) - App configuratie

### 2. Features
- [Gebruikershandleiding](./2-FEATURES/GEBRUIKERSHANDLEIDING.md) - Handleiding
- [Import](./2-FEATURES/IMPORT.md) - CSV/Excel import met warnings
- [Classificatie & Poule Indeling](./2-FEATURES/CLASSIFICATIE.md) - Algoritme voor poule-indeling
- [Freemium Model](./2-FEATURES/FREEMIUM.md) - Gratis tier, betaalde staffels, upgrade flow
- [Betalingen](./2-FEATURES/BETALINGEN.md) - Mollie integratie voor inschrijfgeld
- [Blokverdeling](./2-FEATURES/BLOKVERDELING.md) - Categorieën → blokken
- [Wedstrijdschema](./2-FEATURES/WEDSTRIJDSCHEMA.md) - Punten en kruisfinales
- [Chat](./2-FEATURES/CHAT.md) - Realtime chat met Laravel Reverb
- [Interfaces](./2-FEATURES/INTERFACES.md) - PWA's en device binding
- [Noodplan Handleiding](./2-FEATURES/NOODPLAN-HANDLEIDING.md) - Praktische gids voor organisatoren
- [Eliminatie Systeem](./2-FEATURES/ELIMINATIE/README.md) - Double elimination
  - [Formules](./2-FEATURES/ELIMINATIE/FORMULES.md)
  - [Slot Systeem](./2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md)
  - [Test Matrix](./2-FEATURES/ELIMINATIE/TEST-MATRIX.md)

### 3. Technical
- [URL Structuur](./URL-STRUCTUUR.md) - Routes en authenticatie schema
- [API Documentatie](./3-TECHNICAL/API.md) - REST endpoints
- [Database Schema](./3-TECHNICAL/DATABASE.md) - Tabelstructuur
- [Ontwikkelaar Gids](./3-TECHNICAL/ONTWIKKELAAR.md) - Dev info
- [Redundantie & Veiligheid](./3-TECHNICAL/REDUNDANTIE.md) - Enterprise fail-safe architectuur

### 4. Planning
- [Authenticatie Systeem](./4-PLANNING/PLANNING_AUTHENTICATIE_SYSTEEM.md) - Device binding
- [Noodplan](./4-PLANNING/PLANNING_NOODPLAN.md) - Offline fallback
- [Intropage & PWA](./4-PLANNING/PLANNING_INTROPAGE_PWA.md) - Publieke website vs live app
- [Multi-tenancy](./4-PLANNING/PLANNING_MULTI_TENANCY.md) - Meerdere organisatoren

### 5. Reglement
- [JBN Reglement 2026](./5-REGLEMENT/JBN-REGLEMENT-2026.md)

### 6. Internal
- [Lessons Learned](./6-INTERNAL/LESSONS-LEARNED-AI-SAMENWERKING.md) - AI samenwerking
- [Rollen Hierarchie](./6-INTERNAL/ROLLEN_HIERARCHIE.md) - Gebruikersrollen
- [Evaluatie Demo 21 Jan](./6-INTERNAL/EVALUATIE-DEMO-21-JAN.md) - Post-mortem demo

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
php artisan serve --port=8007
```

## Changelog

Zie [CHANGELOG.md](./CHANGELOG.md) voor wijzigingsgeschiedenis.

## Architectuur

```
laravel/
├── app/
│   ├── Enums/           # Band, Geslacht (Leeftijdsklasse is deprecated)
│   ├── Http/Controllers/
│   ├── Models/          # Toernooi, Judoka, Betaling, etc.
│   └── Services/        # PouleIndelingService, MollieService, etc.
├── config/
│   ├── toernooi.php     # Toernooi configuratie
│   └── services.php     # Mollie API config
├── database/migrations/
├── docs/                # Deze documentatie
├── resources/views/     # Blade templates
└── routes/web.php
```

## Contact

- **Platform**: JudoToernooi (judotournament.org)
- **GitHub**: https://github.com/havun22-hvu/judotoernooi
