---
title: Plan — Poule-scoring werkt niet op staging (auto-toekenning + totalen)
type: plan
scope: judotoernooi
created: 2026-07-22
status: geïmplementeerd 22-07 (wrapper-fix hypothese a, bevestigd door "beide JP's zetbaar") — te testen op staging
---

# Plan — Poule-scoring (auto-punten) werkt niet op staging

## Symptoom (Henk, staging, Generale P#29)

Poule-wedstrijd staat op **groen** (actief). Bij het kiezen van JP=10 voor een judoka gebeurt
niets zichtbaars: de tegenstander vult niet auto naar 0/0, de winnaar krijgt geen WP=2, en de
WP/JP-totalen blijven 0. Zonder scoreboard-app moet dit gewoon werken.

## Wat is HARD bevestigd

- **Backend is goed:** `ScoreRegistrationTest` + `WedstrijdSchemaServiceExtraTest` = 35 tests groen.
  `registreerUitslag`/`updatePouleStand` kennen WP/JP correct toe.
- **Auto-toekenning-logica is correct:** `updateJP` (`_content.blade.php:1866-1904`) doet JP>0 →
  winnaar WP=2, verliezer WP=0 & JP=0; gelijkspel → beide WP=1; hantei → winnaar WP=2.
- **Totalen zijn client-side:** `getTotaalWP/JP` (`:1999-2013`) sommeren het **lokale** `wpScores`/
  `jpScores`-model — géén backend-roundtrip. **Dat de totalen op 0 blijven bewijst dat het lokale
  model nooit is bijgewerkt** → het probleem zit in de handler-uitvoering, niet in opslaan.
- **De invoergating is niet de oorzaak:** de match stond op groen; `isInvoerToegestaan` (`:1832`)
  staat groen/ongekleurd toe. De velden waren dus niet disabled (Henk kon JP kiezen).

## Root-cause hypothese (te bevestigen met console)

De JP-dropdown gebruikt een **compound `@change`**:
```blade
@change="updateJP(w, judoka.id, $event.target.value); saveScore(w, poule)"
```
Twee statements, `;`-gescheiden. Vermoeden: de `@alpinejs/csp`-evaluator voert deze compound niet
(volledig) uit → `updateJP` draait niet → lokaal model onveranderd → geen auto-fill, totalen 0.
Lokaal werkt het omdat strikte CSP daar uit staat. Dit is de énige JP-invoerweg, dus als 'ie faalt
ligt alle poule-scoring plat op staging.

**Tegenbewijs dat we serieus nemen:** er staan compound `;`-handlers in `publiek/index.blade.php`
(o.a. `:534`) die op prod draaien. Als díé werken, falen compound-handlers niet universeel op CSP
en is de oorzaak **reactiviteit** (updateJP draait wél, maar Alpine ziet de `wpScores`-hertoewijzing
niet). Daarom: **eerst bevestigen, dan bouwen.**

## Uitvoerstappen

### Stap 0 — Bevestigen (Henk, browser; Chrome-automation staat uit)
Console open (F12) → kies JP=10 in een groene poule-wedstrijd:
- **Rode Alpine/CSP-error** → hypothese (a): compound faalt → **Stap 1 (wrapper)**.
- **Geen error, model blijft leeg** → hypothese (b): reactiviteit → **Stap 1b** i.p.v. 1.

### Stap 1 — Wrapper-methode (bij hypothese a)
Vervang de compound `@change` door één methode:
```blade
@change="updateJpEnSla(w, judoka.id, $event.target.value, poule)"
```
```js
updateJpEnSla(wedstrijd, judokaId, value, poule) {
    this.updateJP(wedstrijd, judokaId, value);
    this.saveScore(wedstrijd, poule);
},
```
Eén methode-call in de attribuut-expressie = CSP-veilig (patroon uit de KB, "compound → wrapper").
Bestand: `resources/views/pages/mat/partials/_content.blade.php:521`.

### Stap 1b — Reactiviteit (bij hypothese b, alleen als Stap 0 dat aanwijst)
Onderzoek of `poule.wedstrijden[*]` reactief is; forceer zo nodig een reassign
(`poule.wedstrijden = [...poule.wedstrijden]`) na `updateJP`. Apart uitzoeken; niet blind.

### Stap 2 — Guard-test (verplicht — dit ontbrak, Henk's punt)
Breid `AlpineCspBindingTest` (statische blade-scan) uit: **flag elke `@`/`x-on:`-handler met een
methode-aanroep gevolgd door `;`** (compound met call). Zo kan deze klasse bug nooit stil
terugkomen. Puur-assignment-compounds (`a=1; b=2`) mogen blijven mits de scan uitwijst dat ze veilig
zijn; anders ook meenemen.

### Stap 3 — Sweep bestaande compound `;`-handlers met calls
Uit de scan (Fase 1): `publiek/index.blade.php:117` (`forceRefresh(); debugTapCount++`),
`coach/coachkaarten.blade.php:187` (clipboard + setTimeout). Beoordelen en naar wrapper/`x-on`-split
brengen als de guard-test ze terecht flag't. Puur-assignment-handlers
(`app.blade.php:168`, `toernooi/edit.blade.php:2754`, `publiek:392/534`) alleen als Stap 0 uitwijst
dat óók die falen.

## Docs bijwerken (Fase 1, onderdeel van de fix)
- CSP-regel expliciet maken: "compound `@`-handler met een methode-call + `;` → wrapper-methode".
  Doel-doc: de bestaande CSP-migratie/KB-doc. **Let op:** `docs/alpine-csp-migration.md` zit op 7.9k
  (net onder de 8k-indexgrens) → deze toevoeging vereist eerst **splitsen** (index + deeldoc),
  anders wordt de rest onvindbaar. Zie handover-punt.

## Tests
- Backend blijft groen (35 tests, ongewijzigd — backend wordt niet geraakt).
- `AlpineCspBindingTest` uitgebreid → faalt op de huidige compound JP-handler vóór de fix, groen erna.
- Frontend-gedrag (auto-fill/totalen) valt buiten PHPUnit; echte dekking = Playwright (los besluit).

## Risico / rollback
Blade + één JS-methode. Bij regressie: `git revert` op de fix-commit. Client-only, reload = escape.
