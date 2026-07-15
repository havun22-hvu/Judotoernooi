---
title: Tabellen: betalingen en Mollie-velden
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Tabellen: betalingen en Mollie-velden

> Onderdeel van [Database Schema](../DATABASE.md).

### betalingen

Mollie betalingsrecords.

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| id | bigint | Primary key |
| toernooi_id | bigint | FK naar toernooien |
| club_id | bigint | FK naar clubs |
| mollie_payment_id | varchar(255) | Mollie payment ID (unique) |
| bedrag | decimal(8,2) | Totaalbedrag |
| aantal_judokas | int | Aantal judoka's in betaling |
| status | varchar(20) | open/pending/paid/failed/expired/canceled |
| betaald_op | timestamp | Tijdstip van betaling |
| created_at | timestamp | Aangemaakt op |
| updated_at | timestamp | Gewijzigd op |

### Mollie velden in toernooien

Extra velden voor betalingsconfiguratie:

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| betaling_actief | boolean | Betalingen aan/uit |
| inschrijfgeld | decimal(8,2) | Bedrag per judoka |
| mollie_mode | varchar(20) | 'connect' of 'platform' |
| platform_toeslag | decimal(8,2) | Toeslag in euro's |
| platform_toeslag_percentage | boolean | Toeslag als percentage? |
| mollie_account_id | varchar(255) | Connect mode: account ID |
| mollie_access_token | text | Connect mode: encrypted token |
| mollie_refresh_token | text | Connect mode: encrypted token |
| mollie_token_expires_at | timestamp | Token expiry |
| mollie_onboarded | boolean | Succesvol gekoppeld? |
| mollie_organization_name | varchar(255) | Naam Mollie organisatie |

### Extra velden in judokas

| Kolom | Type | Beschrijving |
|-------|------|--------------|
| betaling_id | bigint | FK naar betalingen (nullable) |
| betaald_op | timestamp | Tijdstip betaling |
