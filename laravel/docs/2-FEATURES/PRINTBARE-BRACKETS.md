---
title: Printbare Eliminatie-Brackets (Noodplan)
type: reference
scope: judotoernooi
status: draft — Fase 1 MPC
last_check: 2026-06-13
---

# Printbare Eliminatie-Brackets

> **Doel:** in het Noodplan de eliminatie-brackets net zo printbaar maken als de poule-wedstrijdschema's. Drie varianten: leeg-op-maat, startposities, live.
> **Onderdeel van:** Noodplan (`pages/noodplan/`)
> **Doelgroep:** organisator/hoofdjury die brackets op papier wil als backup of voor de jurytafel.

---

## Drie varianten

| Variant | Bron | Wedstrijden | Use-case |
|---|---|---|---|
| **Leeg op maat** | N judoka's (geen poule) | Lege namenvakken + scorevakken | Algemeen template, vooraf printen |
| **Startposities** | Bestaande eliminatie-poule | Deelnemers in startslots, scores leeg | Backup vóór toernooidag, jurytafel-uitprint |
| **Live** | Bestaande eliminatie-poule | Gespeelde wedstrijden met scores + winnaars doorgeschoven + herkansing B gevuld | Backup tijdens toernooi, snapshot huidige stand |

### Wat ze gemeen hebben

- Bracket structuur identiek aan mat-interface (`_bracket.blade.php` + `_bracket-b.blade.php`)
- A-bracket (hoofdtoernooi) en B-bracket (herkansing) altijd **op aparte pagina's**
- A4 liggend (`landscape`), randen 0.5cm
- Header: datum/tijd-stempel, leeftijds-/gewichtsklasse, mat (indien toegewezen)
- Footer: medailleplekken (🥇🥈🥉)
- Render-output: **SVG** met `viewBox` (vector, geen px-positionering → schaalt naar elk papierformaat)

### Wat ze verschillen

| | Leeg op maat | Startposities | Live |
|---|---|---|---|
| Bron | int `N` | `Poule` (type=eliminatie) | `Poule` (type=eliminatie) |
| Deelnemernamen | Lege regels | Naam + club ingevuld | Naam + club ingevuld |
| Wedstrijdnummers | Genummerd 1..N | Uit DB | Uit DB |
| Scores | Leeg vakje | Leeg vakje | Ingevuld waar gespeeld; doorgestreepte naam = verliezer |
| Winnaarspijl | Lege lijn | Lege lijn | Naam doorgeschoven naar volgende ronde |
| B-bracket | Idem leeg | Idem leeg (alleen structuur) | Gevuld met daadwerkelijke A-verliezers |
| Datum/tijd-stempel | "Leeg template" + N | Poule-titel + "Startposities" | Poule-titel + "Live snapshot om HH:MM" |

---

## Hoogte-regel: "eerste volle ronde bepaalt hoogte"

> Vraag van Henk: bij N=12 wil ik geen 16-bracket met 4 lege regels. De eerste volledig gevulde ronde (in dit geval ronde 2 met 8 echte matches) bepaalt de hoogte. Byes worden niet als lege ronde-1-vakken getekend.

