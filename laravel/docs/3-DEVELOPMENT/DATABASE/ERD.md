---
title: Database ERD
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Database ERD

> Onderdeel van [Database Schema](../DATABASE.md).

## Entity Relationship Diagram

```
┌─────────────────┐
│   toernooien    │
├─────────────────┤
│ id              │
│ naam            │
│ organisatie     │
│ datum           │◄───────────────────────────────┐
│ locatie         │                                │
│ aantal_matten   │                                │
│ aantal_blokken  │                                │
│ ...             │                                │
└─────────────────┘                                │
        │                                          │
        │ 1:N                                      │
        ▼                                          │
┌─────────────────┐      ┌─────────────────┐      │
│    blokken      │      │     matten      │      │
├─────────────────┤      ├─────────────────┤      │
│ id              │      │ id              │      │
│ toernooi_id   ──┼──────│ toernooi_id   ──┼──────┤
│ nummer          │      │ nummer          │      │
│ starttijd       │      │ naam            │      │
│ weging_gesloten │      │ kleur           │      │
└─────────────────┘      └─────────────────┘      │
        │                        │                │
        │ 1:N                    │ 1:N            │
        ▼                        ▼                │
┌─────────────────────────────────────────┐      │
│                poules                    │      │
├─────────────────────────────────────────┤      │
│ id                                       │      │
│ toernooi_id                            ──┼──────┘
│ blok_id                                ──┼──────┐
│ mat_id                                 ──┼──────┤
│ b_mat_id (nullable, eliminatie B-groep)──┼──────┤
│ nummer                                   │      │
│ titel                                    │      │
│ leeftijdsklasse                         │      │
│ gewichtsklasse                          │      │
│ aantal_judokas                          │      │
│ aantal_wedstrijden                      │      │
└─────────────────────────────────────────┘      │
        │                                         │
        │ N:M (via poule_judoka)                 │
        ▼                                         │
┌─────────────────┐                              │
│    judokas      │                              │
├─────────────────┤                              │
│ id              │                              │
│ toernooi_id   ──┼──────────────────────────────┘
│ club_id       ──┼──────┐
│ naam            │      │
│ geboortejaar    │      │
│ geslacht        │      │
│ band            │      │
│ gewicht         │      │
│ leeftijdsklasse │      │
│ gewichtsklasse  │      │
│ judoka_code     │      │
│ aanwezigheid    │      │
│ gewicht_gewogen │      │
│ qr_code         │      │
└─────────────────┘      │
        │                │
        │ N:1            │
        ▼                │
┌─────────────────┐      │
│     clubs       │◄─────┘
├─────────────────┤
│ id              │
│ naam            │
│ afkorting       │
│ plaats          │
└─────────────────┘
```

