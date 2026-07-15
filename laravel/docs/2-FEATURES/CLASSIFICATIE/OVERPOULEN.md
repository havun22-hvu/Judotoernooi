---
title: Overpoulen per Categorie Type
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Overpoulen per Categorie Type

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

### Afwezigen (BEIDE categorie types)

- Afwezigen gaan **automatisch** uit de poule
- Zichtbaar bij ℹ️ info tooltip van de poule
- NIET zichtbaar in de poule zelf

### Lege Poules op Wedstrijddag

```
┌──────────────────────────────────────────────────────────────────┐
│ LEGE POULES                                                        │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│ VASTE CATEGORIEËN: Lege poules WEL tonen                         │
│   → Voorbeeld: -36kg poule leeg → judoka uit -32kg kan erheen   │
│                                                                   │
│ DYNAMISCHE CATEGORIEËN: Lege poules NIET tonen                   │
│                                                                   │
│ ⚠️ LEGE POULES NOOIT OP MAT ZETTEN!                              │
│   • Lege poule = geen wedstrijden = niet op mat                  │
│   • Mat interface toont alleen poules met judoka's               │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

### Zoek Match (Wedstrijddag variant)

Hergebruik Zoek Match met blok-beperkingen:

| Blok situatie | Actie |
|---------------|-------|
| **Zelfde blok** | Direct in poule |
| **Ander blok (weging gesloten)** | Direct in poule |
| **Ander blok (weging open)** | Zoek Match toont waarschuwing |

### UI: Problematische Poules na Weging

Op **Wedstrijddag Poules** pagina:

```
┌──────────────────────────────────────────────────────────────────┐
│ ⚠️ Poule #42 Jeugd 9-10j                         Range: 5kg ❌  │
│    Huidige judoka's: 27-32kg (max toegestaan: 3kg)              │
│                                                                  │
│    [Toon details ▼]                                             │
│                                                                  │
│    27kg - Piet Jansen      [🔍 Zoek match] ← lichtste           │
│    29kg - Jan de Vries                                          │
│    30kg - Kees Bakker                                           │
│    32kg - Tom Smit         [🔍 Zoek match] ← zwaarste           │
│                                                                  │
│    💡 Verplaats de lichtste of zwaarste om range te verkleinen  │
└──────────────────────────────────────────────────────────────────┘
```

**Weergave:**
- Markeer poules waar range > max_kg_verschil
- Toon huidige range en max toegestaan
- Highlight lichtste EN zwaarste judoka (organisator kiest)
- Zoek Match knop alleen bij lichtste en zwaarste

### Zoek Match Popup (Wedstrijddag variant)

Extra informatie t.o.v. voorbereiding:
- Blok van doelpoule tonen
- Beschikbaarheid indicator (blok status)
- Sortering: zelfde blok eerst, dan volgend, dan vorig

```
┌──────────────────────────────────────────────────────────────────┐
│ Match voor: Piet Jansen (27kg, 9j)                          [X] │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│ 🟢 BLOK 2 (huidig blok)                                         │
│ ─────────────────────────────────────────────────────────────── │
│ ✅ Poule #38 Jeugd                                              │
│    Nu:  4 judoka's | 9-10j | 26-28kg                            │
│    Na:  5 judoka's | 9-10j | 26-28kg                            │
│                                                                  │
│ 🔵 BLOK 3 (volgend blok)                                        │
│ ─────────────────────────────────────────────────────────────── │
│ ⚠️ Poule #55 Jeugd                                   +1kg over  │
│    Nu:  3 judoka's | 8-9j | 24-26kg                             │
│    Na:  4 judoka's | 8-9j | 24-27kg                             │
│                                                                  │
│ 🟡 BLOK 1 (vorig blok - weging nog open)                        │
│ ─────────────────────────────────────────────────────────────── │
│ ✅ Poule #12 Jeugd                                              │
│    Nu:  4 judoka's | 9j | 26-29kg                               │
│    Na:  5 judoka's | 9j | 26-29kg                               │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Nieuwe Poule Maken

Als geen geschikte match:
- Organisator kan nieuwe poule aanmaken
- Nieuwe poule komt in zelfde blok (of kies blok)
- **Let op:** Lege poules niet op mat zetten!

---

