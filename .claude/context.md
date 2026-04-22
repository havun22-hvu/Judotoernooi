---
title: JudoToernooi Context
type: claude
scope: judotoernooi
last_check: 2026-04-22
---

# JudoToernooi Context

> Multi-tenant judo toernooi SaaS — judotournament.org (production live)

## Stack & omgevingen

| Aspect | Waarde |
|--------|--------|
| **Stack** | Laravel 11 + Blade + Alpine.js + Tailwind + Reverb |
| **Betaling** | Mollie (standaard, EU) + Stripe (wereldwijd) |
| **Auth** | Magic link registratie, wachtwoord, passkeys/biometrisch |

| Omgeving | URL | App pad | Git repo | Database |
|----------|-----|---------|----------|----------|
| **Local** | localhost:8007 | `D:\GitHub\JudoToernooi\laravel` | `D:\GitHub\JudoToernooi` | SQLite |
| **Staging** | staging.judotournament.org | `/var/www/judotoernooi/staging` (symlink) | `/var/www/judotoernooi/repo-staging` | MySQL |
| **Production** | judotournament.org | `/var/www/judotoernooi/laravel` (symlink) | `/var/www/judotoernooi/repo-prod` | MySQL |

```bash
cd laravel && php artisan serve --port=8007
```

> "WestFries Open" is een specifiek toernooi van klant Cees Veen, niet de platform-naam.

## .claude/ werkbestanden

| Bestand | Inhoud |
|---------|--------|
| `context.md` | Dit bestand — project-overzicht |
| `deploy.md` | Deploy instructies (server) |
| `handover.md` | Openstaande items + recente context |
| `smallwork.md` | Ad-hoc fixes (>2 weken oud: wissen — git log = bron van waarheid) |
| `commands/` | Slash-command skills |

## Belangrijke domein-regels

| Onderwerp | Regel |
|-----------|-------|
| **Band** | Alleen kleur opslaan (wit, geel, oranje, ...). GEEN kyu. |
| **Gewichtsklasse** | NIET invullen bij variabele gewichten in categorie. |
| **Disclaimer** | Havun biedt platform "as is" — organisator is zelf verantwoordelijk voor lokale server, papier-schaduwadministratie en geprint noodplan. |

## Coach Portal (klanten van de organisator)

Toernooi → Organisatie tab: `portaal_modus` (`volledig` / `mutaties` / `bekijken`), `inschrijving_deadline`, `betaling_actief`.

| Modus | Nieuwe judoka's | Wijzigen | Sync | Verwijderen |
|-------|-----------------|----------|------|-------------|
| `volledig` | ✓ | ✓ | ✓ | ✓ |
| `mutaties` | ✗ | ✓ | ✓ | ✗ |
| `bekijken` | ✗ | ✗ | ✗ | ✗ |

Na `inschrijving_deadline`: alles geblokkeerd. Sync vereist volledige judoka (naam, geboortejaar, geslacht, band, gewicht) + passende categorie. Bij `betaling_actief=true` syncet Mollie webhook; anders handmatig.

## Juridische pagina's

Routes: `/algemene-voorwaarden`, `/privacyverklaring`, `/cookiebeleid`, `/disclaimer`. Views in `legal/*.blade.php`, controller `LegalController`, layout `legal-layout` component. Contact: `havun22@gmail.com`. Footer-tekst in `layouts/app.blade.php` én `pages/home.blade.php`.

## Gerelateerde docs

- Project docs: `laravel/docs/README.md`
- Mollie patroon: `HavunCore/docs/kb/patterns/mollie-payments.md`
- LCD scoreboard: `laravel/docs/2-FEATURES/SCOREBORD-APP.md`
