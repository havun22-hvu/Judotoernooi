---
title: Coachkaart overdracht
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Coachkaart overdracht

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

### Coachkaart overdracht

Coaches kunnen worden afgewisseld tijdens het toernooi (bijv. ochtend/middag). Een coachkaart kan worden overgedragen aan een andere coach.

**Flow:**

```
Coach 1 (ochtend)                    Coach 2 (middag)
─────────────────                    ─────────────────
1. Opent link
2. Activeert: naam + foto
3. QR zichtbaar ✓
                                     4. Opent dezelfde link (of scant QR)
                                     5. Ziet: "Kaart overnemen van [Coach 1]?"
                                     6. Klikt "Overnemen"
                                     7. Vult in: eigen naam + foto
                                     8. QR zichtbaar ✓
9. Opent kaart →
   Ziet: "Overgedragen aan [Coach 2]"
   + foto van Coach 2
   🔒 QR niet meer zichtbaar
```

**Technisch:**
- Per coachkaart: 1 actieve binding (naam, foto, device)
- Bij overdracht: oude binding vervalt, nieuwe wordt actief
- Oude foto wordt verwijderd uit storage

**View na overdracht (voor vorige coach):**

```
┌─────────────────────────────────┐
│  🔒 Kaart overgedragen          │
│                                 │
│  Huidige coach:                 │
│  ┌─────────┐                    │
│  │  foto   │  [Naam Coach 2]    │
│  │         │  Sinds [tijdstip]  │
│  └─────────┘                    │
│                                 │
│  Jouw toegang is beëindigd.     │
└─────────────────────────────────┘
```

**Waarom foto tonen aan vorige coach:**
- Transparant: coach ziet aan wie is overgedragen
- Veiligheid: bij controle ziet vrijwilliger dat dit niet de actieve coach is

**Wisselgeschiedenis (dojo scanner):**

Bij scannen toont de dojo scanner niet alleen de huidige coach, maar ook alle wisselingen:

```
┌─────────────────────────────────┐
│  ✓ GELDIGE COACH                │
│                                 │
│  ┌─────────┐  Piet Jansen       │
│  │  foto   │  Club: Judo Hoorn  │
│  └─────────┘                    │
│                                 │
│  Wisselgeschiedenis:            │
│  ├─ 14:32 Piet Jansen ← huidig  │
│  └─ 09:15 Jan de Vries          │
└─────────────────────────────────┘
```

**Database:** `coach_kaart_wisselingen` tabel
```sql
- id
- coach_kaart_id (FK)
- naam
- foto (path, wordt NIET verwijderd)
- device_info
- geactiveerd_op
- overgedragen_op (NULL = huidige coach)
```

**Bestanden:**
- `CoachKaartController@show` - Toont kaart of "overgedragen" view
- `CoachKaartController@activeer` - Activatie/overdracht flow
- `CoachKaartController@scan` - Toont wisselgeschiedenis
- `resources/views/pages/coach-kaart/show.blade.php` - Kaart weergave
- `resources/views/pages/coach-kaart/activeer.blade.php` - Activatie formulier
- `resources/views/pages/coach-kaart/scan-result.blade.php` - Dojo scanner resultaat

