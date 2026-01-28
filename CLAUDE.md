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

> **Type:** Laravel 11 toernooi management systeem (SaaS multi-tenant)
> **URL:** https://judotournament.org (production)
> **Eigenaar:** Havun (henkvu@gmail.com = sitebeheerder)
> **Doel:** SaaS platform voor judo toernooien - verhuurd aan judoscholen/organisatoren

## Bedrijfsmodel (SaaS)

**Havun** verhuurt de JudoToernooi software aan judoscholen (organisatoren).

### Rollen
| Rol | Beschrijving | Voorbeeld |
|-----|--------------|-----------|
| **Sitebeheerder** | Havun admin, ziet alle organisatoren en toernooien | henkvu@gmail.com |
| **Organisator** | Klant (judoschool), beheert eigen toernooien | Judoschool Cees Veen |

### Data per Organisator (blijft bewaard)
Organisatoren zijn terugkerende klanten. Deze data blijft bewaard tussen toernooien:

| Data | Beschrijving |
|------|--------------|
| **Clubs** | Deelnemende judoscholen (fuzzy name matching) |
| **Templates** | Toernooi configuraties (intern/open toernooi) |
| **Presets** | Gewichtsklassen presets |
| **Toernooien** | Historisch overzicht (ook afgesloten) |

### Toernooi Lifecycle
```
Nieuw → Voorbereiding → Wedstrijddag → Afgesloten
         ↑                              |
         └── Templates hergebruiken ────┘
```

### Sitebeheerder Dashboard
Route: `/toernooi` (alleen voor sitebeheerder)
- Overzicht alle organisatoren
- Per organisator: toernooien, statistieken, status
- KPI's: totaal judokas, omzet, actieve klanten

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
cd "D:/GitHub/JudoToernooi/laravel" && php artisan serve --port=8007
```

### Forbidden without permission
- SSH keys, credentials, .env files wijzigen
- Database migrations op production
- Composer/npm packages installeren

### ⛔ HANDS OFF TEST DATA
**NOOIT zonder expliciete vraag:**
- Toernooien aanmaken via tinker/code
- Judoka's, clubs, of andere data toevoegen
- Bestaande data wijzigen of verplaatsen
- `migrate:fresh` uitvoeren (wist ALLE data!)

**WEL toegestaan:**
- Code lezen en analyseren
- Code verbeteren/fixen
- Browser gebruiken om te BEKIJKEN (niet wijzigen)
- Vragen stellen over wat je ziet

**Bij testen:**
- Gebruik ALLEEN data die de gebruiker aanlevert
- Als je data nodig hebt: VRAAG de gebruiker om het aan te maken
- Los geen "testdata problemen" zelf op

### Communication
- Antwoord max 20-30 regels
- Bullet points, direct to the point

## Quick Reference

| Omgeving | Pad |
|----------|-----|
| Local | D:\GitHub\judotoernooi\laravel |
| Staging | /var/www/staging.judotoernooi/laravel |
| Production | /var/www/judotoernooi/laravel |

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
| Classificatie/Poules | `laravel/docs/2-FEATURES/CLASSIFICATIE.md` |
| Mollie betalingen | `laravel/docs/2-FEATURES/BETALINGEN.md` |
| Project docs | `laravel/docs/README.md` |
| HavunCore KB | `D:\GitHub\HavunCore\docs\kb\`
