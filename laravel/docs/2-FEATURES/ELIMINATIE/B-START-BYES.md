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

