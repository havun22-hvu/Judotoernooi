---
title: Bracket rendering: architectuur en layout
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Bracket rendering: architectuur en layout

> Onderdeel van [Eliminatie Systeem](./README.md).

## Bracket Rendering (Mat Interface)

> **Laatste update:** 2026-02-10 — herschreven van Alpine x-html naar Blade + DOM

### Architectuur

**Oud (vóór 10 feb 2026):** Alpine `x-html="renderBracket()"` bouwde ~300 regels HTML als JS string en herbouwde de hele DOM na elke drop. Dit gaf merkbare vertraging.

**Nieuw:** Server-rendered Blade partials + SortableJS + pure DOM updates. Zelfde patroon als de 4 andere werkende DnD-pagina's (zaaloverzicht, wedstrijddag poules, poule index).

### Waarom deze keuze?

1. **Performance**: Blade rendert HTML server-side → geen JS string building → geen complete DOM rebuild
2. **Consistentie**: Alle 5 DnD-pagina's gebruiken nu hetzelfde patroon (SortableJS + DOM updates)
3. **Onderhoud**: Layout wijzigingen in Blade (PHP) ipv JS template strings — Tailwind purge werkt correct
4. **Touch support**: SortableJS voor alle devices (PC + tablet) — geen aparte HTML5 DnD fallback nodig

### Data flow

```
1. Page load → laadWedstrijden() → Alpine poules data
2. x-init → laadBracketHtml(pouleId, groep) → AJAX POST naar /mat/bracket-html
3. Server: MatController::getBracketHtml()
   → Poule + wedstrijden laden
   → BracketLayoutService berekent posities
   → Blade partial renderen → HTML response
4. Client: container.innerHTML = html
5. initBracketSortable() + applyBeurtaanduiding()
```

### Na een drop (judoka handmatig verplaatsen — override)

> De winnaar schuift bij een uitslag al **automatisch** door (zie *Uitslag verwerken*).
> Drag-and-drop is bedoeld voor **handmatige correctie/override** van een slot.


```
1. SortableJS onEnd → DOM revert ALTIJD (clone verwijderen, item terugzetten)
2. fakeEvent bouwen → window.dropJudoka() aanroepen
3. dropJudoka() doet validatie (slot check, wachtwoord, etc.)
4. API call naar plaatsJudoka → server response met updated_slots[]
5. updateAlleBracketSlots() update individuele DOM elementen
6. Bij fout: location.reload()
```

### Positie-berekening (BracketLayoutService)

| Constante | Waarde | Beschrijving |
|-----------|--------|--------------|
| `SLOT_HEIGHT` | 28px | Hoogte van 1 slot (wit of blauw) |
| `POTJE_HEIGHT` | 56px | 2 × SLOT_HEIGHT (wit + blauw) |
| `POTJE_GAP` | 8px | Verticale ruimte tussen potjes |
| `HORIZON_HEIGHT` | 20px | Ruimte tussen B-bracket bovenste/onderste helft |

**A-bracket**: Recursieve `berekenPotjeTop(niveau, potjeIdx)` — elk potje is gecentreerd tussen de 2 potjes van het vorige niveau.

**B-bracket**: Mirrored layout — bovenste helft + onderste helft met horizon gap. Rondes `_1` en `_2` worden gegroepeerd per niveau.

