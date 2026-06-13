---
title: Implementatieplan — Printbare Eliminatie-Brackets
type: plan
scope: judotoernooi
phase: 2-mpc
spec: laravel/docs/2-FEATURES/PRINTBARE-BRACKETS.md
created: 2026-06-13
---

# Plan: Printbare Eliminatie-Brackets

> Volledige spec staat in [`PRINTBARE-BRACKETS.md`](../laravel/docs/2-FEATURES/PRINTBARE-BRACKETS.md). Dit is het stap-voor-stap uitvoeringsplan.

---

## Volgorde (5 stappen, elk een eigen commit)

### Stap 1 — Service: `PrintableBracketService`

**Bestand:** `app/Services/PrintableBracketService.php` (nieuw)

**Wat:**
- Class met 3 publieke methodes: `buildLeegOpMaat(int $aantal)`, `buildStartposities(Poule $poule)`, `buildLive(Poule $poule)`
- Allemaal returnen dezelfde shape: `['a_bracket' => …, 'b_bracket' => …, 'meta' => …]` (zie spec)
- Constructor-injectie van `BracketLayoutService` en `BracketCalculator`
- Voor leeg-op-maat: genereer synthetische wedstrijden-arrays met juiste `ronde` + `bracket_positie` + `volgorde`, dan `BracketLayoutService::berekenABracketLayout()` aanroepen
- Voor startposities: laad wedstrijden, zet uitslag-velden op `null`, behoud namen in ronde 1 alleen
- Voor live: laad wedstrijden onaangepast

**Risico's:**
- BracketLayoutService verwacht arrays met specifieke keys. Eerst de eerste-stap test schrijven en daadwerkelijk een array uitvoeren door de service om de shape te verifiëren.

**Tests:** `tests/Unit/Services/PrintableBracketServiceTest.php` (nieuw)
- Per methode minimaal 3 cases (klein/middel/groot N; geen/sommige/alle wedstrijden gespeeld)

---

### Stap 2 — Controller-methodes in `NoodplanController`

**Bestand:** `app/Http/Controllers/NoodplanController.php` (gewijzigd)

**Wat:**
- Constructor uitbreiden met `PrintableBracketService $printableBracket`
- 4 nieuwe methodes:
  - `printBracketLeeg(Organisator, Toernooi, int $aantal)` — validatie 2..64
  - `printBracketStartposities(Organisator, Toernooi, Poule $poule)` — abort 404 als type ≠ eliminatie
  - `printBracketLive(Organisator, Toernooi, Poule $poule)` — idem
  - `brackets(Organisator, Toernooi, ?int $blokNummer = null)` — index-view met overzicht

**Tests:** `tests/Feature/NoodplanBracketPrintTest.php` (nieuw)
- 5 smoke-tests uit spec

---

### Stap 3 — Routes

**Bestand:** `routes/web.php` (gewijzigd)

**Wat:**
- Binnen bestaande `noodplan.` prefix-group: 4 routes toevoegen
- Route-modelbinding op `Poule` werkt automatisch
- Route-namen: `noodplan.brackets`, `noodplan.bracket-leeg`, `noodplan.bracket-startposities`, `noodplan.bracket-live`

---

### Stap 4 — Print-views (SVG)

**Bestanden (nieuw):**
- `resources/views/pages/noodplan/bracket-print.blade.php` — hoofd-template (extends `layouts.print`)
- `resources/views/pages/noodplan/partials/_bracket-print-a.blade.php` — SVG A-bracket
- `resources/views/pages/noodplan/partials/_bracket-print-b.blade.php` — SVG B-bracket

**Wat:**
- Hoofd-template: header (variant + titel + stempel), include A-partial, `<div class="page-break">`, include B-partial
- A-partial: één `<svg viewBox="0 0 W H">` met `<g class="potje" style="break-inside: avoid;">` per wedstrijd
- B-partial: zelfde, maar leest `$layout['niveaus']` ipv `$layout['rondes']`
- Print-CSS in `@push('styles')` met `<style @nonce>`:
  - `@page { size: A4 landscape; margin: 0.5cm; }`
  - `.potje { break-inside: avoid; page-break-inside: avoid; }`
  - `svg { width: 100%; height: auto; }`

