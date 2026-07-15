---
title: Blokverdeling - Database
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Blokverdeling - Database

> Onderdeel van [Blokverdeling](../BLOKVERDELING.md).


## Database

### Poules tabel
```
blok_id         - FK naar blokken (null = niet verdeeld)
blok_vast       - boolean (true = handmatig vastgezet met 📍)
mat_id          - FK naar matten (A-groep bij eliminatie split)
b_mat_id        - FK naar matten, nullable (B-groep eliminatie, standaard = mat_id)
```

### Blokken tabel
```
gewenst_wedstrijden - integer nullable (null = auto-berekend)
blok_label          - string nullable (auto-gegenereerd voor variabele cat.)
```
