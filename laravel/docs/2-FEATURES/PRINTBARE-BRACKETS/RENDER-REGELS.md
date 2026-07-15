---
title: Render-regels: hoogte, paginering, URLs
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Render-regels: hoogte, paginering, URLs

> Onderdeel van [Printbare Eliminatie-Brackets](../PRINTBARE-BRACKETS.md).

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

