---
title: Technische Details
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Technische Details

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

## Technische Details

### Automatische Geslacht Detectie

Als `geslacht` niet is ingevuld maar label bevat indicatie:

| Label bevat | Wordt |
|-------------|-------|
| "Dames", "Meisjes", "_d" | V |
| "Heren", "Jongens", "_h" | M |

**Let op:** Als `geslacht = 'gemengd'` expliciet, dan GEEN auto-detect.

### Gewicht Fallback

Prioriteit voor effectief gewicht:
1. `gewicht_gewogen` (na weging)
2. `gewicht` (ingeschreven)
3. `gewichtsklasse` (extract: "-38" → 38.0)

### Rode Poule Markering

Een poule is rood als grootte NIET in `poule_grootte_voorkeur`:
- Default [5, 4, 6, 3] → 1, 2, 7, 8+ zijn rood
- Lege poules (0) zijn blauw (verwijderbaar)

### Overpoulen op Wedstrijddag

```
┌─────────────────────────────────────────────────────────────────────┐
│ KERNREGEL: Vast = JUDOKA afwijkend, Variabel = POULE afwijkend      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ 📦 VASTE GEWICHTSCATEGORIEËN (max_kg_verschil = 0)                 │
│    Wat:     JUDOKA weegt buiten eigen gewichtsklasse               │
│    Markering: Judoka gemarkeerd in poule (rode stip/badge)         │
│    Actie:   Org sleept of gebruikt 🔍 Zoek Match                   │
│    Judoka BLIJFT in poule tot org verplaatst                       │
│                                                                     │
│ 📊 DYNAMISCHE CATEGORIEËN (max_kg_verschil > 0)                    │
│    Wat:     POULE range te groot (lichtste vs zwaarste)            │
│    Markering: Poule gemarkeerd als problematisch                   │
│    Actie:   Org sleept of gebruikt 🔍 Zoek Match                   │
│    Lichtste/zwaarste judoka gemarkeerd                             │
│                                                                     │
│ BEIDE:                                                              │
│    - Tools: Drag & drop + 🔍 Zoek Match                            │
│    - Afwezigen: automatisch uit poule, zichtbaar bij ℹ️ info       │
│    - Judoka's direct in/uit poule plaatsen (drag & drop)           │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Zoek Match - Handmatig Judoka Verplaatsen

Verplaatst judoka naar andere poule **binnen dezelfde categorie**.
Gebruik voor: orphans, poule optimalisatie, handmatige correcties.

**Beschikbaar op:**
- **Poules pagina** (voorbereiding) - voor handmatige optimalisatie
- **Wedstrijddag Poules** - voor overpoelen na weging

**Activeren:** Klik op 🔍 vergrootglas icoon achter de judoka

**Popup toont alle poules gesorteerd op compatibiliteit:**

```
┌──────────────────────────────────────────────────────────────────┐
│ Match voor: Jan de Vries (60kg, 8j)                         [X] │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│ ✅ Poule #65 Jeugd                                              │
│    Nu:  4 judoka's | 7-8j | 57-60kg                             │
│    Na:  5 judoka's | 7-8j | 57-60kg                             │
│                                                                  │
│ ⚠️ Poule #68 Jeugd                                  +2kg over  │
│    Nu:  3 judoka's | 8-9j | 55-58kg                             │
│    Na:  4 judoka's | 8-9j | 55-60kg  ← gewicht verandert        │
│                                                                  │
│ ❌ Poule #75 Jeugd                                  +7kg over  │
│    Nu:  4 judoka's | 9-10j | 50-53kg                            │
│    Na:  5 judoka's | 8-10j | 50-60kg ← leeftijd én gewicht      │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

**Per poule tonen:**
- Poule nummer + categorie
- **Nu:** huidige statistieken (aantal judoka's | leeftijd range | gewicht range)
- **Na:** statistieken na verplaatsing (wat verandert is zichtbaar)
- Status indicator:
  - ✅ Past binnen limieten
  - ⚠️ Kleine overschrijding (acceptabel)
  - ❌ Grote overschrijding (problematisch)

**Actie:** Klik op poule → judoka wordt direct verplaatst, popup sluit

**Sortering poules:**
1. Eerst: past binnen limiet (✅)
2. Dan: minste kg overschrijding (⚠️)
3. Laatst: grote overschrijding (❌)

**Backend endpoint:** `POST /poule/{toernooi}/zoek-match/{judoka}`

Response:
```json
{
  "judoka": { "id": 123, "naam": "Jan", "gewicht": 60, "leeftijd": 8 },
  "matches": [
    {
      "poule_id": 65,
      "poule_titel": "Poule #65 Jeugd",
      "huidige_judokas": 4,
      "huidige_leeftijd": "7-8j",
      "huidige_gewicht": "57-60kg",
      "nieuwe_judokas": 5,
      "nieuwe_leeftijd": "7-8j",
      "nieuwe_gewicht": "57-60kg",
      "kg_overschrijding": 0,
      "lft_overschrijding": 0,
      "status": "ok"
    },
    ...
  ]
}
```

---

