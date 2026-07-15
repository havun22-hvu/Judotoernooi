---
title: Overpoulen: UI op de wedstrijddag
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Overpoulen: UI op de wedstrijddag

> Onderdeel van [Classificatie & Poule Indeling](../CLASSIFICATIE.md).

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

