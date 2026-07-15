---
title: Database Schema
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Database Schema

> Index-doc voor het databaseschema van JudoToernooi: het ERD en de kolomdefinities
> per tabel. De inhoud staat in de deeldocs hieronder — dit doc wijst de weg.

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [ERD.md](./DATABASE/ERD.md) | Je wilt zien hoe toernooien, blokken, matten, poules, judokas en clubs aan elkaar hangen (1:N, N:M, FK's) |
| [TABELLEN-BASIS.md](./DATABASE/TABELLEN-BASIS.md) | Je zoekt een kolom in `toernooien`, `gewichtsklassen_presets`, `clubs` of `judokas` |
| [TABELLEN-POULES-EN-WEDSTRIJDEN.md](./DATABASE/TABELLEN-POULES-EN-WEDSTRIJDEN.md) | Je zoekt een kolom in `poules`, `poule_judoka`, `wedstrijden` of `wegingen` |
| [TABELLEN-BETALINGEN.md](./DATABASE/TABELLEN-BETALINGEN.md) | Je werkt aan Mollie: `betalingen`, de Mollie-velden in `toernooien` of de betaalvelden in `judokas` |
