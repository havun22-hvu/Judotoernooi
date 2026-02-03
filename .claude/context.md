# JudoToernooi Context

> Multi-tenant judo toernooi platform

## Overzicht

| Aspect | Waarde |
|--------|--------|
| **Type** | Laravel 11 + Blade + Alpine.js + Tailwind |
| **URL** | judotournament.org |
| **Status** | Production (live) |

> "WestFries Open" is een specifiek toernooi van klant Cees Veen, niet de naam van het platform.

## Omgevingen

| Omgeving | URL | Pad | Database |
|----------|-----|-----|----------|
| **Local** | localhost:8007 | `D:\GitHub\JudoToernooi\laravel` | SQLite |
| **Staging** | (geen domein) | `/var/www/staging.judotoernooi/laravel` | MySQL |
| **Production** | judotournament.org | `/var/www/judotoernooi/laravel` | MySQL |

```bash
php artisan serve --port=8007
```

## Documentatie Structuur

| Bestand | Inhoud |
|---------|--------|
| `context.md` | Dit bestand - overzicht |
| `features.md` | Functionaliteit, classificatie, auth |
| `mollie.md` | Betalingen configuratie |
| `deploy.md` | Deploy instructies |
| `handover.md` | Laatste sessie info |
| `smallwork.md` | Kleine fixes (archief in `archive/`) |
| `handover/` | Gedetailleerde sessie handovers |

## Core Features

- **Toernooi Management** - Aanmaken/configureren
- **Deelnemers Import** - CSV/Excel met auto-classificatie
- **Poule Indeling** - Automatisch algoritme
- **Mat Interface** - Wedstrijden en uitslagen
- **Eliminatie** - Double elimination
- **Real-time Sync** - Reverb WebSockets voor chat en score updates

## Belangrijke Regels

| Onderwerp | Regel |
|-----------|-------|
| **Band** | Alleen kleur opslaan (wit, geel, oranje, etc.) - GEEN kyu |
| **Gewichtsklasse** | NIET invullen bij variabele gewichten in categorie |

## Coach Portal Logica

**Instellingen (Toernooi → Organisatie tab):**
- `portaal_modus`: `volledig` / `mutaties` / `bekijken`
- `inschrijving_deadline`: datum waarna portal NIET meer kan wijzigen/syncen
- `betaling_actief`: bij aanmelden moet betaald worden

**Wat mag wanneer:**
| Modus | Nieuwe judoka's | Wijzigen | Sync | Verwijderen |
|-------|-----------------|----------|------|-------------|
| `volledig` | ✓ | ✓ | ✓ | ✓ |
| `mutaties` | ✗ | ✓ | ✓ | ✗ |
| `bekijken` | ✗ | ✗ | ✗ | ✗ |

**Deadline:** Na `inschrijving_deadline` is ALLES geblokkeerd (ongeacht modus)

**Sync vereisten:** Judoka moet volledig zijn (naam, geboortejaar, geslacht, band, gewicht) EN passen in een categorie

**Sync flow:**
- `betaling_actief = false` → handmatig syncen als gegevens volledig zijn
- `betaling_actief = true` → sync via Mollie webhook na succesvolle betaling

## Gerelateerde Docs

- `laravel/docs/` - Project documentatie
- `HavunCore/docs/kb/patterns/mollie-payments.md`
