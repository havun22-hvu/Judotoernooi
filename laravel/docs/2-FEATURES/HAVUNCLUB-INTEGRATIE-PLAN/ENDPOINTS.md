---
title: HavunClub ↔ JudoToernooi — endpoints & JT-handoff
type: docs
scope: havunclub
audience: JudoToernooi-team (judotournament.org)
last_updated: 2026-07-02
---

# Endpoints & handoff — wat JT levert

Index: `../HAVUNCLUB-INTEGRATIE-PLAN.md`.

## Bestaande JT-endpoints (✅ werken, na base-URL-fix aan HavunClub-kant)

Auth: `Authorization: Bearer <ClubApiToken>` (token = tenant/Organisator; géén `tenant`-parameter). Base-URL: `https://judotournament.org/api`.

| Doel | Methode + pad | HavunClub stuurt | Response |
|---|---|---|---|
| Judoka-sync (upsert) | `POST /api/judokas` | `havunclub_judoka_id`, `voornaam`, `achternaam`, `geboortedatum`, `geslacht`, `band` | `{ "id": <stam-judoka-id> }` |
| Inschrijven | `POST /api/inschrijvingen` | `toernooi_id`, `judoka_id` (stam-id), `naam?`, `band?`, `gewicht?` | `{ "id": <judoka-id> }` |
| Resultaten | `GET /api/toernooien/{toernooi}/resultaten` | — | `[ { judoka_id, stam_judoka_id, naam, gewichtsklasse, resultaat, partijen } ]` |

JT mapt server-side (`naam`, `geboortejaar`, `geslacht → M/V`). Idempotentie via (`organisator`, `havunclub_judoka_id`→`havunclub_ref`). `resultaat` = eindpositie (1=goud …).

## Wat JudoToernooi nog moet leveren (handoff)

1. ✅ **Base-URL bevestigd:** `https://judotournament.org/api`.
2. ✅ **Portal-vul-endpoint (geleverd):** `POST /api/school-portal/{code}/inschrijvingen`. Autorisatie =
   per-toernooi **portal-code** (`{code}`, uit de uitnodigingslink) + **5-cijfer PIN** in de body — niet het
   ClubApiToken. PIN-bruteforce-guard (5/300s → `429`), verkeerde PIN `401`, onbekende code `404`. Velden per
   judoka: `pincode`, `havunclub_judoka_id?`, `voornaam`, `achternaam`, `geboortedatum?`, `geslacht?`, `band?`,
   `gewicht?`. Idempotent op `havunclub_judoka_id` (kolom `judokas.havunclub_ref`), anders naam+geboortejaar.
   De school kan alleen de eigen portal vullen (code+PIN scoping). Details: `../HAVUNCLUB-KOPPELING.md` §"Scenario 2".
3. ✅ **Weegkaart-lookup-endpoint (geleverd):** `GET /api/toernooien/{toernooi}/weegkaart/{judoka}` (op stam-id /
   `havunclub_ref`) → `{ "token": "<uuid>", "url": "https://judotournament.org/weegkaart/<uuid>" }`, of `404`
   als de judoka niet in dit toernooi is ingeschreven.
4. ✅ **Iframe-toestemming (geleverd):** op de weegkaart-route
   `Content-Security-Policy: frame-ancestors 'self' https://havunclub.havun.nl` (en géén `X-Frame-Options`),
   zodat HavunClub de weegkaart-page kan inbedden. Overige routes blijven `SAMEORIGIN`.
5. ✅ **`gewicht` (geleverd):** `POST /api/inschrijvingen` accepteert nu een optioneel `gewicht`
   (`nullable|numeric|min:0|max:300`) dat de gewichtsklasse-bepaling voedt.

> **Stand JT-kant:** alle 5 punten geïmplementeerd (`feat/havunclub-koppeling`). Openstaande
> **business**-keuze (geen JT-code): pusht HavunClub de volledige inschrijving via de portal-vul-API, of
> vult het alleen de lijst en gebeurt het definitieve inschrijven op de JT-portal-page? De API ondersteunt
> de push-variant; deep-linken naar de portal-page blijft ook mogelijk. Dat is HavunClubs keuze, niet JT-werk.
