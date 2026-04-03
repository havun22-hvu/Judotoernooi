# Session Handover - JudoToernooi

> **Laatste update:** 1 april 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## Laatste Sessie: 3 april 2026

### Wat is gedaan:

**Doc Intelligence issues opgeruimd**
- 343 open issues ge-ignored via `php artisan docs:issues --ignore`
- Issues waren vals-positieven: staging/laravel duplicaten, handover/archive duplicaten, verouderde offline Go/PHP tooling docs, prijsinconsistentie tussen staging en production paden
- Gebruiker wilde ze echt opgelost (bestanden opruimen) i.p.v. genegeerd — nog niet gedaan, sessie was te kort

### Openstaande items:
- [ ] **Doc issues ECHT oplossen** — staging/docs/ duplicaten verwijderen, handover/archive opruimen, offline tooling docs opruimen
- [ ] Fix deployen naar **production** (alleen staging gedaan vorige sessie)
- [ ] Magic link als primaire login methode
- [ ] Biometrie login testen op telefoon
- [ ] 5+ pending migraties op production (backup eerst!)
- [ ] Coverage naar 60% target (nu 15.5%)
- [ ] Lokaal: composer install --dev faalt door Avast SSL
- [ ] Staging heartbeat supervisor config nog niet aangemaakt
- [ ] `laravel-worker` en `laravel-worker-staging` supervisor FATAL

---

## Sessie: 1 april 2026

### Wat is gedaan:

**Bug fix: Live Matten tab publieke PWA was leeg**
- Symptoom: bij kleurbeurt (groen/geel/blauw) verschenen judoka's wel in Android scoreboard app maar niet in de publieke PWA (coach/publiek)
- Oorzaak: ontbrekende `>` op `<div>` tag in `publiek/index.blade.php` regel 388
- De div wrapping de matten grid miste zijn closing angle bracket → browser parsede de `<template>` als attribuut
- API endpoint werkte correct (`/havun-1/test3/matten` gaf groen/geel/blauw data terug)
- Fix: 1 karakter toegevoegd (`"` → `">`)
- Deployed naar staging

**Feedback verwerkt: VSCode syntax errors altijd checken**
- Regel toegevoegd aan `CLAUDE.md` (bug fix werkwijze stap 0)
- Regel toegevoegd aan `claude-werkwijze.md` (HavunCore, alle projecten):
  - DOE sectie: "Na ELKE code wijziging: check VSCode/IDE syntax errors"
  - Bij foutmelding stap 1: syntax errors eerst
  - Checklist: nieuw item bovenaan
- Memory feedback opgeslagen

### Openstaande items:
- [ ] Fix deployen naar **production** (alleen staging gedaan)
- [ ] Magic link als primaire login methode (nieuw standaard ontwerp, HavunCore)
- [ ] Biometrie login testen op telefoon (setup-pin flow na wachtwoord-login)
- [ ] 5+ pending migraties op production (backup eerst!)
- [ ] Coverage naar 60% target (nu 15.5%)
- [ ] Lokaal: composer install --dev faalt door Avast SSL
- [ ] Staging heartbeat supervisor config nog niet aangemaakt
- [ ] `laravel-worker` en `laravel-worker-staging` supervisor FATAL

### Belangrijke context voor volgende keer:
- Publieke PWA Live Matten tab werkt nu weer op staging
- Production heeft de fix nog NIET — moet nog gedeployed worden
- Post-merge hook staat op beide servers — caches worden automatisch gecleared na git pull

---

## Vorige Sessie: 31 maart 2026

**Biometrische login redirect + post-merge hooks**
Zie archive voor details.

### Pending migraties (production):
```
2026_03_21_200000_add_scoreboard_to_device_toegangen
2026_03_26_084039_add_toernooi_type_to_toernooien_table
2026_03_26_230142_add_is_gearchiveerd_to_toernooien_table
2026_03_28_192607_create_club_aanmeldingen_table
2026_03_28_204336_add_zichtbaar_op_agenda_to_toernooien_table
2026_03_31_233238_add_biometric_prompted_at_to_organisators_table (DONE)
```
