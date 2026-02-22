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
║  📖 VERPLICHTE LEESSTOF:                                         ║
║     laravel/docs/3-DEVELOPMENT/CODE-STANDAARDEN.md               ║
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

### Registratie & Login
- `/registreren` - Nieuwe organisator aanmelden (guest only)
- `/login` - Inloggen (email + wachtwoord)
- Organisator dashboard: `/{slug}/dashboard`

### Sitebeheerder Dashboard
Route: `/admin` (alleen voor sitebeheerder)
- Overzicht alle organisatoren en toernooien
- Klantenbeheer: `/admin/klanten` (bewerken, is_test/kortingsregeling, verwijderen)
- Per organisator: toernooien, statistieken, status

## CRITICAL: Sessie Start - Sync met Server

AutoFix kan code wijzigen op production en staging. Bij sessie start ALTIJD eerst synchroniseren:

```bash
# 1. Server: commit & push AutoFix wijzigingen (production)
ssh root@188.245.159.115 "cd /var/www/judotoernooi/laravel && git add -A && git diff --cached --quiet || git commit -m 'autofix: server changes' && git push"

# 2. Server: commit & push AutoFix wijzigingen (staging)
ssh root@188.245.159.115 "cd /var/www/staging.judotoernooi/laravel && git add -A && git diff --cached --quiet || git commit -m 'autofix: server changes' && git push"

# 3. Lokaal: pull server wijzigingen
cd D:\GitHub\JudoToernooi && git pull
```

**Waarom?** AutoFix draait op production+staging en kan bestanden aanpassen. Zonder sync werk je op verouderde code.

## Rules (ALWAYS follow)

### DENK ALS SAAS-BOUWER, NIET ALS PROBLEEMOPLOSSER
Je bouwt een **SaaS product** dat door betalende klanten wordt gebruikt. Denk bij elke beslissing:
- **Werkt dit voor ALLE organisatoren?** Niet alleen voor Henk's test-scenario
- **Wat als 50 toernooien tegelijk draaien?** Schaalbaarheid, permissies, isolatie
- **Wat ziet de klant?** Error pages, UX, foutmeldingen — alles moet professioneel
- **Is dit productie-waardig?** Geen hacks, geen "werkt op mijn machine"

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
- **Max 2 pogingen** bij bug fix - daarna STOP en verslag uitbrengen aan gebruiker

**Documentatie discipline:**
- **Oriënteer** via hoofd docs: `CLAUDE.md` → `laravel/docs/README.md`
- **Hiërarchisch**: hoofd docs verwijzen naar detail docs → lees die EERST
- **GEEN** dubbele documentatie - check of info al ergens staat
- **ONDERHOUD** bestaande docs, maak niet steeds nieuwe files

**Bug fix werkwijze (max 2 pogingen):**
1. **Poging 1:** Analyseer probleem, check VIEW eerst (waar komt data vandaan?), fix
2. **Poging 2:** Als 1 faalt, heranalyseer, probeer andere aanpak
3. **STOP na 2 pogingen:** Breng verslag uit aan gebruiker:
   - Wat is het symptoom?
   - Waar heb je gezocht?
   - Wat heb je geprobeerd?
   - Wat denk je dat het probleem is?
   - Gebruiker kan dan meedenken

**Hoofd docs → Detail docs:**
| Onderwerp | Hoofd | Detail |
|-----------|-------|--------|
| Workflow toernooi | README.md | `GEBRUIKERSHANDLEIDING.md` |
| Betalingen | CLAUDE.md | `BETALINGEN.md` |
| Interfaces/PWA | README.md | `INTERFACES.md` |
| **Code standaarden** | CLAUDE.md | `laravel/docs/3-DEVELOPMENT/CODE-STANDAARDEN.md` |
| **Error handling** | CLAUDE.md | `laravel/docs/3-DEVELOPMENT/STABILITY.md` |

### BESCHERM BESTAANDE CODE (5 lagen)

Hoe verder de app vordert, hoe belangrijker het is dat werkende code niet onbedoeld kapot gaat.
**Bij ELKE wijziging aan een view of component: LEES EERST wat er staat en waarom.**

**5 beschermingslagen (gebruik ze!):**

| # | Laag | Beschermt tegen | Hoe |
|---|------|----------------|-----|
| 1 | **MD docs** | Onbegrip (waarom bestaat dit?) | Beschrijf functie + implementatie in feature docs |
| 2 | **DO NOT REMOVE comment** | Onoplettendheid (per ongeluk wissen) | `{{-- DO NOT REMOVE: [wat + waarom] --}}` in de view |
| 3 | **Tests** | Code-regressie (het werkt niet meer) | PHPUnit/Feature test die de output checkt |
| 4 | **CLAUDE.md regels** | Alle toekomstige sessies | Deze sectie + specifieke regels |
| 5 | **Memory** | Context verlies tussen sessies | `memory/MEMORY.md` kritieke regels |

**Regels:**
- Views met `DO NOT REMOVE` comments: **NIET aanraken** zonder expliciete toestemming
- Bij twijfel of iets bewust geplaatst is: **VRAAG aan gebruiker**
- Verwijder NOOIT UI-elementen die je niet begrijpt — lees eerst de docs
- Na een fix: controleer dat bestaande functionaliteit nog intact is

### Workflow: Local → GitHub → Server
```
ALTIJD: Edit lokaal → Push naar GitHub → Deploy naar staging/production
NOOIT: Direct op server editen
```

### Auto-start servers (bij lokaal testen)
```bash
cd "D:/GitHub/JudoToernooi/laravel" && php artisan serve --port=8007
```

### Forbidden without permission
- SSH keys, credentials, .env files wijzigen
- Database migrations op production
- Composer/npm packages installeren

### ⛔ TEST DATA REGELS
**Testdata aanmaken/wijzigen:**
- Als gebruiker vraagt → gewoon doen

**NIET DOEN - Bugs maskeren:**
- Als code niet werkt (bijv. gewicht updaten faalt), NIET de data handmatig "goed" zetten
- De CODE moet werken, niet de data "toevallig goed staan"
- Fix altijd de code, niet de symptomen

### Database & Migraties
**Local/Staging:**
- `migrate:fresh` alleen voor volledige database reset
- Na gewone migratie: ALTIJD gegevens terugzetten

**Production (KRITIEK - geen minuut data mag verloren gaan):**
- ALTIJD backup maken TIJDENS werken aan de app (continu!)
- ALTIJD backup maken VOOR migratie
- Dagelijkse backup: server → Hetzner backup (elke avond)
- Bij crash: direct kunnen herstellen naar laatste backup

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
| Error handling | `laravel/docs/3-DEVELOPMENT/STABILITY.md` |
| Code standaarden | `laravel/docs/3-DEVELOPMENT/CODE-STANDAARDEN.md` |
| Project docs | `laravel/docs/README.md` |
| Handover | `.claude/handover.md` |
| HavunCore KB | `D:\GitHub\HavunCore\docs\kb\`

## Production Commands

```bash
# Validate production readiness
php artisan validate:production

# Run tests
php artisan test

# Build assets
npm run build
```
