---
title: Coach in/uitcheck: flow & database
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Coach in/uitcheck: flow & database

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

### Coach In/Uitcheck Systeem (optioneel)

**Doel:** Voorkomen dat coachkaart wordt overgedragen terwijl coach nog in de dojo is.

**Instelling:** `coach_incheck_actief` in toernooi instellingen (default: false)

#### Flow bij ingeschakeld

**Dojo Scanner - In/Uitcheck:**
```
┌─────────────────────────────────┐
│  ✓ GELDIGE COACH                │
│                                 │
│  ┌─────────┐  Piet Jansen       │
│  │  foto   │  Club: Judo Hoorn  │
│  └─────────┘                    │
│                                 │
│  Status: ⬚ Niet ingecheckt      │  ← of ✅ Ingecheckt sinds 09:15
│                                 │
│  ┌─────────────────────────────┐│
│  │      CHECK IN               ││  ← Groene knop
│  └─────────────────────────────┘│
│  (of "CHECK UIT" als ingecheckt)│
└─────────────────────────────────┘
```

**Portal - Overdracht GEBLOKKEERD als coach ingecheckt:**

Clubs kunnen NIET zelf bepalen of overdracht mogelijk is. Dit voorkomt dat meerdere coaches op 1 QR-code "binnen gesmokkeld" worden.

```
┌─────────────────────────────────┐
│  🔒 OVERDRACHT NIET MOGELIJK    │
│                                 │
│  Huidige coach [Naam] is nog    │
│  ingecheckt in de dojo.         │
│                                 │
│  De coach moet eerst UIT-       │
│  checken bij de dojo scanner    │
│  voordat de kaart kan worden    │
│  overgedragen.                  │
│                                 │
│  [Begrepen]                     │
└─────────────────────────────────┘
```

**Coachkaart view - Instructie voor huidige coach:**

Als coach ingecheckt is en kaart bekijkt:
```
┌─────────────────────────────────┐
│  ℹ️ OVERDRACHT                  │
│                                 │
│  Wilt u deze kaart overdragen   │
│  aan een andere coach?          │
│                                 │
│  Ga naar de dojo scanner en     │
│  check uit. Daarna kan de       │
│  nieuwe coach de kaart          │
│  overnemen.                     │
└─────────────────────────────────┘
```

**Coachkaart view - Instructie voor nieuwe coach:**

Als nieuwe coach de link opent maar huidige coach nog ingecheckt:
```
┌─────────────────────────────────┐
│  🔒 KAART NOG IN GEBRUIK        │
│                                 │
│  [Naam huidige coach] is nog    │
│  ingecheckt in de dojo.         │
│                                 │
│  Vraag de huidige coach om      │
│  uit te checken bij de dojo     │
│  scanner. Daarna kunt u de      │
│  kaart overnemen.               │
└─────────────────────────────────┘
```

#### Database

**coach_kaarten tabel (nieuw veld):**
```sql
ingecheckt_op    TIMESTAMP NULL    -- NULL = niet ingecheckt
```

**toernooien tabel (nieuw veld):**
```sql
coach_incheck_actief    BOOLEAN DEFAULT FALSE
```

**coach_checkins tabel (history):**
```sql
- id
- coach_kaart_id (FK)
- toernooi_id (FK)
- naam              -- Naam coach op moment van actie
- club_naam         -- Club naam voor snelle weergave
- foto              -- Foto path (snapshot)
- actie             -- 'in', 'uit', 'uit_geforceerd'
- geforceerd_door   -- NULL of 'hoofdjury'
- created_at        -- Tijdstip van actie
```

