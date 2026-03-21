# Changelog

All notable changes to JudoToernooi will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Comprehensive test suite with Model Factories (37 tests, 110 assertions)
- CI/CD pipeline with GitHub Actions (tests, code quality, security audit)
- Security Headers Middleware (CSP, HSTS, X-Frame-Options)
- PHPStan static analysis (Level 5)
- Professional API documentation with examples
- Circuit Breaker pattern for external services
- Result object pattern for error handling
- Custom exception classes (JudoToernooiException, MollieException, ImportException, ExternalServiceException)
- Production validation command (`php artisan validate:production`)
- Error notification service for critical production errors
- Vite asset bundling (Tailwind CSS + Alpine.js)

### Changed
- Improved error handling across all services
- Enhanced spreker notities for tablet/iPad use
- Better print layout for wedstrijdschema

### Security
- Added X-Frame-Options header (clickjacking protection)
- Added X-Content-Type-Options header (MIME sniffing protection)
- Added Content-Security-Policy header
- Added Strict-Transport-Security header (HTTPS enforcement)
- Added Permissions-Policy header (feature restrictions)
- Rate limiting on login attempts (5/minute)

## [1.0.0] - 2025-10-25

### Added
- Initial release for WestFries Open 2025
- Toernooi management system
- Deelnemers import (CSV/Excel)
- Automatische poule indeling
- Blok/Mat planning
- Weging interface with QR scanner
- Mat interface for score registration
- Spreker interface for prize ceremony
- Live scoreboard with WebSocket
- Mollie payment integration (Connect + Platform)
- Multi-tenant SaaS architecture
- Organisator dashboard
- Sitebeheerder admin panel

### Technical
- Laravel 11 framework
- Alpine.js + Tailwind CSS frontend
- Laravel Reverb for WebSockets
- MySQL database (production)
- SQLite database (development)

---

## Version History

| Version | Date | Highlights |
|---------|------|------------|
| 1.0.0 | 2025-10-25 | Initial release - WestFries Open 2025 |
| 1.1.0 | TBD | Quality improvements, CI/CD, Security headers |
