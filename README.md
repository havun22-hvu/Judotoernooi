# JudoToernooi Management Systeem

[![CI](https://github.com/havun22-hvu/judotoernooi/actions/workflows/ci.yml/badge.svg)](https://github.com/havun22-hvu/judotoernooi/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11-red.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/License-Proprietary-orange.svg)](LICENSE)

**Enterprise-grade** tournament management platform for judo organizations.

> 🏆 **Production URL**: [judotournament.org](https://judotournament.org)

## Software Quality

| Category | Status |
|----------|--------|
| **Automated Tests** | ✅ Unit + Feature tests |
| **CI/CD Pipeline** | ✅ GitHub Actions |
| **Code Style** | ✅ Laravel Pint |
| **Error Handling** | ✅ Custom exceptions + Circuit Breaker |
| **Rate Limiting** | ✅ API protection |
| **Health Monitoring** | ✅ /health endpoint |
| **Documentation** | ✅ Full technical docs |

### Architecture Highlights

- **Circuit Breaker Pattern** - Prevents cascade failures on external services
- **Result Object Pattern** - Clean error handling without exceptions
- **Form Requests** - Centralized validation with Dutch messages
- **Custom Exceptions** - Structured error categorization
- **Error Notifications** - Real-time alerts to HavunCore

## Snel Starten

```bash
cd laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Run Tests

```bash
cd laravel
php artisan test
php artisan test --coverage  # With coverage report
```

## Organisatie

- **Toernooi**: WestFries Open JudoToernooi
- **Organisator**: Judoschool Cees Veen
- **Platform**: Laravel Web Applicatie

## Functionaliteit

### 1. Voorbereidingok
