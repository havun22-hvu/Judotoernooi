# Session Handover - JudoToernooi

> **Laatste update:** 28 maart 2026
> **Status:** PRODUCTION DEPLOYED - Live op https://judotournament.org

---

## Laatste Sessie: 28 maart 2026

### Wat is gedaan:

**Poule regels check (HOOFDTAAK):**
- Bug: gewichtsverschil-waarschuwing verdween na verwijderen judoka uit poule
- Nieuwe `Poule::checkPouleRegels()` methode — checkt max_kg_verschil + max_leeftijd_verschil
- `buildPouleResponse()` bevat nu `problemen` array in ALLE mutatie-responses
- Gefixt in BEIDE pagina's: poule indeling + wedstrijddag
- DO NOT REMOVE comments op alle kritieke code
- 13 guard tests in `PouleCheckRegelsTest.php`

**Code coverage opzetten:**
- PCOV geinstalleerd op staging (php8.2-pcov)
- 109 nieuwe tests geschreven (4 agents parallel):
  - `PouleModelTest.php` (35 tests)
  - `PouleIndelingServiceTest.php` (16 tests)
  - `PouleControllerApiTest.php` (21 tests)
  - `ImportServiceTest.php` (+37 tests)
- Coverage: 8% → 15.5% (290 tests, 714 assertions, ALL GREEN)

**Bugs gevonden door tests:**
- `PouleController::store()` miste `type => 'voorronde'` (NOT NULL op SQLite)
- `PouleController::store()` zette `gewichtsklasse` op null i.p.v. ''

**Kleine features:**
- Device token (eerste 4 tekens) zichtbaar naast PIN in device toegangen

**HavunCore:**
- Nieuw pattern: `docs/kb/patterns/regression-guard-tests.md`

### Openstaande items:
- [ ] 5 pending migraties op production (backup eerst!)
- [ ] Coverage naar 60% target (nu 15.5%)
- [ ] Lokaal: composer install --dev faalt door Avast SSL — apart oplossen
- [ ] `verwijderOudeToernooien()` method kan verwijderd worden uit ToernooiService

### Belangrijke context:
- Henk werkt met 10+ parallelle Claude sessies — behandel als team
- ~60% van tijd gaat naar herstelwerk — tests zijn NOODZAAK
- `checkPouleRegels()` is 5x per ongeluk verwijderd — nu beschermd met tests + DO NOT REMOVE
- Bij NIEUWE poule mutatie-endpoints: ALTIJD `buildPouleResponse()` in response

### Pending migraties (production):
```
2026_03_21_200000_add_scoreboard_to_device_toegangen
2026_03_26_084039_add_toernooi_type_to_toernooien_table
2026_03_26_230142_add_is_gearchiveerd_to_toernooien_table
2026_03_28_192607_create_club_aanmeldingen_table
2026_03_28_204336_add_zichtbaar_op_agenda_to_toernooien_table
```
