---
title: Bracket rondes, beurtaanduiding, bestanden en routes
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Bracket rondes, beurtaanduiding, bestanden en routes

> Onderdeel van [Eliminatie Systeem](./README.md).

### Lookup tabellen

Ronde-namen en volgorde staan in `BracketLayoutService.php` als class constants:

| Constant | Doel |
|----------|------|
| `RONDE_VOLGORDE` | Kolom-volgorde (links→rechts) |
| `RONDE_NAMEN` | Leesbare namen (1/32, 1/16, Finale, etc.) |

**Ondersteunde rondes (A-groep):**
`tweeendertigste_finale`, `zestiende_finale`, `achtste_finale`, `kwartfinale`, `halve_finale`, `finale`

**Ondersteunde rondes (B-groep):**
`b_zestiende_finale_1/_2`, `b_achtste_finale_1/_2`, `b_kwartfinale_1/_2`, `b_halve_finale_1/_2`, `b_brons`
Plus varianten zonder suffix (SAMEN modus).

**Bij nieuwe ronde-namen:** altijd `RONDE_VOLGORDE` + `RONDE_NAMEN` in BracketLayoutService bijwerken!

### Beurtaanduiding (double-click kleuren)

Double-click op eliminatie potje → zelfde 3-kleuren systeem als poules:
- **Groen** = speelt nu (`actieve_wedstrijd_id`)
- **Geel** = staat klaar (`volgende_wedstrijd_id`)
- **Blauw** = gereed maken (`gereedmaken_wedstrijd_id`)

**Flow:** `ondblclick` → `window.dblClickBracket()` → `Alpine.$data()` → `toggleVolgendeWedstrijd()` → `setWedstrijdStatus()` → `applyBeurtaanduiding()`

**applyBeurtaanduiding()** zet inline `style=""` op de wit/blauw slot divs (via `document.getElementById('slot-{id}-wit')`). Inline styles wint van Tailwind classes.

### Bestanden

| Bestand | Rol |
|---------|-----|
| `app/Services/BracketLayoutService.php` | Positie-berekening, ronde lookups |
| `views/pages/mat/partials/_bracket.blade.php` | A-bracket container |
| `views/pages/mat/partials/_bracket-b.blade.php` | B-bracket container (mirrored) |
| `views/pages/mat/partials/_bracket-potje.blade.php` | Individueel potje (wit+blauw) |
| `views/pages/mat/partials/_bracket-medailles.blade.php` | Medaille slots (goud/zilver/brons) |
| `views/pages/mat/partials/_content.blade.php` | JS: laadBracketHtml, updateBracketSlot, SortableJS, beurtaanduiding |
| `app/Http/Controllers/MatController.php` | getBracketHtml endpoint + updated_slots in plaatsJudoka |

### Routes

| Route | Middleware | Beschrijving |
|-------|-----------|--------------|
| `POST {org}/toernooi/{toernooi}/mat/bracket-html` | auth:organisator | Admin bracket HTML |
| `POST {org}/{toernooi}/mat/{toegang}/bracket-html` | device.binding | Device-bound bracket HTML |

