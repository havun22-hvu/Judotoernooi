# WestFries Open JudoToernooi - Documentatie

Welkom bij de documentatie van het WestFries Open JudoToernooi Management Systeem.

## Inhoud

### 1. Getting Started
- [Installatie](./1-GETTING-STARTED/INSTALLATIE.md) - Hoe het systeem te installeren
- [Configuratie](./1-GETTING-STARTED/CONFIGURATIE.md) - Configuratie opties

### 2. Features
- [Gebruikershandleiding](./2-FEATURES/GEBRUIKERSHANDLEIDING.md) - Handleiding voor gebruikers
- [Blokverdeling](./2-FEATURES/BLOKVERDELING.md) - Categorieën verdelen over blokken
- [Wedstrijdschema](./2-FEATURES/WEDSTRIJDSCHEMA.md) - Wedstrijdschema's, punten en kruisfinales
- [Eliminatie Systeem](./2-FEATURES/ELIMINATIE_SYSTEEM.md) - Double elimination bracket systeem

### 3. Technical
- [API Documentatie](./3-TECHNICAL/API.md) - REST API endpoints
- [Database Schema](./3-TECHNICAL/DATABASE.md) - Database structuur
- [Ontwikkelaar Gids](./3-TECHNICAL/ONTWIKKELAAR.md) - Informatie voor ontwikkelaars

### 4. Deployment
- Server deployment instructies (zie INSTALLATIE.md)

## Snelle Start

```bash
# Clone repository
git clone https://github.com/judoschool-cees-veen/judo-toernooi.git
cd judo-toernooi/laravel

# Installeer dependencies
composer install
npm install

# Configuratie
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate
php artisan db:seed

# Start development server
php artisan serve
```

## Architectuur Overzicht

```
laravel/
├── app/
│   ├── Enums/           # PHP 8.1+ Enums (Band, Geslacht, etc.)
│   ├── Http/
│   │   ├── Controllers/ # Request handlers
│   │   └── Requests/    # Form validation
│   ├── Models/          # Eloquent models
│   └── Services/        # Business logic
├── config/              # Configuratie bestanden
├── database/
│   ├── migrations/      # Database migraties
│   └── seeders/         # Test data seeders
├── docs/                # Documentatie (deze map)
│   ├── 1-GETTING-STARTED/
│   ├── 2-FEATURES/
│   ├── 3-TECHNICAL/
│   ├── 4-DEPLOYMENT/
│   └── archive/
├── resources/
│   └── views/           # Blade templates
├── routes/              # Route definities
└── tests/               # PHPUnit tests
```

## Contact

- **Organisatie**: Judoschool Cees Veen
- **Toernooi**: WestFries Open JudoToernooi
