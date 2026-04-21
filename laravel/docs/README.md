# JudoToernooi — Documentatie

Documentatie van het JudoToernooi Management Systeem (Laravel 11 SaaS).

## Structuur

```
docs/
├── 1-GETTING-STARTED/  Installatie en configuratie
├── 2-FEATURES/         Feature documentatie
├── 3-DEVELOPMENT/      Code standaarden, API, database, architectuur
├── 4-PLANNING/         Open/toekomstige features
├── 5-REGLEMENT/        JBN reglementen
├── 6-INTERNAL/         Rollen, interne referenties
└── postmortem/         Incident analyses
```

## 1. Getting Started

- [Installatie](./1-GETTING-STARTED/INSTALLATIE.md) — server/local setup
- [Configuratie](./1-GETTING-STARTED/CONFIGURATIE.md) — app configuratie

## 2. Features

**Kern-workflow**
- [Gebruikershandleiding](./2-FEATURES/GEBRUIKERSHANDLEIDING.md)
- [Import](./2-FEATURES/IMPORT.md) — CSV/Excel + warnings
- [Classificatie & Poule-indeling](./2-FEATURES/CLASSIFICATIE.md)
- [Blokverdeling](./2-FEATURES/BLOKVERDELING.md)
- [Wedstrijdschema](./2-FEATURES/WEDSTRIJDSCHEMA.md)
- [Mat Wedstrijd Selectie](./2-FEATURES/MAT-WEDSTRIJD-SELECTIE.md) — 3-kleuren systeem
- [Eliminatie](./2-FEATURES/ELIMINATIE/README.md) (+ [Formules](./2-FEATURES/ELIMINATIE/FORMULES.md), [Slot-systeem](./2-FEATURES/ELIMINATIE/SLOT-SYSTEEM.md), [Test-matrix](./2-FEATURES/ELIMINATIE/TEST-MATRIX.md))

**Business / commercieel**
- [Betalingen](./2-FEATURES/BETALINGEN.md) — Mollie + Stripe
- [Freemium](./2-FEATURES/FREEMIUM.md) — tier model
- [Club Aanmelding](./2-FEATURES/CLUB-AANMELDING.md)
- [Wimpeltoernooi](./2-FEATURES/WIMPELTOERNOOI.md)
- [Danpunten](./2-FEATURES/DANPUNTEN.md)

**Interfaces & devices**
- [Interfaces](./2-FEATURES/INTERFACES.md) — PWA's + device binding
- [Scorebord App](./2-FEATURES/SCOREBORD-APP.md) — Android + LCD display
- [Chromecast](./2-FEATURES/CHROMECAST.md)
- [Chat](./2-FEATURES/CHAT.md) — Reverb realtime
- [Judoka Self-Check](./2-FEATURES/JUDOKA-SELFCHECK.md)
- [Judoka Database](./2-FEATURES/JUDOKA-DATABASE.md) — stambestand import

**Business continuity**
- [Noodplan Handleiding](./2-FEATURES/NOODPLAN-HANDLEIDING.md)
- [Lokale Server Handleiding](./2-FEATURES/LOKALE-SERVER-HANDLEIDING.md)

## 3. Development

- [Code Standaarden](./3-DEVELOPMENT/CODE-STANDAARDEN.md) — **verplicht** voor elke wijziging
- [Stability & Error Handling](./3-DEVELOPMENT/STABILITY.md) — Exceptions, Circuit Breaker, Rate Limiting, Activity Logging
- [Codebase-structuur](./3-DEVELOPMENT/CODEBASE-STRUCTUUR.md)
- [URL Structuur](./URL-STRUCTUUR.md)
- [API Documentatie](./3-DEVELOPMENT/API.md)
- [Database Schema](./3-DEVELOPMENT/DATABASE.md)
- [Functies Overzicht](./3-DEVELOPMENT/FUNCTIES.md)
- [Ontwikkelaar Gids](./3-DEVELOPMENT/ONTWIKKELAAR.md)
- [Redundantie & Veiligheid](./3-DEVELOPMENT/REDUNDANTIE.md) — enterprise fail-safe architectuur
- [SEO](./3-DEVELOPMENT/SEO.md) — robots, sitemap, JSON-LD, GA4

## 4. Planning (openstaand)

- [Categorie-wedstrijdinstellingen](./4-PLANNING/CATEGORIE-WEDSTRIJD-INSTELLINGEN.md) — `shiai_time`, `shime_waza`, `kansetsu_waza` per klasse (IJF compliance)
- [Publiek Scorebord mobiel](./4-PLANNING/PUBLIEK-SCOREBORD.md) — portrait/landscape variant
- [Multi-tenancy Roadmap](./4-PLANNING/MULTI-TENANCY-ROADMAP.md) — subdomains (op hold, slug-based werkt)

## 5. Reglement

- [JBN Reglement 2026](./5-REGLEMENT/JBN-REGLEMENT-2026.md)

## 6. Internal

- [Rollen Hiërarchie](./6-INTERNAL/ROLLEN_HIERARCHIE.md)

## Postmortems

- [2026-04-05 Reverb Broadcasting Failure](./postmortem/2026-04-05-reverb-broadcasting-failure.md)

## Snelle Start

```bash
git clone https://github.com/havun22-hvu/judotoernooi.git
cd judotoernooi/laravel
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate && php artisan db:seed
php artisan serve --port=8007
```

## Changelog

Zie [CHANGELOG.md](../CHANGELOG.md).
