---
title: Plan — uitslagcorrectie propageert naar herkansing (B) + self-healing
type: plan
scope: judotoernooi
last_updated: 2026-07-21
status: in-uitvoering
---

# Plan — correctie-propagatie eliminatie + probleem 1 rollback

Scenario (Henk, echte test): matscheids voert verkeerde winnaar in (Guus), jury corrigeert
(Vince). De A-groep wordt correct herzien, maar de **herkansing (B)** niet → wed#25275 blijft
`wit=Sam, blauw=Guus, winnaar=Vince` (winnaar geen deelnemer), Guus zit vast, bracket ≠ live-mat.
**NB: niets resetten — dit klooien moet het systeem netjes opvangen.**

## Diagnose (agent 21-07)

`EliminatieService::verwerkUitslag` (`:628-642`) roept bij correctie twee opruimers aan:
- A-groep: `verwijderUitLatereRondes` (`WinnerCalculator:354-397`) → reset winnaar_id/is_gespeeld
  + cascade. **Correct.**
- B-groep: `verwijderUitB` (`WinnerCalculator:329-347`) → leegt alleen het slot, **reset de
  uitslag NIET en cascadeert niet**. ← de bug.

## Wijzigingen

| # | Bestand | Wijziging |
|---|---------|-----------|
| 1 | `WinnerCalculator::verwijderUitB` (329-347) | bij leegmaken slot óók `winnaar_id`/`is_gespeeld`/`gespeeld_op` resetten als die judoka daar winnaar was; daarna `verwijderUitLatereRondes(...,'B',...)` cascade binnen B |
| 2 | `WinnerCalculator::plaatsVerliezerDubbel` (41-48) + `plaatsVerliezerIJF` (102-108) | doelwedstrijd resetten als `is_gespeeld` vóór de nieuwe verliezer erin komt (vangnet) |
| 3 | `Wedstrijd::isEchtGespeeld` (151-153) | self-healing: winnaar moet deelnemer (`wit`/`blauw`) zijn — een stale winnaar telt niet als "gespeeld". Geneest bestaande corruptie (wed#25275) zónder DB-reset |
| 4 | `PouleEliminatieController` (95-102) | `oudeWinnaarId = $wedstrijd->winnaar_id` vóór de update doorgeven i.p.v. `null` (correcties via deze route sloegen de cascade over) |
| 5 | `_content.blade.php setWedstrijdStatus` catch (2599) | `applyBeurtaanduiding()` aanroepen → na server-400 verdwijnt de optimistische kleur (probleem 1: onvolledige/blind wedstrijd) |

## Test
- `verwerkUitslag`-correctie: bouw A-kf gekoppeld aan B-kf (`herkansing_wedstrijd_id` +
  `verliezer_naar_slot`); speel uit (winnaar Guus → Vince in B); corrigeer (winnaar Vince) →
  assert B-kf heeft géén stale winnaar, is niet meer `is_gespeeld`, en de nieuwe verliezer
  (Guus) staat er schoon in.
- `isEchtGespeeld`: winnaar = niet-deelnemer → false; winnaar = deelnemer → true; bye → true.
- Lokaal SQLite, nooit op server.

## Risico
Hoog-risico bracket-correctheid, maar patroon bestaat al (`verwijderUitLatereRondes`). `===`
vs `==`: deelnemer-vergelijking met `==` (int/string tolerant, conform codebase-conventie).

## Vervolg (21-07, na staging-test) — B-bracket symptomen

Reconstructie uit activiteiten-log poule 33: 20:12 Guus als A-kf-winnaar ingevoerd (fout),
20:14 gecorrigeerd naar Vince — met de **oude** `verwijderUitB` (vóór deze fix) → wed#25275
bleef `winnaar=Vince` terwijl slots Sam+Guus werden. Bestaande corruptie, ontstaan vóór de fix.

Symptomen + fixes:
1. **Ravi Prins (onvolledige wed#25278) zit vast op geel, niet uit te zetten.** Mijn beurt-guard
   blokkeerde óók het *deselecteren*. → guard alleen in de **nieuwe-selectie**-tak van
   `toggleVolgendeWedstrijd`; blind-guard uit `dblClickBracket` (deselect moet altijd kunnen).
2. **Sam/Guus onspeelbaar** ("Dit is niet de winnaar"): de drag-check leest rauwe `is_gespeeld`
   + `winnaar_id`. → `MatBracketController:120,165` gebruiken `isEchtGespeeld()`.
3. **Actieve self-healing** (Henk akkoord): `getSchemaVoorMat` reset bij elke mat/bracket-load
   een eliminatie-wedstrijd waarvan `winnaar_id` geen deelnemer is → geneest wed#25275 definitief.
   Idempotent, gelogd.

Test: self-healing reset een corrupte wedstrijd bij schema-load; deselect van een onvolledige
wedstrijd werkt. Bestaande wed#25275 wordt genezen zodra Henk de mat opent.
