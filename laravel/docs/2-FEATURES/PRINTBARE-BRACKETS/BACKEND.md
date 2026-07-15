---
title: Backend: PrintableBracketService en controller
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Backend: PrintableBracketService en controller

> Onderdeel van [Printbare Eliminatie-Brackets](../PRINTBARE-BRACKETS.md).

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

