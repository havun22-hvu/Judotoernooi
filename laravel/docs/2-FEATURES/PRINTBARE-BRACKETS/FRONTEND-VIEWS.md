---
title: Frontend, views en SVG-rendering
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Frontend, views en SVG-rendering

> Onderdeel van [Printbare Eliminatie-Brackets](../PRINTBARE-BRACKETS.md).

## Frontend / Views

### Bestaande print-layout hergebruiken

`layouts/print.blade.php` bevat al CSP-nonce + Tailwind print styles. Gebruiken.

### Nieuwe views

```
pages/noodplan/bracket-print.blade.php          → hoofd-template, kiest variant via $meta['variant']
pages/noodplan/partials/_bracket-print-a.blade.php → SVG A-bracket
pages/noodplan/partials/_bracket-print-b.blade.php → SVG B-bracket
pages/noodplan/brackets-index.blade.php         → overzicht eliminatie-poules per blok met print-knoppen
```

### SVG-rendering

**Geen px-positionering.** Gebruik `viewBox="0 0 W H"` met:
- W = `aantal_rondes * (KOLOM_BREEDTE + KOLOM_GAP)` (bv. 5 rondes × 200 = 1000)
- H = `$layout['totale_hoogte']` uit BracketLayoutService

Per wedstrijd in SVG:
```svg
<g transform="translate(kolom_x, potje_top)">
  <!-- Top vakje (wit / wedstrijd-uitkomst) -->
  <rect x="0" y="0" width="180" height="32" fill="white" stroke="#333"/>
  <text x="6" y="14" font-size="10" font-weight="bold">Naam Speler</text>
  <text x="6" y="26" font-size="8" fill="#666">Club</text>
  <rect x="150" y="0" width="30" height="32" fill="#f3f4f6" stroke="#333"/>
  <text x="165" y="20" text-anchor="middle" font-size="14" font-weight="bold">{{ score_of_leeg }}</text>

  <!-- Onder vakje (blauw / wedstrijd-uitkomst) -->
  ...

  <!-- Verbindingslijn naar volgende ronde -->
  <line x1="180" y1="32" x2="200" y2="32" stroke="#333" stroke-width="1"/>
  <line x1="200" y1="32" x2="200" y2="halfweg_van_buurman_potje" stroke="#333"/>
</g>
```

Lijn-coördinaten komen rechtstreeks uit `$layout['rondes'][i]['wedstrijden'][j]['_layout']['top']` (al berekend door BracketLayoutService).

### Leeg-op-maat: invul-strookjes

In de SVG-vakken voor naam: een dunne onderlijn `<line>` als invulstreep zodat de organisator met pen kan invullen. Score-vakken: gewoon leeg.

---

## Aansluiting op bestaande Noodplan-index

In `pages/noodplan/index.blade.php`: nieuwe sectie "Eliminatie brackets" naast "Wedstrijdschema's", met:

1. Knop "Leeg bracket op maat" → opent select-N-formuliertje (2..32 dropdown) → linkt door naar `noodplan.bracket-leeg`
2. Per blok (indien blokken zijn aangemaakt) een dropdown met eliminatie-poules in dat blok + 2 knoppen per poule: "Startposities" en "Live".

---

