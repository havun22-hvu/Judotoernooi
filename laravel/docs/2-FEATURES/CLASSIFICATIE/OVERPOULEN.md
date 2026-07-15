---
title: Overpoulen: detectie & regels
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Overpoulen: detectie & regels

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

## Wedstrijddag: Overpoulen per Categorie Type

### TL;DR

```
┌─────────────────────────────────────────────────────────────────────┐
│ KERNREGEL: Vast = JUDOKA afwijkend, Variabel = POULE afwijkend      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ 📦 VASTE GEWICHTSCATEGORIEËN (max_kg_verschil = 0)                 │
│ ───────────────────────────────────────────────────────────────────│
│ Probleem:  JUDOKA weegt buiten eigen gewichtsklasse                │
│ Detectie:  gewogen_gewicht past niet in ingeschreven klasse        │
│ Markering: Judoka gemarkeerd in poule (rode stip/badge)            │
│ Judoka BLIJFT in poule — wordt NIET automatisch verwijderd         │
│ Actie:     Org sleept of gebruikt 🔍 Zoek Match                    │
│                                                                     │
│ 📊 DYNAMISCHE CATEGORIEËN (max_kg_verschil > 0)                    │
│ ───────────────────────────────────────────────────────────────────│
│ Probleem:  POULE gewichtsrange > max_kg_verschil                   │
│ Detectie:  range = max(gewogen) - min(gewogen)                     │
│ Markering: Poule gemarkeerd als problematisch                      │
│            Lichtste + zwaarste judoka gemarkeerd                   │
│ Actie:     Org sleept of gebruikt 🔍 Zoek Match                    │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│ BEIDE:                                                              │
│   - Tools: Drag & drop + 🔍 Zoek Match                             │
│   - Afwezigen: automatisch uit poule, zichtbaar bij ℹ️ info        │
│   - Weegkaart + publieke pagina's updaten automatisch              │
│   - Judoka's direct in/uit poule plaatsen (drag & drop)            │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Detectie: Wanneer is overpoulen nodig?

**Na sluiten weging** per blok:

#### Vaste gewichtscategorieën
- Per judoka: `gewogen_gewicht` past niet in de gewichtsklasse van de poule
- Judoka wordt **gemarkeerd** in de poule (rode stip + badge)
- Judoka **blijft in de poule** tot org actie onderneemt

#### Dynamische categorieën
1. **Herbereken min-max kg** per poule op basis van **gewogen gewichten**
2. **Check:** `(max_kg - min_kg) > max_kg_verschil` uit categorie config?
3. **Indien ja:** poule is problematisch → lichtste + zwaarste gemarkeerd

**Voorbeeld (dynamisch):**
```
Poule #42 vóór weging:  28, 29, 30, 31 kg → range 3kg ✅ (max=3)
Poule #42 na weging:    27, 29, 30, 32 kg → range 5kg ❌ (max=3)
→ Probleem: 27kg of 32kg moet verplaatst worden
```

**Belangrijk:** Bij dynamisch gaat het om de POULE range, niet om individuele judoka's!

### Afwijkend Gewicht bij Vaste Categorieën

```
┌──────────────────────────────────────────────────────────────────┐
│ FLOW: AFWIJKEND GEWICHT BIJ VASTE GEWICHTSCATEGORIEËN            │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│ SITUATIE: Judoka ingeschreven -36kg, weegt 37.2kg                │
│                                                                   │
│ STAP 1: WEGING                                                    │
│   - Weegstation registreert 37.2kg                               │
│   - Systeem markeert judoka als "afwijkend gewicht"              │
│                                                                   │
│ STAP 2: MARKERING (geen automatische verplaatsing!)              │
│   - Judoka BLIJFT in -36kg poule                                 │
│   - Rode stip/badge toont "afwijkend gewicht"                    │
│   - Judoka is zichtbaar voor org om actie te ondernemen          │
│                                                                   │
│ STAP 3: ORGANISATOR HANDELT                                      │
│   - Sleept judoka naar passende -40kg poule                      │
│   - OF gebruikt 🔍 Zoek Match voor suggesties                    │
│   - Org bepaalt zelf prioriteit en timing                        │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

