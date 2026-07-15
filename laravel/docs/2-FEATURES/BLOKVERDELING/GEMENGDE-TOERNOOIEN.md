---
title: Blokverdeling - Gemengde toernooien
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Blokverdeling - Gemengde toernooien

> Onderdeel van [Blokverdeling](../BLOKVERDELING.md).


## Gemengde Toernooien (Vast + Variabel)

Bij toernooien met **zowel vaste ALS variabele categorieën** werkt de blokverdeling in twee fasen.

### Wanneer is het gemengd?

| Categorie | max_kg_verschil | Type |
|-----------|-----------------|------|
| Mini's U7 | > 0 | Variabel |
| Jeugd U12 | > 0 | Variabel |
| Dames U15 | = 0 | Vast |
| Heren U15 | = 0 | Vast |

### Het Probleem

- **Vaste categorieën** = grote groepen (bijv. "Heren U15 -55kg" met 40 judoka's)
- **Variabele categorieën** = kleine poules (bijv. "Mini's 6-7j 22-25kg" met 5 judoka's)

Grote groepen zijn moeilijk te plaatsen, kleine zijn flexibel.

### De Oplossing: Twee-Fasen Algoritme

```
FASE 1: VASTE CATEGORIEËN (ruggengraat)
────────────────────────────────────────
1. Identificeer categorieën met max_kg_verschil = 0
2. Groepeer per leeftijdsklasse + geslacht
3. Verdeel van jong → oud, licht → zwaar
4. Respecteer aansluiting gewichtsklassen (+1, -1, +2 blokken)
5. Update resterende capaciteit per blok

FASE 2: VARIABELE POULES (opvulling)
────────────────────────────────────────
1. Identificeer poules met max_kg_verschil > 0
2. Sorteer op min_leeftijd → min_gewicht
3. Vul resterende ruimte per blok
4. Kleine poules zijn flexibel "vulmiddel"
```

### Voorbeeld

```
Toernooi: Mini's (var), Jeugd (var), Dames U15 (vast), Heren U15 (vast)
4 Blokken, doel: 150 wedstrijden per blok

FASE 1 - Vaste categorieën eerst:
─────────────────────────────────
Blok 1: Dames U15 -40kg (35w), Dames U15 -44kg (42w)  = 77w
Blok 2: Dames U15 -48kg (38w), Heren U15 -46kg (45w)  = 83w
Blok 3: Heren U15 -50kg (52w), Heren U15 -55kg (48w)  = 100w
Blok 4: Heren U15 -60kg (44w), Heren U15 +60kg (32w)  = 76w

FASE 2 - Variabele poules vullen:
─────────────────────────────────
Blok 1: + Mini's 5-6j (28w) + Mini's 6-7j licht (45w) = 150w ✓
Blok 2: + Mini's 6-7j zwaar (32w) + Jeugd 8-9j (35w)  = 150w ✓
Blok 3: + Jeugd 9-10j (50w)                            = 150w ✓
Blok 4: + Jeugd 10-11j (40w) + Jeugd 11-12j (34w)     = 150w ✓
```

### Voordelen

1. **Grote groepen gegarandeerd geplaatst** - geen "past niet" situaties
2. **Aansluiting behouden** - gewichtsklassen blijven aansluitend
3. **Flexibele opvulling** - variabele poules passen in gaten
4. **Dag loopt logisch** - jong → oud, licht → zwaar

### UI Gedrag

Bij gemengde toernooien:

| Element | Gedrag |
|---------|--------|
| Sleepvak | Toont BEIDE types (vast als chips, variabel als poules) |
| Drag & drop | Werkt voor beide types |
| Vastzetten (📌) | Werkt voor beide types |
| Bereken | Fase 1 + Fase 2 algoritme |
| Varianten | 5 varianten met verschillende combinaties |

### Chip Weergave

**Vaste categorieën:**
```
Dames U15 -48kg (38w)
```

**Variabele poules:**
```
M 6j 22kg (10w)
```

---
