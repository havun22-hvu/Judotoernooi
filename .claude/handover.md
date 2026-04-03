# Session Handover - JudoToernooi

> **Laatste update:** 3 april 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## Laatste Sessie: 3 april 2026

### Wat is gedaan:

**1. Dashboard "Genereer Pouleindeling" knop verbeterd**
- Als er al poules bestaan: subtielere outline knop ("Opnieuw genereren") + `confirm()` dialoog
- Toont aantal bestaande poules en wedstrijden in waarschuwing
- Als geen poules: prominente blauwe knop (ongewijzigd)
- Bestand: `dashboard.blade.php` regels 164-183

**2. Poule solver significant verbeterd (Python)**
- Root cause: greedy koppelde judoka's aan incompatibele partners (bv. 51.5+53.3kg), waarna betere matches onbereikbaar werden
- 3 nieuwe strategieën toegevoegd:
  - **Strategy 5 (break-apart):** Splitst size-2 "doodlopende" poules, herplaatst individueel
  - **Strategy 2 verbeterd:** Soepeler steal threshold voor orphans (>min_size ipv >ideale_size)
  - **Step 4b (consolidatie):** Merge + steal pass na soft placement voor nieuwe orphans
- Testresultaat (190 judokas, max_kg=3, max_lft=1): score 1025→620, singleton poules 5→0
- Bestand: `scripts/poule_solver.py`

**3. Mat show pagina real-time updates**
- `show.blade.php` was volledig statisch — geen WebSocket, geen auto-refresh
- Pusher/Reverb listener + auto-reload (500ms debounce) bij score/beurt/poule updates
- Bestand: `pages/mat/show.blade.php`

**4. Eliminatie MatUpdate broadcast bug gefixt**
- `MatController::doRegistreerUitslag()` had `return` in eliminatie branch VOOR de `MatUpdate::dispatch()`
- Eliminatie score wijzigingen broadcastten niet naar mat/publiek interfaces
- Nu: dispatch vóór return, met type='eliminatie'
- Bestand: `MatController.php` regels 187-200

**5. bandNaarNummer bug ontdekt (niet gefixt)**
- `DynamischeIndelingService::bandNaarNummer()` verwacht strings ("wit","blauw") maar DB slaat integers op (0-6)
- Alle judoka's krijgen band=0 in solver input → band-sortering werkt niet
- Impact: sorteervolgorde suboptimaal, maar compatibiliteitscheck ongewijzigd (max_band=0)
- TODO: fix mapping of DB opslag

### Openstaande items:
- [ ] **bandNaarNummer bug** — integers→strings mapping in DynamischeIndelingService
- [ ] Magic link als primaire login methode
- [ ] 5+ pending migraties op production (backup eerst!)
- [ ] Coverage naar 60% target (nu 15.5%)
- [ ] Lokaal: composer install --dev faalt door Avast SSL
- [ ] `laravel-worker` en `laravel-worker-staging` supervisor FATAL

### Bekende issues:
- Mat 59 in URL `/mat/59` bestaat niet in DB (gap 55→66). Mogelijk oude test URL.
- P#1 Wimpeltoernooi: wedstrijden 2-9 `is_gespeeld=true` maar geen winnaar/scores (test data)
- 4 medium PHP security vulnerabilities (league/commonmark 2x, league/flysystem 2x)

---

## Vorige Sessies

### 1 april 2026
- Bug fix: Live Matten tab publieke PWA — ontbrekende `>` op div tag
- Feedback: VSCode syntax errors altijd checken

### 31 maart 2026
- Biometrische login redirect + post-merge hooks

### Pending migraties (production):
```
2026_03_21_200000_add_scoreboard_to_device_toegangen
2026_03_26_084039_add_toernooi_type_to_toernooien_table
2026_03_26_230142_add_is_gearchiveerd_to_toernooien_table
2026_03_28_192607_create_club_aanmeldingen_table
2026_03_28_204336_add_zichtbaar_op_agenda_to_toernooien_table
2026_03_31_233238_add_biometric_prompted_at_to_organisators_table (DONE)
```
