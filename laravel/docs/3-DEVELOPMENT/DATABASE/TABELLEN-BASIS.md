---
title: Tabellen: toernooien, presets, clubs, judokas
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Tabellen: toernooien, presets, clubs, judokas

> Onderdeel van [Database Schema](../DATABASE.md).

## Tabellen

### toernooien

Hoofdtabel voor toernooien.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| naam | varchar(255) | Naam van het toernooi |
| organisatie | varchar(255) | Organiserende club |
| datum | date | Datum van het toernooi |
| locatie | varchar(255) | Locatie |
| aantal_matten | tinyint | Aantal beschikbare matten |
| aantal_blokken | tinyint | Aantal tijdsblokken |
| min_judokas_poule | tinyint | Minimum judoka's per poule |
| optimal_judokas_poule | tinyint | Optimaal aantal per poule |
| max_judokas_poule | tinyint | Maximum judoka's per poule |
| gewicht_tolerantie | decimal(3,1) | Tolerantie in kg |
| is_actief | boolean | Actief toernooi |
| poules_gegenereerd_op | timestamp | Timestamp poule generatie |
| blokken_verdeeld_op | timestamp | Timestamp blok verdeling |
| judoka_code_volgorde | varchar(20) | Sorteervolgorde: gewicht_band of band_gewicht |
| max_kg_verschil | decimal(3,1) | Max kg verschil dynamische indeling (default 3.0) |
| max_leeftijd_verschil | tinyint | Max leeftijd verschil dynamische indeling (default 2) |
| gebruik_gewichtsklassen | boolean | Gebruik vaste gewichtsklassen |
| gewichtsklassen | json | Configuratie per leeftijdsgroep |

### gewichtsklassen_presets

Opgeslagen preset configuraties per organisator.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| organisator_id | bigint | FK naar organisators |
| naam | varchar(100) | Naam van de preset |
| configuratie | json | Gewichtsklassen configuratie |
| created_at | timestamp | Aangemaakt op |
| updated_at | timestamp | Gewijzigd op |

**Unique constraint:** `[organisator_id, naam]`

### clubs

Judo clubs/verenigingen.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| naam | varchar(255) | Naam van de club |
| afkorting | varchar(10) | Korte naam |
| plaats | varchar(255) | Vestigingsplaats |
| email | varchar(255) | Contact email |

### judokas

Deelnemende judoka's.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| toernooi_id | bigint | FK naar toernooien |
| club_id | bigint | FK naar clubs |
| naam | varchar(255) | Volledige naam |
| geboortejaar | year | Geboortejaar |
| geslacht | char(1) | M of V |
| band | varchar(20) | Bandkleur |
| gewicht | decimal(4,1) | Opgegeven gewicht |
| leeftijdsklasse | varchar(20) | Berekende klasse |
| gewichtsklasse | varchar(10) | Berekende klasse |
| judoka_code | varchar(20) | Unieke code |
| aanwezigheid | varchar(20) | Status |
| gewicht_gewogen | decimal(4,1) | Gemeten gewicht |
| qr_code | varchar(50) | QR code identifier |