**Risico's:**
- Lijntekeningen tussen rondes (de "haken") moeten als losse `<line>` buiten de `.potje`-groep om page-breaks niet te triggeren. Of: lijntekening helemaal weglaten en alleen rechte horizontale lijntjes per potje, met de verbindingen impliciet (kleinere visuele impact).
- Eerst proberen mét haken; als CSS-breaks vreemd renderen → vereenvoudigen.

---

### Stap 5 — Noodplan-index uitbreiden

**Bestanden (gewijzigd):**
- `resources/views/pages/noodplan/index.blade.php` — nieuwe sectie "Eliminatie brackets"
- `resources/views/pages/noodplan/brackets-index.blade.php` (nieuw) — overzicht-pagina

**Wat:**
- In `index.blade.php`: een card "Eliminatie brackets" met 2 knoppen:
  - "Leeg bracket op maat" → opent klein formuliertje (number-input 2..64 + submit naar `noodplan.bracket-leeg`)
  - "Overzicht eliminatie-poules" → `noodplan.brackets`
- `brackets-index.blade.php`: tabel met alle eliminatie-poules per blok, 2 print-knoppen per poule (startposities / live)

---

## Afhankelijkheden tussen stappen

```
Stap 1 (Service) ─┬─→ Stap 2 (Controller) ─→ Stap 3 (Routes) ─→ Stap 4 (Views) ─→ Stap 5 (Index)
                  │
                  └─ Tests van stap 1 staan los
```

Stappen 4 en 5 zijn afhankelijk van wat eerder ervoor; stappen kunnen niet parallel.

---

## Tests per stap

| Stap | Tests draaien | Verwacht |
|---|---|---|
| 1 | `php artisan test --filter PrintableBracketServiceTest` | Alle groen |
| 2 | + `--filter NoodplanBracketPrintTest` | Alle groen |
| 3 | `php artisan route:list \| grep noodplan.bracket` | 4 routes |
| 4 | Handmatig in browser staging: `/…/noodplan/bracket/leeg/16` | Bracket op scherm, print preview OK |
| 5 | Handmatig: `/…/noodplan/` toont "Eliminatie brackets" card | UI klikt door |

Volledige suite (`php artisan test --no-coverage`) na stap 2 en na stap 5.

---

## Risico-overzicht

| Risico | Mitigatie |
|---|---|
| `BracketLayoutService` accepteert geen synthetische arrays voor leeg-op-maat | In stap 1 als eerste een failing test schrijven met een synthetische array. Slaagt = service is tolerant; faalt = wrapper-laag erin (kleine extension van BracketLayoutService óf adapter in PrintableBracketService). |
| SVG print-breaks renderen lelijk (lijnen breken halverwege) | Vereenvoudigde lijn-tekening fallback: alleen `<line>` per potje, geen "haken" tussen rondes. |
| `BracketCalculator` bestaat al; mogelijk overlap met logica die ik in stap 1 zelf schrijf | Voor stap 1 eerst kort `BracketCalculator.php` doorlezen en hergebruiken waar mogelijk (al ingelezen vóór dit plan; `getRondeNaam(int $n, bool $voorAantal)` is bruikbaar). |
| Print-views breken door CSP (inline scripts/styles) | `<style @nonce>` en `<script @nonce>` gebruiken zoals overal elders in noodplan-views. |

---

## Commit-strategie

Elke stap = één commit:

1. `feat: PrintableBracketService — leeg/startposities/live data`
2. `feat: NoodplanController — bracket print methodes + tests`
3. `feat: routes — noodplan bracket print routes`
4. `feat: bracket print views (SVG, A/B aparte pagina's)`
5. `feat: noodplan index — eliminatie brackets sectie`

Geen merge naar main; staging-deploy aan einde voor handmatige test. Verwijzen naar handover voor merge-volgorde (huidige nachtbranches eerst, dan deze).

---

## Plan check

- ✅ Spec is helder en up-to-date in `PRINTBARE-BRACKETS.md`
- ✅ Stappen zijn atomair en testbaar
- ✅ Risico's geïdentificeerd met mitigaties
- ✅ Geen openstaande beslissingen
- ✅ Volgt Havun kwaliteitsnormen (tests, geen polling, CSP-nonces, geen credentials)

---

> **Wacht op "ga maar" van Henk voordat Fase 3 (codering) start.**
