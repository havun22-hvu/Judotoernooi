# WestFries Open JudoToernooi - Documentatie

Welkom bij de documentatie van het WestFries Open JudoToernooi Management Systeem.

## Inhoud

1. [Installatie](./INSTALLATIE.md) - Hoe het systeem te installeren
2. [Configuratie](./CONFIGURATIE.md) - Configuratie opties
3. [Gebruikershandleiding](./GEBRUIKERSHANDLEIDING.md) - Handleiding voor gebruikers
4. [API Documentatie](./API.md) - REST API endpoints
5. [Database Schema](./DATABASE.md) - Database structuur
6. [Ontwikkelaar Gids](./ONTWIKKELAAR.md) - Informatie voor ontwikkelaars

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
├── resources/
│   └── views/           # Blade templates
├── routes/              # Route definities
└── tests/               # PHPUnit tests
```

## Contact

- **Organisatie**: Judoschool Cees Veen
- **Toernooi**: WestFries Open JudoToernooi
