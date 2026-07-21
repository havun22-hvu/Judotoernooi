---
title: Eliminatie Beurtkleur — regels & guards
type: reference
scope: judotoernooi
last_check: 2026-07-21
---

# Eliminatie Beurtkleur (dubbelklik) — regels

> Onderdeel van [Eliminatie Systeem](./README.md). Architectuur (DOM-styling, flow):
> zie memory `eliminatie-beurtaanduiding`.

Dubbelklik op een bracket-potje zet een 3-kleuren beurtstatus **per mat**:
groen = actief (speelt nu), geel = volgende, blauw = gereedmaken.

## Regel 1 — een blind (bye) krijgt NOOIT een beurtkleur

Een **blind** = een wedstrijd met maar één deelnemer (wit gevuld, blauw leeg) die nog niet
gespeeld is. Zo'n wedstrijd is niet speelbaar en mag dus geen groen/geel/blauw krijgen.

**Definitie speelbaar:** `wit && blauw && !is_gespeeld`. Alles anders (blind, of een nog-lege
ronde-2-wedstrijd die op een winnaar wacht) is **niet** kleurbaar.

> Let op: `uitslag_type === 'bye'` wordt **pas ná `advance-byes`** gezet. Een nog-niet
> doorgeschoven blind heeft `uitslag_type = null` — daarom mag de guard NIET op `uitslag_type`
> testen, maar op de aanwezigheid van beide deelnemers.

**Drie guard-lagen** (defense in depth):
| Laag | Plek | Check |
|------|------|-------|
| Frontend centraal | `toggleVolgendeWedstrijd()` | `if (!wedstrijd.wit \|\| !wedstrijd.blauw) return;` |
| Frontend bracket-ingang | `dblClickBracket()` | zelfde blind-check i.p.v. `uitslag_type === 'bye'` |
| Server | `MatUitslagController::doSetHuidigeWedstrijd()` | weiger (400) als `judoka_wit_id`/`judoka_blauw_id` leeg én niet echt gespeeld |

Gevolg: een blind blijft ongekleurd → wordt niet door de geel/blauw-drag-blokkade geraakt →
blijft dus gewoon versleepbaar (doorschuiven). De drag-blokkade op geel/blauw blijft ongewijzigd.

## Regel 2 — drag-highlight is groen, maar verdwijnt na loslaten

Tijdens het slepen kleurt het geldige doelslot groen (`outline 3px #22c55e` + `#dcfce7`) —
**dat is gewenst** en blijft. Het mag alleen niet blíjven staan na loslaten/terugzetten.

Twee opruim-routines moeten elkaars CSS-properties dekken, anders strandt een groene stijl
(→ "twee keer groen"):
- `_clearBracketTargetMarks()` (na `onEnd`) wist nu óók `border` (niet alleen `outline`).
- `applyBeurtaanduiding()` reset alle slots volledig (`border` + `outline` + `outlineOffset`
  + `boxShadow` + `backgroundColor`) vóór het de échte beurt-kleuren terugzet. Draait na elke
  re-render/drop, dus een gestrande drag-stijl kan niet overleven.
