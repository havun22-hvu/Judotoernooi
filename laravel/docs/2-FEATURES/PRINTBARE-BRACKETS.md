---
title: Printbare Eliminatie-Brackets (Noodplan)
type: reference
scope: judotoernooi
status: draft — Fase 1 MPC
last_check: 2026-07-15
---

# Printbare Eliminatie-Brackets

> **Doel:** in het Noodplan de eliminatie-brackets net zo printbaar maken als de poule-wedstrijdschema's. Drie varianten: leeg-op-maat, startposities, live.
> **Onderdeel van:** Noodplan (`pages/noodplan/`)
> **Doelgroep:** organisator/hoofdjury die brackets op papier wil als backup of voor de jurytafel.

> **Index-doc:** hieronder de drie varianten; de details staan in deeldocs onder `PRINTBARE-BRACKETS/` — zie [Waar staat wat](#waar-staat-wat).

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

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---|---|
| [RENDER-REGELS.md](PRINTBARE-BRACKETS/RENDER-REGELS.md) | Je wilt weten hoe hoog een bracket wordt, waar de pagina afbreekt, of welke URL bij welke variant hoort. |
| [BACKEND.md](PRINTBARE-BRACKETS/BACKEND.md) | Je raakt `PrintableBracketService` of de `NoodplanController`-methodes aan. |
| [FRONTEND-VIEWS.md](PRINTBARE-BRACKETS/FRONTEND-VIEWS.md) | Je past de print-layout, de Blade-views, de SVG-rendering of de invul-strookjes aan. |
| [TESTS-EN-CSP.md](PRINTBARE-BRACKETS/TESTS-EN-CSP.md) | Je schrijft tests voor de service of loopt tegen CSP/Alpine-beperkingen aan. |
| [SCOPE-EN-BESTANDEN.md](PRINTBARE-BRACKETS/SCOPE-EN-BESTANDEN.md) | Je wilt weten wat buiten scope valt, welke open vragen er zijn, of welke bestanden nieuw/gewijzigd zijn. |
