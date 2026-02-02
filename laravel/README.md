# JudoToernooi

[![CI](https://github.com/havun22-hvu/judotoernooi/actions/workflows/ci.yml/badge.svg)](https://github.com/havun22-hvu/judotoernooi/actions/workflows/ci.yml)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![License](https://img.shields.io/badge/License-Proprietary-blue)](LICENSE)

> SaaS platform voor judo toernooi management - professionele software voor judoscholen en organisatoren.

## ✨ Features

| Feature | Beschrijving |
|---------|--------------|
| **Toernooi Management** | Aanmaken, configureren en beheren van toernooien |
| **Deelnemers Import** | CSV/Excel import met automatische classificatie |
| **Poule Indeling** | Intelligent algoritme voor optimale verdeling |
| **Blok/Mat Planning** | Verdeling over tijdsblokken en matten |
| **Weging Interface** | QR scanner en naam zoeken (tablet-friendly) |
| **Mat Interface** | Real-time wedstrijden en uitslagen |
| **Spreker Interface** | Prijsuitreiking met notities |
| **Live Scorebord** | Publiek scorebord met WebSocket updates |
| **Mollie Betalingen** | Geïntegreerde online betalingen |

## 🚀 Quick Start

```bash
# Clone & install
git clone https://github.com/havun22-hvu/judotoernooi.git
cd judotoernooi/laravel
composer install

# Configure
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate

# Start
php artisan serve --port=8007
```

## 🧪 Testing

```bash
# Run all tests
php artisan test

# With coverage
php artisan test --coverage

# Specific test
php artisan test --filter=SecurityHeadersTest
```

## 🏗️ Architecture

```
app/
├── Exceptions/          # Custom exception classes
│   ├── JudoToernooiException.php
│   ├── MollieException.php
│   └── ImportException.php
├── Http/
│   ├── Controllers/     # Request handling
│   └── Middleware/      # Security headers, auth
├── Models/              # Eloquent models
├── Services/            # Business logic
│   ├── MollieService.php
│   ├── ImportService.php
│   └── DynamischeIndelingService.php
└── Support/             # Utilities
    ├── CircuitBreaker.php
    └── Result.php
```

## 🔒 Security

- **Security Headers**: X-Frame-Options, CSP, HSTS, XSS Protection
- **Rate Limiting**: Login attempts, API requests
- **CSRF Protection**: All form submissions
- **Input Validation**: Server-side validation on all endpoints

## 📚 Documentation

| Document | Beschrijving |
|----------|--------------|
| [Gebruikershandleiding](./docs/1-GETTING-STARTED/GEBRUIKERSHANDLEIDING.md) | Voor eindgebruikers |
| [API Documentatie](./docs/3-TECHNICAL/API.md) | REST API reference |
| [Code Standaarden](./docs/3-DEVELOPMENT/CODE-STANDAARDEN.md) | Development guidelines |
| [Betalingen](./docs/2-FEATURES/BETALINGEN.md) | Mollie integratie |

## 🛠️ Tech Stack

| Component | Technologie |
|-----------|-------------|
| **Backend** | Laravel 11, PHP 8.2+ |
| **Frontend** | Blade, Alpine.js, Tailwind CSS |
| **Database** | MySQL 8.0+ (prod), SQLite (dev) |
| **Real-time** | Laravel Reverb (WebSockets) |
| **Payments** | Mollie Connect + Platform |
| **CI/CD** | GitHub Actions |
| **Analysis** | PHPStan Level 5 |

## 📊 Quality Metrics

- ✅ Automated testing (Unit + Feature)
- ✅ Static analysis (PHPStan)
- ✅ Code style (Laravel Pint)
- ✅ Security scanning (Composer Audit)
- ✅ Continuous Integration

## 📄 License

Proprietary software - developed for [JudoTournament.org](https://judotournament.org)

---

*Built with ❤️ for the judo community*
