---
title: Wedstrijdschema-matrix en kolom-headers
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Wedstrijdschema-matrix en kolom-headers

> Onderdeel van [Wedstrijdschema Systeem](../WEDSTRIJDSCHEMA.md).

## Wedstrijdschema Matrix

Het schema wordt weergegeven als een matrix:

```
         │ Wed 1  │ Wed 2  │ Wed 3  │ Wed 4  │ Wed 5  │ Wed 6  │ Totaal │
         │ WP  JP │ WP  JP │ WP  JP │ WP  JP │ WP  JP │ WP  JP │ WP  JP │ Plts
─────────┼────────┼────────┼────────┼────────┼────────┼────────┼────────┼─────
Judoka 1 │ □   □  │ ██████ │ □   □  │ ██████ │ □   □  │ ██████ │  6  25 │  1
Judoka 2 │ □   □  │ □   □  │ ██████ │ ██████ │ ██████ │ □   □  │  4  20 │  2
Judoka 3 │ ██████ │ □   □  │ □   □  │ □   □  │ ██████ │ ██████ │  4  15 │  3
Judoka 4 │ ██████ │ ██████ │ ██████ │ □   □  │ □   □  │ □   □  │  2  10 │  4
```

- `□` = Wit vak, invulbaar (judoka speelt in deze wedstrijd)
- `██` = Grijs vak, geblokkeerd (judoka speelt niet in deze wedstrijd)

### Kolom Headers (UI)
Elke wedstrijdkolom heeft **twee regels** in de header:
1. **Wedstrijdnummer** (1, 2, 3...) — klikbaar voor beurt-aanduiding (groen/geel/blauw)
2. **Sub-labels "wp jp"** — kleine grijze tekst die aangeeft welk invoerveld WP is en welk JP

```
│  1   │  2   │  3   │
│ wp jp│ wp jp│ wp jp│
```

**Implementatie:** `_content.blade.php` — sub-labels als inline `<div>` met `font-size: 9px` onder het wedstrijdnummer.
De breedte van `wp` (w-5) en `jp` (w-7) komt overeen met de invoervelden eronder.

