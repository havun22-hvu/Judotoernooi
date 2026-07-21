---
title: Plan — scoreboard ververst bij deelnemer-wijziging
type: plan
scope: judotoernooi
last_updated: 2026-07-22
status: in-uitvoering
---

# Plan — scoreboard-refresh bij deelnemer-wijziging

**Probleem (Henk):** de Android scoreboard-app ververst niet als de judoka's van de
al-getoonde (actieve) wedstrijd wijzigen — bv. door een drag-plaatsing, een uitslagcorrectie,
of het doorschuiven van een winnaar. Client-side reload regelt Henk in het scoreboard-project;
**server-side moet JudoToernooi het event sturen.**

## Diagnose

Het scoreboard krijgt deelnemers via `ScoreboardNotifier::notifyActiveMatchChanged`
(→ `ScoreboardAssignment`). Dat wordt alleen aangeroepen bij een **beurt**-wissel
(`MatUitslagController:503`) en een scoreboard-event (`ScoreboardController:267`) — dus als de
*actieve wedstrijd wisselt*, niet als de *deelnemers binnen* de actieve wedstrijd veranderen.
`plaatsJudoka` stuurt wel `MatUpdate('bracket')`, maar daar luistert het scoreboard niet op.

## Wijzigingen

| # | Bestand | Wijziging |
|---|---------|-----------|
| 1 | `ScoreboardNotifier` | nieuwe `notifyForPoule(int $toernooiId, Poule $poule)`: her-broadcast de toewijzing voor elke mat waarvan `actieve_wedstrijd_id` in deze poule zit (eliminatie = 1 poule met A+B, dekt plaatsing + winnaar-advance + B) |
| 2 | `MatBracketController::plaatsJudoka` | ná de bracket-broadcast `notifyForPoule(...)` aanroepen |
| 3 | `MatUitslagController` (na `verwerkUitslag`) | idem — een doorgeschoven winnaar kan de actieve wedstrijd op een andere mat zijn |
| 4 | `ScoreboardController::result` (na `verwerkUitslag`) | idem |

Idempotent: als de actieve wedstrijd niet wijzigde is het een onschadelijke re-broadcast met
verse deelnemer-data.

## Test
- `notifyForPoule` dispatcht `ScoreboardAssignment` voor een mat waarvan de actieve wedstrijd
  in de poule zit, met de bijgewerkte judoka-namen (Event::fake).
- Geen mat met actieve wedstrijd in de poule → geen dispatch.

## Buiten scope
Client-side reload in de Android scoreboard-app (Henk).