**Concreet:** bij N=12 (volgende macht van 2 = 16, dus 4 byes):
- Ronde 1: 4 echte wedstrijden (8 judoka's strijden), 4 byes
- Ronde 2: 8 wedstrijden (4 winnaars uit ronde 1 + 4 byes) ← **eerste volle ronde**
- Ronde 3 (kwart): 4 wedstrijden
- enz.

**Visueel:**
- De 4 ronde-1-byes verschijnen **niet** als lege vakken in kolom 1
- Hun byes "starten" in kolom 2 op de positie waar ze tegen een ronde-1-winnaar uitkomen
- Bracket-hoogte = 8 potjes × (POTJE_HEIGHT + POTJE_GAP)

`BracketLayoutService::berekenABracketLayout()` werkt al zo: het start de layout vanaf de eerste ronde die echte wedstrijden bevat. Voor "leeg op maat" moeten we synthetische wedstrijden in dat formaat genereren zodat dezelfde service werkt.

---

## Paginering: natuurlijke browser-pagebreak

Het hele bracket-SVG wordt als één doorlopend stuk geprint op A4 liggend. Past het niet op één pagina → de browser breekt af en gaat verder op pagina 2.

**Geen limiet op N**, geen linkerhelft/rechterhelft splitsing, geen waarschuwingen. Wij zorgen alleen dat:

- Potjes (de wedstrijd-vakjes met 2 namen) **niet doormidden worden gesneden** → CSS `page-break-inside: avoid` per `<g>` per potje
- Ronde-headers (1/8, 1/4, ...) **herhalen** op elke pagina → CSS `position: sticky` werkt niet in print; oplossing: SVG-header los renderen op elke pagina via `@page` margin + Blade-trick óf headers binnen het bracket-SVG zodat ze meeschuiven (eenvoudiger)

**Praktisch:** in de SVG starten we elke "verticale strip" (één wedstrijd-potje met zijn verbindingslijntjes) als een `<g class="potje">` met CSS `break-inside: avoid`. De browser doet de rest.

---

## URL-structuur

Onder bestaande `noodplan.` prefix:

```
GET /{org}/toernooi/{toernooi}/noodplan/bracket/leeg/{aantal}              → leeg op maat (aantal: 2..32)
GET /{org}/toernooi/{toernooi}/noodplan/bracket/{poule}/startposities      → startposities (poule moet type=eliminatie zijn)
GET /{org}/toernooi/{toernooi}/noodplan/bracket/{poule}/live               → live (idem)
GET /{org}/toernooi/{toernooi}/noodplan/brackets/{blok?}                    → overzicht van alle eliminatie-poules in een blok, met links naar startposities/live
```

Route-namen: `noodplan.bracket-leeg`, `noodplan.bracket-startposities`, `noodplan.bracket-live`, `noodplan.brackets`.

Middleware: dezelfde als andere noodplan-routes (`CheckToernooiRol::class . ':jury'`).

---

## Backend

### Nieuwe service: `PrintableBracketService`

**Verantwoordelijkheid:** brug tussen de drie varianten en de bestaande `BracketLayoutService`.

```
namespace App\Services;

class PrintableBracketService
{
    public function __construct(
        private BracketLayoutService $bracketLayout,
    ) {}

    // Variant 1: leeg op maat
    public function buildLeegOpMaat(int $aantal): array;

    // Variant 2: startposities (poule, alleen structuur + namen, geen scores)
    public function buildStartposities(Poule $poule): array;

    // Variant 3: live (poule, alles gevuld zoals nu in DB)
    public function buildLive(Poule $poule): array;
}
```

**Return-shape** (in alle 3 gevallen):

```
[
    'a_bracket' => [
        'rondes' => [...],          // zoals BracketLayoutService::berekenABracketLayout
        'totale_hoogte' => int,
        'medaille_data' => [...],
    ],
    'b_bracket' => [
        'niveaus' => [...],         // zoals BracketLayoutService::berekenBBracketLayout
        'totale_hoogte' => int,
        'medaille_data' => [...],
    ],
    'meta' => [
        'variant' => 'leeg'|'startposities'|'live',
        'aantal_deelnemers' => int,
        'pagineren' => false|true,  // true bij >16
        'splits' => 'geen'|'links_rechts',
        'titel' => string,           // "Leeg template – 12 judoka's" | poule-titel
        'stempel' => string,         // "Snapshot om 14:32" | "Startposities" | "Leeg template"
    ],
]
```

### `buildLeegOpMaat(int $aantal)`

1. Bereken structuur: `$ronde0Wedstrijden = max(1, $aantal - 2^(floor(log2($aantal-1))))`. Dit is het aantal echte ronde-0-matches voor een N-bracket met byes (zelfde formule als BracketCalculator).
2. Genereer synthetische wedstrijden-array in het formaat dat `BracketLayoutService` verwacht (`ronde` veld als `'achtste_finale'` / `'kwartfinale'` / …, `bracket_positie` 1..N, `wit`=null, `blauw`=null, `volgorde`=index).
3. Roep `berekenABracketLayout()` aan.
4. Genereer ook B-bracket structuur: voor N deelnemers genereert eliminatie altijd een bijbehorende B-bracket (zie `BracketCalculator`); zelfde aanpak.

### `buildStartposities(Poule $poule)` + `buildLive(Poule $poule)`

1. Laad poule met `wedstrijden.judokaWit.club`, `wedstrijden.judokaBlauw.club`, `wedstrijden.winnaar`.
2. Voor startposities: kopie maken van wedstrijden-array, scores leeg zetten (`uitslag_wit=null`, `uitslag_blauw=null`, `winnaar_id=null`); doorgeschoven judoka's in latere rondes leeghalen (= alleen `bracket_positie` deelnemers in ronde 1).
3. Voor live: data ongewijzigd.
4. Splits A en B wedstrijden op `ronde`-prefix (`b_` = B).
5. Roep respectievelijk `berekenABracketLayout()` en `berekenBBracketLayout()` aan.

### Nieuwe controller-methodes in `NoodplanController`

(Niet nieuwe controller; aansluiten op bestaande conventies.)

```
public function printBracketLeeg(Organisator $organisator, Toernooi $toernooi, int $aantal): View;
public function printBracketStartposities(Organisator $org, Toernooi $t, Poule $poule): View;
public function printBracketLive(Organisator $org, Toernooi $t, Poule $poule): View;
public function brackets(Organisator $org, Toernooi $t, ?int $blokNummer = null): View;  // index
```

Validatie:
- `$aantal` tussen 2 en 64 inclusief (hard cap puur als sanity-check; >64 in 1 categorie komt niet voor)
- `$poule->type === 'eliminatie'` (else 404)
- `$poule->toernooi_id === $toernooi->id` (route-binding doet dit al deels)

---

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
