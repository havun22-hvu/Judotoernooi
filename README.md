# WestFries Open JudoToernooi Management Systeem

Management systeem voor het organiseren van het WestFries Open JudoToernooi door Judoschool Cees Veen.

## Snel Starten

```bash
cd laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Organisatie

- **Toernooi**: WestFries Open JudoToernooi
- **Organisator**: Judoschool Cees Veen
- **Platform**: Laravel Web Applicatie

## Functionaliteit

### 1. Voorbereiding
- Toernooi configuratie (matten, tijdsblokken, leeftijdsklassen, gewichtsklassen)
- Deelnemerslijst importeren (CSV/Excel)
- Automatische judoka-code generatie
- Data validatie

### 2. Poule-indeling
- Automatische poule-indeling op basis van:
  - Leeftijdsklasse
  - Gewichtsklasse
  - Bandkleur
  - Geslacht
- Optimale verdeling (3-6 judoka's per poule)
- Handmatige aanpassingen mogelijk

### 3. Blok/Mat Planning
- Verdeling van poules over tijdsblokken
- Toewijzing aan matten
- Zaaloverzicht

### 4. Toernooidag
- **Weging Interface** met QR scanner
- **Gewichtscontrole** met tolerantie
- **Mat Interface** voor wedstrijden en uitslagen
  - WP/JP scoring met dropdown (0, 5, 7, 10)
  - Automatische plaats berekening na alle wedstrijden
  - Afronden knop stuurt poule naar spreker
- **Spreker Interface** voor prijsuitreiking
  - Begint met lege wachtpagina
  - Afgeronde poules verschijnen automatisch (oudste eerst)
  - Naam (club), WP, JP, plaats met medaille-kleuren
  - Afgerond knop archiveert poule na prijsuitreiking

## Leeftijdsklassen

| Klasse | Leeftijd |
|--------|----------|
| Mini's | < 8 jaar |
| A-pupillen | < 10 jaar |
| B-pupillen | < 12 jaar |
| Dames -15 | < 15 jaar |
| Heren -15 | < 15 jaar |

## Projectstructuur

```
laravel/
├── app/
│   ├── Enums/           # PHP Enums (Band, Geslacht, etc.)
│   ├── Http/Controllers # Request handlers
│   ├── Models/          # Eloquent models
│   └── Services/        # Business logic
├── config/              # Configuratie
├── database/migrations/ # Database schema
├── docs/                # Documentatie
├── routes/              # Route definities
└── resources/views/     # Blade templates
```

## Documentatie

Zie [`laravel/docs/`](./laravel/docs/) voor:
- [Installatie](./laravel/docs/INSTALLATIE.md)
- [Configuratie](./laravel/docs/CONFIGURATIE.md)
- [Gebruikershandleiding](./laravel/docs/GEBRUIKERSHANDLEIDING.md)
- [API](./laravel/docs/API.md)
- [Database Schema](./laravel/docs/DATABASE.md)

## Technologie

- **Framework**: Laravel 11
- **PHP**: 8.2+
- **Database**: MySQL 8.0+

## Licentie

Ontwikkeld voor Judoschool Cees Veen
