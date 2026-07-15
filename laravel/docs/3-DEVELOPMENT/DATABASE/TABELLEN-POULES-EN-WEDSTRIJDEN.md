---
title: Tabellen: poules, wedstrijden, wegingen
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Tabellen: poules, wedstrijden, wegingen

> Onderdeel van [Database Schema](../DATABASE.md).

### poules

Poules met judoka's.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| toernooi_id | bigint | FK naar toernooien |
| blok_id | bigint | FK naar blokken |
| mat_id | bigint | FK naar matten (A-groep bij eliminatie split) |
| b_mat_id | bigint NULL | FK naar matten (B-groep eliminatie, nullable) |
| nummer | int | Poulenummer |
| titel | varchar(255) | Beschrijvende titel |
| leeftijdsklasse | varchar(20) | Leeftijdsklasse |
| gewichtsklasse | varchar(10) | Gewichtsklasse |
| aantal_judokas | tinyint | Cached count |
| aantal_wedstrijden | smallint | Cached count |
| spreker_klaar | timestamp | Tijdstip klaar voor spreker |
| afgeroepen_at | timestamp | Tijdstip prijzen uitgereikt |

### poule_judoka (pivot)

Koppeltabel poules ↔ judoka's.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| poule_id | bigint | FK naar poules |
| judoka_id | bigint | FK naar judokas |
| positie | tinyint | Volgorde in poule |
| punten | tinyint | Totaal punten |
| gewonnen | tinyint | Aantal gewonnen |
| verloren | tinyint | Aantal verloren |
| gelijk | tinyint | Aantal gelijk |
| eindpositie | tinyint | Eindrangschikking |

### wedstrijden

Individuele wedstrijden.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| poule_id | bigint | FK naar poules |
| judoka_wit_id | bigint | FK naar judokas |
| judoka_blauw_id | bigint | FK naar judokas |
| volgorde | tinyint | Wedstrijdnummer |
| winnaar_id | bigint | FK naar judokas |
| score_wit | varchar(20) | Score witte judoka |
| score_blauw | varchar(20) | Score blauwe judoka |
| uitslag_type | varchar(20) | Type uitslag |
| is_gespeeld | boolean | Wedstrijd afgerond |
| gespeeld_op | timestamp | Tijdstip |

### wegingen

Wegingsregistraties.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| judoka_id | bigint | FK naar judokas |
| gewicht | decimal(4,1) | Gemeten gewicht |
| binnen_klasse | boolean | Binnen gewichtsklasse |
| alternatieve_poule | varchar(255) | Suggestie |
| opmerking | varchar(255) | Opmerking |
| geregistreerd_door | varchar(255) | Registrant |

