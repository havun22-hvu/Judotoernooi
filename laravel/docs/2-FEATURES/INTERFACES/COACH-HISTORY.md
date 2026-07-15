---
title: Coach check-in history: dojo-scanner
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Coach check-in history: dojo-scanner

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

#### Check-in History Overzichten

**Dojo Scanner - 2 Tabs:**

De dojo scanner krijgt 2 tabs: Scanner en Overzicht.

```
┌─────────────────────────────────────────────────┐
│  [Scanner]  [Overzicht]                         │
└─────────────────────────────────────────────────┘
```

**Tab 1: Scanner** (bestaand)
- QR scanner + check-in/uit knoppen

**Tab 2: Overzicht**

Simpel en overzichtelijk:
1. Bovenaan: zoek budoschool
2. Bij selectie: lijst kaarten met naam + in/uit status
3. Klik op kaart: toont check-in + overdracht geschiedenis

```
┌─────────────────────────────────────────────────┐
│  [Scanner]  [Overzicht]                         │
├─────────────────────────────────────────────────┤
│  🔍 Zoek budoschool...                          │
│  ┌─────────────────────────────────────────────┐│
│  │ Judo Hoorn                                  ││
│  │ Judo Alkmaar                                ││
│  │ Judo Den Helder                             ││
│  └─────────────────────────────────────────────┘│
└─────────────────────────────────────────────────┘
```

**Na selectie budoschool:**

```
┌─────────────────────────────────────────────────┐
│  [Scanner]  [Overzicht]                         │
├─────────────────────────────────────────────────┤
│  ← JUDO HOORN                       3 kaarten   │
├─────────────────────────────────────────────────┤
│  Kaart 1: Piet Jansen            ✅ IN (14:32)  │
│  Kaart 2: Jan de Vries           🚪 UIT (12:30) │
│  Kaart 3: (niet geactiveerd)     ⬚ --           │
└─────────────────────────────────────────────────┘
```

**Klik op kaart → Detail met geschiedenis:**

```
┌─────────────────────────────────────────────────┐
│  ← Kaart 1 - Judo Hoorn                         │
├─────────────────────────────────────────────────┤
│  Huidige coach: Piet Jansen                     │
│  Status: ✅ Ingecheckt sinds 14:32              │
├─────────────────────────────────────────────────┤
│  CHECK-IN GESCHIEDENIS                          │
│  14:32  ✅ IN   Piet Jansen                     │
│  12:30  🚪 UIT  Jan de Vries                    │
│  09:00  ✅ IN   Jan de Vries                    │
├─────────────────────────────────────────────────┤
│  OVERDRACHT GESCHIEDENIS                        │
│  14:30  Jan de Vries → Piet Jansen              │
│  08:45  (eerste activatie) Jan de Vries         │
└─────────────────────────────────────────────────┘
```

