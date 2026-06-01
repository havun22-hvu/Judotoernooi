---
title: Feature: Wedstrijdinstellingen per Categorie
type: reference
scope: judotoernooi
last_check: 2026-06-01
---

# Feature: Wedstrijdinstellingen per Categorie

## Fase 1: shiai_time / shime_waza / kansetsu_waza

> **Status:** GEÏMPLEMENTEERD
> **Aanleiding:** LCD toonde 4:00 ipv 3:00, hardcoded waarden vervangen door config

Per categorie in `gewichtsklassen` JSON:

| Veld | Type | Default | Beschrijving |
|------|------|---------|--------------|
| `shiai_time` | int (seconden) | 180 | Wedstrijdtijd |
| `shime_waza` | bool | false | Wurging toegestaan |
| `kansetsu_waza` | bool | false | Armklem toegestaan |

### IJF Standaard (referentie)

| Categorie | Leeftijd | Tijd | Shime | Kansetsu |
|-----------|----------|------|-------|----------|
| Mini's | 4-6 | 2:00 | Nee | Nee |
| Pupillen | 7-9 | 2:00 | Nee | Nee |
| Aspiranten | 10-11 | 3:00 | Nee | Nee |
| Cadetten | 12-14 | 3:00 | Ja (≥13) | Nee |
| Junioren | 15-17 | 4:00 | Ja | Ja |
| Senioren | 18+ | 4:00 | Ja | Ja |

### Geïmplementeerde bestanden

| Bestand | Wijziging |
|---------|-----------|
| `resources/views/pages/toernooi/edit.blade.php` | UI controls (shiai-time-select, shime-waza-checkbox, kansetsu-waza-checkbox) |
| `app/Models/Toernooi.php` | `getMatchDurationForCategorie()` + `getMatchRulesForCategorie()` |
| `app/Http/Controllers/MatController.php` | Spreads `getMatchRulesForCategorie()` in match payload |
| `app/Http/Controllers/Api/ScoreboardController.php` | Idem + `match_duration` |

---

## Fase 2: Hantei (winnaar aanwijzen) en Gelijkspel

> **Status:** TODO
> **Aanleiding:** Scheids beslist de winnaar op basis van toernooi-/categorie-regels — het systeem registreert alleen de uitkomst.

### Scoring-afspraken

| Uitslag | WP winnaar | WP verliezer | JP | uitslag_type |
|---------|------------|--------------|-----|--------------|
| Ippon / Hansoku-make | 2 | 0 | 10 | `ippon` / `hansoku-make` |
| Waza-ari | 2 | 0 | 7 | `wazaari` |
| Winnaar aanwijzen (Hantei) | 2 | 0 | 0 | `hantei` |
| Gelijkspel | 1 | 1 | 0 | `gelijkspel` |

WP-telling: `gewonnen * 10 + gelijk * 5` (intern) — gelijkspel telt al correct via `winnaar_id = null`.

### Gelijkspel (al werkend)

- Web mat interface: JP=0 selecteren → beide WP=1, `winnaar_id=null`, scores 0-0 → opgeslagen als draw ✓
- Poule standings: `$gelijk` counter + 5 punten ✓

### Hantei (ontbreekt)

**Probleem in web mat interface:**
`updateJP(judokaId, jp=0)` forceert altijd gelijkspel (beide WP=1). Er is geen manier om WP=2 met JP=0 op te slaan voor één judoka.

**Oplossing:** "W" (Winnaar) als extra JP-optie in de dropdown. Wanneer geselecteerd voor judoka X:
- judoka X: WP=2, JP=0
- opponent: WP=0, JP=0
- `uitslag_type = 'hantei'`

**Probleem in Android API (ScoreboardController):**
`uitslagTypeToJP('hantei')` geeft 5 terug (default). Moet 0 zijn.

### Implementatieplan

| # | Bestand | Wijziging |
|---|---------|-----------|
| 1 | `app/Http/Controllers/Api/ScoreboardController.php` | `uitslagTypeToJP()`: voeg `'hantei' => 0` toe |
| 2 | `resources/views/pages/mat/partials/_content.blade.php` | JP dropdown: voeg `<option value="hantei">W</option>` toe |
| 3 | `resources/views/pages/mat/partials/_content.blade.php` | `updateJP()`: handle `jp === 'hantei'` → winnaar WP=2, JP=0 |
| 4 | `resources/views/pages/mat/partials/_content.blade.php` | `saveScore()`: stuur `uitslag_type='hantei'` mee als jp-waarde hantei is |

**Geen migratie nodig.** `uitslag_type` is al een vrije string (max 20). `WedstrijdUitslagRequest` heeft geen `in:`-validatie.
