---
title: B-Start Byes bij dubbele rondes
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# B-Start Byes bij dubbele rondes

> Onderdeel van [Eliminatie Systeem](./README.md).

### B-Start Byes (Dubbele Rondes)

Bij dubbele rondes worden de eerste A-ronde verliezers verspreid over **alle** B-start(1) wedstrijden. Elke B(1) wedstrijd krijgt minimaal 1 judoka. Als er minder verliezers zijn dan 2× B-capaciteit, krijgen sommige B(1) wedstrijden maar 1 judoka (bye).

```
B-capaciteit = berekenMinimaleBWedstrijden(V1)
Volle weds    = V1 - B-capaciteit     (krijgen 2 judoka's, 2:1 mapping)
Bye weds      = 2 × B-cap - V1        (krijgen 1 judoka op WIT, blauw=null)

Voorbeeld N=54: V1=22, B-cap=16, slots=32
→ Volle weds = 22 - 16 = 6   (idx 0-11: 12 verliezers, 2:1)
→ Bye weds   = 32 - 22 = 10  (idx 12-21: 10 verliezers, alleen WIT)
```

**Spreiding in `koppelARondeAanBRonde` type 'eerste':**
1. Eerste `volle × 2` verliezers → 2:1 mapping (normaal, wit+blauw)
2. Resterende verliezers → 1:1 op WIT (bye, blauw blijft null)

Bye wedstrijden worden **handmatig door de hoofdjury** geregistreerd → winnaar schuift door naar B(2) WIT.

### De "Byes"-knop verdwijnt zodra de eerste echte wedstrijd gespeeld is

Byes bestaan **alleen vóór de eerste echte wedstrijd** (A-ronde 1 en de B-start). Een leeg
blauw-slot in een latere ronde is géén bye maar een partij die op een winnaar wacht — die mag niet
doorschuiven.

- **Knop-zichtbaarheid:** `heeftOnverwerkteByes()` geeft `false` zodra `isBracketLocked(poule)` waar
  is (1 echte wedstrijd gespeeld; byes tellen niet). De knop verdwijnt dan.
- **Backend-guard:** `MatBracketController::doAdvanceByes()` weigert (`advanced: 0, locked: true`)
  als de bracket locked is — zodat een stale pagina of dubbelklik geen ronde-2-partij als bye
  doorschuift.

> **Was kapot (juli 2026):** de knop-check keek alleen naar "wit gevuld, blauw leeg, niet gespeeld"
> zonder de lock. Na een paar gespeelde partijen schoof de knop ook ronde-2-wedstrijden zonder
> tegenstander door. De lock-regel bestond al (`isBracketLocked`) maar was niet aan de knop of de
> backend gekoppeld.

