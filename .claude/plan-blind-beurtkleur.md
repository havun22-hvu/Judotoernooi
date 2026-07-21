---
title: Plan — blind mag geen beurtkleur + drag-groen opruimen
type: plan
scope: judotoernooi
last_updated: 2026-07-21
status: in-uitvoering
---

# Plan — eliminatie blind-beurtkleur + gestrande drag-groen

Doc: `laravel/docs/2-FEATURES/ELIMINATIE/BEURTKLEUR.md`. Symptomen (Henk, 21-07):
blind (Hidde/Zakaria) krijgt via dubbelklik groen/blauw (mag niet); en na slepen+terugzetten
blijft een tweede groen hangen. Drag-blokkade op geel/blauw is correct — ongewijzigd.

## Bestanden + volgorde

| # | Bestand | Wijziging | Bug |
|---|---------|-----------|-----|
| 1 | `_content.blade.php` `toggleVolgendeWedstrijd` (~2427) | vroege `return` als `!wedstrijd.wit \|\| !wedstrijd.blauw` | 1 |
| 2 | `_content.blade.php` `dblClickBracket` (1257) | `uitslag_type==='bye'`-test → blind-check (wit/blauw) | 1 |
| 3 | `MatUitslagController::doSetHuidigeWedstrijd` (~451) | weiger blind (leeg wit/blauw én niet echt gespeeld), respecteer `$alInSelectie`-skip | 1 |
| 4 | `_content.blade.php` `applyBeurtaanduiding` reset (1763-1766) | reset ook `outline`/`outlineOffset`/`boxShadow` | 2 |
| 5 | `_content.blade.php` `_clearBracketTargetMarks` (2841) | reset ook `border` | 2 |

## Test
- Feature: `MatUitslagController` POST met een blind-wedstrijd als `actieve_wedstrijd_id` → 400.
  Echte 2-deelnemer-wedstrijd → 200. (Guard geldt niet als de wedstrijd al in de mat-selectie
  stond.)
- `AlpineCspBindingTest` groen houden (guards zitten in JS-methodes, geen blade-`?.`/dot-assign).
- Frontend (staging, Henk): blind dubbelklik → geen kleur; blind slepen → groen tijdens sleep,
  weg na loslaten/terugzetten; geen twee-keer-groen meer.

## Risico
- Bracket-code met bekende gevoeligheid (memory: max 2 pogingen). Velden `wit`/`blauw` bevestigd
  via bestaand gebruik (`heeftOnverwerkteByes`, regel ~1410). Server-veld: `judoka_wit_id`/
  `judoka_blauw_id` (`MatBracketController` bye-detectie).
