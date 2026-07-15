---
title: Eliminatie Implementatie (service, uitslag, velden)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Eliminatie Implementatie (service, uitslag, velden)

> Onderdeel van [Eliminatie Systeem](./README.md).

## Implementatie

### Service

```php
use App\Services\EliminatieService;

$service = app(EliminatieService::class);

// Genereer bracket
$service->genereerBracket($poule, $judokaIds);

// Verwerk uitslag (schuift winnaar + verliezer automatisch door — zie hieronder)
$service->verwerkUitslag($wedstrijd, $winnaarId, $oudeWinnaarId, $type);

// Statistieken
$stats = $service->berekenStatistieken($n);
```

### Uitslag verwerken: automatisch doorschuiven

> **Sinds 2026-06-12.** Eerder schoof alleen de verliezer automatisch door; de winnaar
> moest handmatig (drag-and-drop) naar de volgende ronde. Nu schuiven **beide** automatisch.

`EliminatieService::verwerkUitslag()` is de centrale plek waar elke uitslag wordt verwerkt
(aangeroepen door zowel `Api\ScoreboardController::registreerUitslag` als
`MatUitslagController`). Bij **elke** uitslag — eerste registratie én correctie, A-groep én
B-groep — gebeurt:

1. **Winnaar → volgende ronde (A én B):** als de wedstrijd een `volgende_wedstrijd_id` heeft,
   wordt de winnaar in het juiste slot van die wedstrijd gezet:
   `winnaar_naar_slot` (`'wit'`/`'blauw'`) → `judoka_wit_id`/`judoka_blauw_id`.
2. **Verliezer → B-groep:** alleen bij A-groep wedstrijden, deterministisch via
   `herkansing_wedstrijd_id` + `verliezer_naar_slot` (`WinnerCalculator::plaatsVerliezer*`).
3. **Correctie-cascade:** als de winnaar wijzigt (`$oudeWinnaarId != $winnaarId`) wordt de
   oude winnaar éérst uit alle latere rondes verwijderd (`verwijderUitLatereRondes`),
   daarna wordt de nieuwe winnaar doorgeschoven (stap 1).

**Volgorde in `verwerkUitslag`:** correctie-cascade → winnaar doorschuiven → verliezer naar B.

#### Eigenschappen & randgevallen

| Geval | Gedrag |
|-------|--------|
| **Eindpunt** (finale, brons `b_halve_finale_2`, `BF`) | Geen `volgende_wedstrijd_id` → winnaar wordt **niet** doorgeschoven (correct). |
| **Idempotent** | Zelfde winnaar nogmaals plaatsen = dezelfde waarde, geen neveneffect. |
| **Handmatige DnD** | Blijft werken als **override/correctie** (`MatBracketController::plaatsJudoka`). Een DnD-plaatsing houdt stand tot de bronwedstrijd opnieuw wordt verwerkt; dan wint de auto-advance weer. |
| **Bye in volgende ronde** | Géén extra cascade. De winnaar wordt alleen geplaatst; de bye-wedstrijd registreert de hoofdjury handmatig (zie *B-Start Byes* hierboven). |
| **Ontbrekende `winnaar_naar_slot`** | Winnaar wordt niet geplaatst, er wordt een `Log::warning` geschreven (duidt op configuratiefout in bracket-schema), geen fatale fout. |

#### Realtime

De plaatsing gebeurt via `Wedstrijd::update()`/`save()` op de volgende wedstrijd, wat het
bestaande broadcast-event afvuurt (`SafelyBroadcasts`-trait) → bracket/mat-interface werkt
live bij. Geen extra broadcast-implementatie nodig.

### Database Velden

| Veld | Type | Beschrijving |
|------|------|--------------|
| `groep` | A/B | Hoofdboom of herkansing |
| `ronde` | string | b_achtste_finale, b_achtste_finale_2, etc. |
| `bracket_positie` | int | Wedstrijdnummer binnen ronde (1-based) |
| `volgende_wedstrijd_id` | int | Winnaar schuift automatisch naar deze wedstrijd (zie *Uitslag verwerken*) |
| `winnaar_naar_slot` | wit/blauw | Slot in de volgende wedstrijd waar de winnaar landt |
| `slot_wit` | int | Slotnummer voor wit (2N-1) |
| `slot_blauw` | int | Slotnummer voor blauw (2N) |

