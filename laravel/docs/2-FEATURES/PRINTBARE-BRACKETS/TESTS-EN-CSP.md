---
title: Tests en CSP/Alpine
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Tests en CSP/Alpine

> Onderdeel van [Printbare Eliminatie-Brackets](../PRINTBARE-BRACKETS.md).

## Tests

### Unit (`PrintableBracketService`)

- `buildLeegOpMaat(4)` → A-bracket 2 rondes, geen byes, geen scores
- `buildLeegOpMaat(12)` → A-bracket 4 rondes met juiste byes (4 ronde-1 + 4 byes naar ronde 2)
- `buildLeegOpMaat(2)` → 1 wedstrijd
- `buildLeegOpMaat(65)` → throw ValidationException
- `buildStartposities(eliminatiePoule)` → wedstrijden in ronde 1, latere rondes leeg
- `buildLive(poule_met_3_gespeeld)` → 3 wedstrijden gevuld, winnaars doorgeschoven

### Feature (smoke)

- `GET /…/noodplan/bracket/leeg/16` → 200, view rendert, bevat "viewBox", bevat geen lege fatale errors
- `GET /…/noodplan/bracket/leeg/0` → 404 of redirect
- `GET /…/noodplan/bracket/leeg/65` → 404 of redirect
- `GET /…/noodplan/bracket/{poule}/live` waar `$poule->type='poule'` → 404
- `GET /…/noodplan/bracket/{poule}/startposities` → 200, bevat deelnemersnamen

### Geen e2e nodig

Print-views worden door de browser-print engine getest; voor papier-output is e2e niet zinvol. Visuele kwaliteit toetst Henk handmatig op staging.

---

## CSP / Alpine.js

Print-views hebben minimale JS-behoefte (`window.print()` op page-load). Implementeren als `<script @nonce>` in de `layouts/print` template (al aanwezig).

Geen Alpine nodig op deze pagina's.

---

