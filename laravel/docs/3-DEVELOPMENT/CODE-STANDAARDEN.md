---
title: Code-standaarden
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Code-standaarden

> Het kernprincipe en de review-checklist staan hieronder — dat is wat je bij elke wijziging
> langsloopt. De patronen per onderwerp staan in [`CODE-STANDAARDEN/`](CODE-STANDAARDEN/).
> **Index-doc**, zie de wegwijzer onderaan.

## Kernprincipe

**Alle business logic hoort in MODEL METHODES, niet in views of controllers.**

Data komt uit de database, maar de INTERPRETATIE van die data moet via centrale methodes.

---

## 7. Checklist voor Code Review

Voordat code gemerged wordt, check:

- [ ] Geen `str_starts_with()` op gewichtsklasse voor business logic
- [ ] Geen `preg_match()` op titel strings voor data extractie
- [ ] Geen `$poule->titel` direct - gebruik `getDisplayTitel()`
- [ ] Geen hardcoded tolerantie/max_kg waarden
- [ ] Geen dubbele logica - centrale methodes gebruiken
- [ ] JavaScript krijgt pre-berekende waarden van backend
- [ ] Views bevatten geen business logic

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [GEWICHT-EN-POULES](CODE-STANDAARDEN/GEWICHT-EN-POULES.md) | Je vergelijkt gewichtsklassen of bouwt een pouletitel op. |
| [CATEGORIE-CONFIG](CODE-STANDAARDEN/CATEGORIE-CONFIG.md) | Je leest of schrijft categorie-configuratie. |
| [FRONTEND](CODE-STANDAARDEN/FRONTEND.md) | JavaScript of Blade — inclusief de CSP-regels. |
| [DUBBELE-LOGICA](CODE-STANDAARDEN/DUBBELE-LOGICA.md) | Je staat op het punt logica te kopiëren; en de paar plekken waar een string-check wél mag. |
| [MODEL-METHODES](CODE-STANDAARDEN/MODEL-METHODES.md) | Waar hoort nieuwe logica thuis — model, service of controller. |
| [ERROR-HANDLING](CODE-STANDAARDEN/ERROR-HANDLING.md) | Exceptions en calls naar externe diensten. |
| [BANDEN](CODE-STANDAARDEN/BANDEN.md) | Alles met bandkleuren. Let op: geen kyu. |
| [VERTALINGEN](CODE-STANDAARDEN/VERTALINGEN.md) | Je voegt tekst toe die vertaald moet worden. |
