---
title: Plan — favorieten-tab werkend + eliminatie-status + tab-beurtkleur
type: plan
scope: judotoernooi
last_updated: 2026-07-21
status: wacht-op-ga-maar
---

# Plan — Publieke favorieten-tab

Drie samenhangende wijzigingen op `/publiek/{slug}` → favorieten-tab.
Doc die dit raakt: `laravel/docs/2-FEATURES/INTERFACES/PUBLIEK.md` (bijgewerkt).

## A. Render-regressie: kaart blijft leeg  (bug)

**Symptoom (handover 21-07):** endpoint geeft 2 poules terug, tabjes Bram/Emma staan er,
maar de poule-kaart blijft leeg. `toonFavorietPoules` = true, container niet verborgen.

**Oorzaak:** de kaart hangt aan `index.blade.php:835`
`x-for="poule in favorietenPoules.filter(p => p.judokas.some(j => j.id === activeFavoriet))"`.
`activeFavoriet` blijft `null` → filter leeg → geen kaart (tabjes lopen over `favorieten`,
blijven dus wél staan). Het auto-selecteren hangt aan een `$watch('favorietenPoules')` die
alleen zet als `activeFavoriet` nog leeg is — fragiele timing. Types kloppen (int===int), `?.`
is al weg (commit 47c2142c). Niet in browser reproduceerbaar (Chrome-integratie uit) → fix is
defensief, Henk verifieert op staging.

**Fix (robuust, kan nooit meer stil leeg):**
- Nieuwe component-methode `kiesActieveFavoriet()`: kies de eerste favoriet-id die daadwerkelijk
  in een geladen poule zit; val terug op `favorieten[0]`.
- `loadFavorieten()`: ná `this.favorietenPoules = data.poules` imperatief
  `if (!activeFavoriet of activeFavoriet zit in geen enkele poule) activeFavoriet = kiesActieveFavoriet()`.
- Kaart-`x-for` (835) → component-methode `actievePoules()` die de gefilterde array teruggeeft
  (zelfde CSP-safe patroon als `favorietEliminatie()` / `matPreviewTekst()`).
- `$watch` mag als extra vangnet blijven; de imperatieve set is leidend.

Bestand: `resources/views/pages/publiek/index.blade.php`.

## B. Eliminatie-status i.p.v. ranglijst  (feature)

Een eliminatie-poule heeft geen stand. Toon de **status van dat moment + mat-nr**.

**Controller** `PubliekController::bouwEliminatieInfo()` uitbreiden. Nieuw payload-object:
| veld | inhoud |
|------|--------|
| `status` | `'komt'` \| `'afgevallen'` \| `'medaille'` |
| `groep` | `'A'` \| `'B'` — via `str_starts_with($ronde, 'b_')` |
| `ronde_naam` | bestaand, `BracketLayoutService::rondeNaam()` |
| `tegenstander` | bestaand (naam + club), alleen bij `komt` |
| `eindpositie` | bestaand, alleen bij `medaille` (1e/2e/3e, "3e (gedeeld)") |

- `komt`: eerstvolgende ongespeelde partij (bestaand).
- `afgevallen`: geen komende partij én geen medaille → pak de **laatste gespeelde partij die de
  favoriet verloor**, lees groep + ronde daaruit. Vervangt het kale "Uitgeschakeld".
- Groep-afleiding evt. als helper `BracketLayoutService::rondeGroep(string $ronde): string`
  (`'B'` als prefix `b_`, anders `'A'`) — houdt de A/B-logica bij de andere ronde-lookups.

**Blade** eliminatie-paneel (`index.blade.php:850-881`):
- `komt`: badge `A · 1/4 finale` + tegenstander (of "nog niet bekend") + `Mat X`.
- `afgevallen`: `Afgevallen — B · 1/8 finale`.
- `medaille`: 🏅 eindplaats (bestaand).
- Mat-nr staat al in de blauwe header; bij `komt` extra prominent in het paneel.

Bestanden: `PubliekController.php`, `BracketLayoutService.php`, `index.blade.php`.

## C. Naam-tab kleurt naar beurt  (UI)

De naam-tabs onder "mijn favorieten" (`index.blade.php:810`) krijgen de beurtkleur als
achtergrond, zodat je in één oogopslag ziet of je favoriet moet spelen / klaar staan / klaar maken.

- Component-methode `favorietBeurt(id)` → `'speelt'` (groen) | `'klaar'` (geel) | `'gereed'`
  (blauw) | `null`, afgeleid uit `favorietenPoules` (leest `is_aan_de_beurt` / `is_volgende` /
  `is_gereedmaken`). Houdt de zware `.some()`-expressie uit de blade (CSP-net + leesbaar).
- Tab `:class` (object-vorm): geen beurt = grijs; speelt = groen; klaar = geel; gereed = blauw.
- **Geselecteerde tab** (`activeFavoriet === id`): **oranje ring** (`ring-2 ring-orange-500`) +
  vet — bovenop de beurt-vulling, want blauw/groen/geel zijn al beurtkleuren. Zo blijven beurt
  én selectie beide zichtbaar.
- De losse hoek-bolletjes (816-824) kunnen weg: de volle tabkleur vervangt ze.
- **CSS-bundle meecommitten** (nieuwe `ring-orange-*` / bg-classes) — anders missen ze op prod.

Bestand: `resources/views/pages/publiek/index.blade.php`.

## Test & verificatie
- Unit/feature: `bouwEliminatieInfo()` — `komt` (groep A), `afgevallen` (groep B), `medaille`.
  Fixture met een kleine eliminatie-poule; **niet** op staging/prod draaien (RefreshDatabase).
- `AlpineCspBindingTest` groen houden (geen `?.` / dot-assignment / `x-model` in nieuwe expressies).
- Henk verifieert op staging: kaart rendert, tab-kleuren + oranje ring, eliminatie-tekst per fase.

## CSP-aandachtspunten (@alpinejs/csp build)
- Zware logica in component-methodes (`actievePoules`, `favorietBeurt`, `kiesActieveFavoriet`).
- `:class`/`:style` als **object**, nooit als string.
- Geen `?.`, geen `foo.bar = x`, geen `x-model="a.b"`.
