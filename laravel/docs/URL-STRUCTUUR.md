---
title: URL Structuur - JudoToernooi
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# URL Structuur - JudoToernooi

> **Versie:** 2.0 (januari 2026)
> **Status:** In implementatie

> **Index-doc:** hieronder de principes en de auth-types; het volledige schema staat in deeldocs onder `URL-STRUCTUUR/` — zie [Waar staat wat](#waar-staat-wat).

---

## Overzicht

De URL structuur is opgebouwd rond twee principes:

1. **Organisator context** - URLs bevatten `{org}` (organisator slug) voor duidelijke eigenaarschap
2. **Auth indicator** - Het woord `toernooi` in de URL geeft aan dat login vereist is

---

## Authenticatie Types

| Type | Omschrijving | Voorbeeld |
|------|--------------|-----------|
| **Geen** | Vrij toegankelijk | Publieke toernooi pagina |
| **Organisator Login** | Email + wachtwoord (+ passkeys/biometrie) | Dashboard, toernooi beheer |
| **Rolcode** | 12-char code per rol | Coach portal |
| **Device binding** | Rolcode + browser/device koppeling | Vrijwilligers-interfaces (mat, weging, dojo, spreker, coach) |

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---|---|
| [URL-SCHEMA-BEHEER.md](URL-STRUCTUUR/URL-SCHEMA-BEHEER.md) | Je zoekt de URL van login, organisator-beheer, sitebeheerder of het toernooibeheer achter login. |
| [URL-SCHEMA-PUBLIEK-EN-PORTALS.md](URL-STRUCTUUR/URL-SCHEMA-PUBLIEK-EN-PORTALS.md) | Je zoekt een publieke toernooi-URL, of een URL in het coach portal of de vrijwilligers-flow met device binding. |
| [URL-SCHEMA-QR-API-LEGACY.md](URL-STRUCTUUR/URL-SCHEMA-QR-API-LEGACY.md) | Je werkt aan QR-codes, de dojo scanner API, of een oude URL die moet blijven redirecten. |
| [VISUEEL-OVERZICHT.md](URL-STRUCTUUR/VISUEEL-OVERZICHT.md) | Je wilt de hele URL-boom in één blik zien. |
| [MIDDLEWARE-EN-IMPLEMENTATIE.md](URL-STRUCTUUR/MIDDLEWARE-EN-IMPLEMENTATIE.md) | Je voegt een route toe: welke middleware hoort erbij, in welke volgorde, en welke slug-constraints gelden. |
