---
title: HavunClub ↔ JudoToernooi — HavunClub-scope & openstaande keuzes
type: docs
scope: havunclub
audience: JudoToernooi-team (judotournament.org)
last_updated: 2026-07-02
---

# HavunClub-scope & nog te bevestigen

Index: `../HAVUNCLUB-INTEGRATIE-PLAN.md`.

## Wat HavunClub gaat bouwen (eigen scope, aparte MPC-taak)

- Base-URL `judotoernooi.nl` → `judotournament.org` in `JudoToernooiService`.
- `havunclub_judoka_id` (= `judokas.id`) meesturen bij sync + inschrijven.
- `koppelJudoka`/`kiesKandidaat` (fuzzy `/judokas/match`) **verwijderen**.
- **Weegkaart-page** met `<iframe>` naar `judotournament.org/weegkaart/{token}` + CSP `frame-src judotournament.org`; `haalWeegkaart` afstemmen op het nieuwe JT-endpoint.
- `/koppelingen`-beheer: scenario 1 = eigen JT-token; scenario 2 = per toernooi een **portal-link + pincode** invoeren (uit de uitnodiging van de organisator) waarmee HavunClub de portal vult.

## Nog te bevestigen (business/JT)

- **Portal-vul-API** (scenario 2): endpoint + hoe de **pincode** als autorisatie werkt (punt 2 in
  `ENDPOINTS.md`). De autorisatie zelf is duidelijk (portal-link + pincode per uitnodiging); alleen de
  exacte API-vorm ontbreekt nog.
- Verhouding "push via API" (portal vullen) vs "inschrijven op de JT-portal-page" — vult HCl alleen de lijst en
  gebeurt het definitieve inschrijven op de JT-portal, of pusht HCl de inschrijving compleet?
- Judoschool-portals zijn **optioneel** in JT — zonder portal geen HCl-push voor dat toernooi (dan QR-weegkaart buiten de app).
