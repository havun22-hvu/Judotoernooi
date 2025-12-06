# WestFries Open JudoToernooi - Laravel

Laravel implementatie van het WestFries Open JudoToernooi Management Systeem.

## Snel Starten

```bash
# Installeer dependencies
composer install

# Configuratie
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate

# Start server
php artisan serve
```

## Functionaliteit

- **Toernooi Management** - Aanmaken en configureren van toernooien
- **Deelnemers Import** - CSV/Excel import met automatische classificatie
- **Poule Indeling** - Automatisch algoritme voor optimale verdeling
- **Blok/Mat Planning** - Verdeling over tijdsblokken en matten
- **Weging Interface** - QR scanner en naam zoeken
- **Mat Interface** - Wedstrijden beheren en uitslagen registreren
- **API** - REST API voor externe integraties

## Documentatie

Zie de [docs](./docs) map voor volledige documentatie:

- [Installatie](./docs/INSTALLATIE.md)
- [Configuratie](./docs/CONFIGURATIE.md)
- [Gebruikershandleiding](./docs/GEBRUIKERSHANDLEIDING.md)
- [API](./docs/API.md)
- [Database Schema](./docs/DATABASE.md)
- [Ontwikkelaar Gids](./docs/ONTWIKKELAAR.md)

## Technologie Stack

- **Framework**: Laravel 11
- **PHP**: 8.2+
- **Database**: MySQL 8.0+
- **Frontend**: Blade + Alpine.js + Tailwind CSS

## Licentie

Ontwikkeld voor Judoschool Cees Veen.
