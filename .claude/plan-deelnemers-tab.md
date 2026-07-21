---
title: Plan — Deelnemers-tab render- en klap-bugs
type: plan
scope: judotoernooi
created: 2026-07-21
---

# Plan — Deelnemers-tab op de publieke live-app

## Symptomen (Henk, Generale-toernooi op staging)

1. **H-15 / D-15 klappen niet uit.** Klik op de leeftijdsklasse-header → visueel gebeurt niets.
   Content (gewichtsklasse-knoppen) blijft dicht.
2. **Jeugd toont wél judoka-namen, maar geen gewichtsklassen.** Dit is by design (variabele
   indeling → géén vaste gewichtsklassen), maar Henk wil zeker weten dat het klopt en dat de
   categorie-label bovenaan blijft staan.

## Diagnose — waar zit de bug (per symptoom)

### Symptoom 1: H-15/D-15 klappen niet uit

**Verdachte plek:** `resources/views/pages/publiek/index.blade.php:582-701`.
Structuur:
```
<div x-data="{ collapsed: false }">      ← buitenste, houdt klap-state
  <h2>
    <button @click="collapsed = !collapsed">…</button>
  </h2>
  <div x-show="!collapsed" x-collapse
       x-data="nullableSelection"           ← nested x-data
       data-state-key="openGewicht">
    @if($isDynamisch) … @else … @endif
  </div>
</div>
```

**Vier hypotheses, in volgorde van waarschijnlijkheid:**

1. **`x-collapse` bij de eerste render blijft "afgemeten" op de content-hoogte.** Alpine's
   `x-collapse`-plugin meet de element-hoogte bij mount en cachet die voor de animatie. Als de
   content bij mount `display: none` had (zoals bij nested `x-show`-ancestors), is de gemeten
   hoogte 0. Na een klik animeert 'ie van 0 naar 0. Test: verwijder tijdelijk `x-collapse` van
   de content-`<div>` → als het dan wél opent, is dít de oorzaak. Fix: `x-collapse` weg (gewone
   `x-show`) of `x-collapse.duration.150ms` proberen (herevalueert soms).

2. **CSP-error stopt render voor deze subboom.** Als érgens in de blade een Alpine-expressie
   throwt (denk aan de favorieten-tab-story van 21-07), stopt Alpine met evalueren op die tak.
   `@click` op de header werkt dan wel, maar `x-show="!collapsed"` reageert niet meer.
   Test: DevTools console open, klikken, check op "Alpine Expression Error".

3. **Nested `x-data` scope-conflict.** De binnenste `x-data="nullableSelection"` overschaduwt
   niets van de buitenste (`collapsed` staat er niet in), maar de `nullableSelection.init()`
   doet een `Object.defineProperty(this, 'openGewicht', …)`. Als dat throwt (bv. omdat
   `data-state-key` er niet is bij deze render-pass), stopt de binnenste `x-data`-init en
   propageert het naar `x-show` op dezelfde node. **Aan te tonen** met console-error.

4. **Klik lekt naar buiten.** De header-`<button>` staat *binnen* de `<h2>` die *buiten* de
   `x-data`-scope-child staat maar wél in de buitenste. Onwaarschijnlijk maar goedkoop uit te
   sluiten: `@click.stop` op de knop.

**Diagnose-volgorde (zonder gokken):**
- Console open → klik header H-15 → melden welke errors verschijnen (hypothese 2/3).
- Als geen error: temp-patch `x-collapse` weghalen op één plek → check of collapse-plugin de
  oorzaak is (hypothese 1).
- Als beide niks: `@click.stop` (hypothese 4).

### Symptoom 2: Jeugd zonder gewichtsklasse — verwacht gedrag

Bevestigd via `PubliekController.php:60`:
```php
if ($maxKgVerschil > 0 || empty($vasteGewichten)) {
    // Return all judokas under one key 'Alle', gesorteerd op leeftijd + gewicht
}
```
En de doc (regel 40-41): `max_kg_verschil > 0` → dynamische indeling, geen groepering.

**Geen bug.** Wél te doen: verifiëren dat de leeftijdsklasse-label duidelijk bovenaan staat
en visueel als "categorie" leesbaar is. In de blade (`:582-593`) staat de header met:
```
<span class="bg-blue-100 text-blue-800 …">{{ $leeftijdsklasse }}</span>
<span class="…">{{ $alleJudokas->count() }} judoka's</span>
```
Dat is al correct. Naam + leeftijd + gewicht per judoka in `:611-612`. Match met Henk's
verwachting ("categorie + naam zichtbaar bij dynamisch") — geen aanpassing nodig.

## Scope van de fix

**Alleen symptoom 1** (H-15/D-15 klappen niet uit). Symptoom 2 is by design; doc herschreven
in `PUBLIEK.md` zodat het expliciet is.

## Aanpak

**Stap 1 — diagnose op staging** (Henk, browser):
- Open Generale-toernooi op staging, deelnemers-tab.
- DevTools console open.
- Klik header H-15.
- Meld: (a) staat er een Alpine-error, (b) verandert `collapsed` in de DOM (Elements → check
  het `x-data`-attribuut of expand het via `Alpine.$data($0)`), (c) verschijnt de content maar
  onzichtbaar (`display: none` blijft), (d) niets in de DOM verandert.

**Stap 2 — fix op basis van diagnose** (Claude, code):
- Hypothese 1 (`x-collapse`-cache): vervang `x-collapse` door standaard `x-show`-transitie
  (`x-transition`) of `x-collapse.duration.200ms`. Test op staging.
- Hypothese 2 (Alpine-error): fix de throwende expressie in de blade — meestal een `?.` in
  een Alpine-attribuut of een compound `@click`.
- Hypothese 3 (nested `x-data` init throw): verplaats `nullableSelection` naar een aparte
  sibling of geef 'm defensieve init.
- Hypothese 4 (click-lek): `@click.stop` op de header-knop.

**Stap 3 — verificatie** (Henk):
- Alle leeftijdsklassen klappen open/dicht.
- Dynamische categorieën tonen namen direct.
- Vaste categorieën tonen gewichtsklasse-knoppen na openen.

## Wat NIET meenemen

- Refactor van de collapse-structuur. Twee `x-data`'s naast elkaar mag; de nesting werkt in de
  praktijk overal in het project. Alleen de concrete bug fixen.
- Layout-wijzigingen aan de dynamische lijst. Alleen als symptoom 2 blijkt écht een bug (Henk
  wil andere weergave) → apart plan.

## Testen

Handmatig op staging (browser). Geen nieuwe unit- of feature-tests: Alpine-render valt buiten
PHPUnit. `AlpineCspBindingTest` blijft de statische guard voor `?.`-regressies.

## Rollback

Blade-only edits. Bij regressie: één `git revert` op de fix-commit.
