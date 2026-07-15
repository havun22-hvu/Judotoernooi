---
title: Scope, open vragen en bestandenlijst
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Scope, open vragen en bestandenlijst

> Onderdeel van [Printbare Eliminatie-Brackets](../PRINTBARE-BRACKETS.md).

## Scope: wat NIET in deze levering zit

- Brackets voor "puntencompetitie"-poules (alleen `type === 'eliminatie'`)
- Combinatie A + B op één pagina (altijd apart)
- Export naar PDF buiten browser-print
- Realtime updates op de print-pagina (live = snapshot bij page load)

---

## Open vragen

Geen meer — alle scope-beslissingen zijn met Henk gemaakt:
- A/B apart: ✅
- Leeg-op-maat met N als input + eerste-volle-ronde-hoogte: ✅
- Geen pagina-limiet, browser breekt natuurlijk af, `break-inside: avoid` per potje: ✅

---

## Bestanden — concrete lijst

### NIEUW
- `app/Services/PrintableBracketService.php`
- `resources/views/pages/noodplan/bracket-print.blade.php`
- `resources/views/pages/noodplan/brackets-index.blade.php`
- `resources/views/pages/noodplan/partials/_bracket-print-a.blade.php`
- `resources/views/pages/noodplan/partials/_bracket-print-b.blade.php`
- `tests/Unit/Services/PrintableBracketServiceTest.php`
- `tests/Feature/NoodplanBracketPrintTest.php`

### GEWIJZIGD
- `app/Http/Controllers/NoodplanController.php` — 4 nieuwe methodes
- `routes/web.php` — 4 nieuwe routes in noodplan group
- `resources/views/pages/noodplan/index.blade.php` — sectie "Eliminatie brackets"
- `app/Services/BracketLayoutService.php` — alleen indien `groepeerPerRonde` zonder DB-objects moet werken (zie open punt hieronder)

### Open punt voor `/mpc`

Of `BracketLayoutService` zonder aanpassing aanvaardt dat onze synthetische wedstrijden voor "leeg op maat" puur array-data zijn (geen Eloquent models). Bij doornemen van de service blijken alle inputs al `array`-shape (uit `getSchemaVoorMat`), dus dit zou direct moeten werken. Verifiëren tijdens implementatie.
