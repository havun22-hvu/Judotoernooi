# JudoToernooi - Claude Instructions

```
╔══════════════════════════════════════════════════════════════════╗
║  ⛔ STOP! LEES DIT VOORDAT JE IETS DOET                          ║
║                                                                   ║
║  GEEN CODE SCHRIJVEN VOORDAT JE ANTWOORD GEEFT OP:               ║
║                                                                   ║
║  1. "Wat staat er in de docs over dit onderwerp?"                ║
║  2. "Waar staat dat?" (geef bestandsnaam + regelnummer)          ║
║  3. "Is er iets inconsistent of ontbrekend?"                     ║
║                                                                   ║
║  PAS DAARNA mag je code voorstellen.                             ║
║  Gebruiker moet EERST akkoord geven.                             ║
║                                                                   ║
║  ⚠️  Bij twijfel: /kb of vraag aan gebruiker                     ║
╚══════════════════════════════════════════════════════════════════╝
```

> **Type:** Laravel 11 toernooi management systeem
> **URL:** https://staging.judotournament.org
> **Doel:** WestFries Open Judo Toernooi beheren

## Rules (ALWAYS follow)

### LEES-DENK-DOE-DOCUMENTEER (Kritiek!)

> **Volledige uitleg:** `HavunCore/docs/kb/runbooks/claude-werkwijze.md`

**Bij ELKE taak:**
1. **LEES** - Hiërarchisch: CLAUDE.md → relevante code/docs voor de taak
2. **DENK** - Analyseer, begrijp, stel vragen bij twijfel
3. **DOE** - Pas dan uitvoeren, rustig, geen haast
4. **DOCUMENTEER** - Sla nieuwe kennis op in de juiste plek

**Kernregels:**
- Kwaliteit boven snelheid - liever 1x goed dan 3x fout
- Bij twijfel: VRAAG en WACHT op antwoord
- Nooit aannemen, altijd verifiëren
- Als gebruiker iets herhaalt: direct opslaan in docs

**Documentatie discipline:**
- **Oriënteer** via hoofd docs: `CLAUDE.md` → `laravel/docs/README.md`
- **Hiërarchisch**: hoofd docs verwijzen naar detail docs → lees die EERST
- **GEEN** dubbele documentatie - check of info al ergens staat
- **ONDERHOUD** bestaande docs, maak niet steeds nieuwe files

**Hoofd docs → Detail docs:**
| Onderwerp | Hoofd | Detail |
|-----------|-------|--------|
| Workflow toernooi | README.md | `GEBRUIKERSHANDLEIDING.md` |
| Betalingen | CLAUDE.md | `BETALINGEN.md` |
| Interfaces/PWA | README.md | `INTERFACES.md` |

### Auto-start servers (bij lokaal testen)
```bash
cd laravel && php artisan serve --port=8001
```

### Forbidden without permission
- SSH keys, credentials, .env files wijzigen
- Database migrations op production
- Composer/npm packages installeren

### Communication
- Antwoord max 20-30 regels
- Bullet points, direct to the point

## Quick Reference

| Omgeving | Pad |
|----------|-----|
| Local | D:\GitHub\judotoernooi\laravel |
| Server | /var/www/judotoernooi/laravel |

**Server:** 188.245.159.115 (root, SSH key)
**GitHub:** https://github.com/havun22-hvu/judotoernooi

## Dit Project

| Aspect | Waarde |
|--------|--------|
| **Framework** | Laravel 11 + Blade + Alpine.js + Tailwind |
| **Local DB** | SQLite |
| **Server DB** | MySQL (judo_toernooi) |

### Features
- Toernooi management, deelnemers import, poule indeling
- Weging interface, mat interface, eliminatie systeem
- **Mollie betalingen** (Connect + Platform mode)

## Mollie Betalingen

> **Uitgebreide docs:** `.claude/context.md` en `laravel/docs/2-FEATURES/BETALINGEN.md`

| Modus | Geld naar | Toeslag |
|-------|-----------|---------|
| **Connect** | Organisator's eigen Mollie | Nee |
| **Platform** | JudoToernooi's Mollie | €0,50 |

**Key files:**
- `app/Services/MollieService.php` - Hybride service
- `config/services.php` - Mollie config
- `app/Models/Toernooi.php` - Helper methods

## Knowledge Base

| Onderwerp | Locatie |
|-----------|---------|
| Project details | `.claude/context.md` |
| Mollie betalingen | `laravel/docs/2-FEATURES/BETALINGEN.md` |
| Project docs | `laravel/docs/` |
| HavunCore KB | `D:\GitHub\HavunCore\docs\kb\`
