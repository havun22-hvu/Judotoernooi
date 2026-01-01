# JudoToernooi - Claude Instructions

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

- **Framework:** Laravel 11
- **Local DB:** SQLite
- **Server DB:** MySQL (judo_toernooi)
- **Features:** Toernooi management, deelnemers import, poule indeling, weging, mat interface

## Knowledge Base

Voor uitgebreide info:
- **Context:** `.claude/context.md` (features, database, deploy)
- **Project docs:** `laravel/docs/` (installatie, API, database schema)
- **HavunCore KB:** `D:\GitHub\HavunCore\docs\kb\`
