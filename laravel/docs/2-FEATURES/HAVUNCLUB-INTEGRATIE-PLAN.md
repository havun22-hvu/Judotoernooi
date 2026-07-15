---
title: HavunClub ↔ JudoToernooi (judotournament.org) — integratiespec & handoff
type: docs
scope: havunclub
audience: JudoToernooi-team (judotournament.org)
last_updated: 2026-07-02
status: SPEC / handoff — JT-kant volledig geleverd; HavunClub-kant plant de ombouw
---

# HavunClub ↔ JudoToernooi (judotournament.org) — integratieplan

> **Doel van dit doc:** één gedeelde bron zodat de HavunClub- en JudoToernooi-teams samen kunnen
> werken. HavunClub = de aanroeper/pusher; JudoToernooi (judotournament.org) = de leverancier.
> Beide apps zijn van dezelfde eigenaar. Bestaande JT-implementatie: `HAVUNCLUB-KOPPELING.md`.

**Stand JT-kant:** alle vijf de handoff-punten zijn geïmplementeerd (`feat/havunclub-koppeling`,
live op prod sinds 03-07). Wat rest is HavunClub-werk plus één business-keuze — zie
`HAVUNCLUB-INTEGRATIE-PLAN/HAVUNCLUB-SCOPE.md`.

## Doel in het kort (voor JT)

Een judoschool die HavunClub gebruikt wil zijn judoka's **niet dubbel intypen** op judotournament.org. De
bedoeling:
1. **HavunClub vult de judoka-lijst op JT** (push vanuit HCl), met de **HavunClub-judoka-ID** erbij, zodat JT
   die judoka's kan herkennen.
2. Bij een toernooi van een **andere** organisator die de (optionele) **judoschool-portals** gebruikt, vult de
   HCl-school zijn **portal** via de **portal-link + pincode** die de organisator stuurde.
3. Omdat JT de HavunClub-ID kent, kan de **weegkaart** die JT maakt **binnen HavunClub** getoond worden (als
   ingebedde page), niet alleen via de losse QR.

**Wat JT hiervoor moest doen:** een base-URL bevestigen, een **portal-vul-API** (met pincode-autorisatie) leveren,
een **weegkaart-lookup-endpoint** (per judoka → weegkaart-URL) toevoegen, en de weegkaart-page **iframe-baar**
maken vanaf HavunClub. Alle vijf geleverd; details in `HAVUNCLUB-INTEGRATIE-PLAN/ENDPOINTS.md`.

## Context

Een judoschool gebruikt drie losse SaaS-apps: **HavunClub** (ledenadministratie/hub), **JudoToernooi**
(judotournament.org — toernooien én interne puntencompetitie) en **HavunAdmin** (boekhouding). HavunClub
is de bron van de judoka-stamdata van een school. JT organiseert toernooien en maakt weegkaarten.

## Kernflow (besluit 02-07)

1. **HavunClub pusht de judolijst** van een school naar JT (stamdata + de **HavunClub-judoka-ID**). JT
   bewaart die ID als `stam_judokas.havunclub_ref` → **JT kent de HavunClub-ID** van elke judoka.
2. **Inschrijven voor een toernooi** gebeurt op de **JT-judoschool-page** van die school binnen dat toernooi:
   `https://judotournament.org/{organisator}/{toernooi}/school/{school-code}/judokas`. HavunClub pusht de
   judoka's daarheen en/of deep-linkt naar die page.
3. **Weegkaart** wordt door JT gemaakt (QR-verspreiding: publieke route `judotournament.org/weegkaart/{token}`).
   Omdat JT de HavunClub-ID kent, kan HavunClub de weegkaart **binnen de app** tonen (iframe) — **alleen voor
   judoka's die via HavunClub zijn ingeschreven**. Niet via HavunClub ingeschreven → judoka gebruikt de **QR
   buiten de app** (geen onzekere koppeling).
4. **Deterministische ID-koppeling, GEEN fuzzy matching.** De vroegere `POST /judokas/match` (matchen op
   naam+geboortejaar+band) vervalt — te onzeker. De ID wordt bij de push/inschrijving vastgelegd.

## Deeldocs

| Doc | Wanneer je het nodig hebt |
|---|---|
| `HAVUNCLUB-INTEGRATIE-PLAN/SCENARIOS.md` | Hoe HCl en JT elkaar raken: eigen toernooi (ClubApiToken) vs uitgenodigd bij andermans toernooi (portal-link + pincode). |
| `HAVUNCLUB-INTEGRATIE-PLAN/ENDPOINTS.md` | De API: bestaande endpoints (judoka-sync, inschrijven, resultaten) + de vijf geleverde handoff-punten met velden, auth en foutcodes. |
| `HAVUNCLUB-INTEGRATIE-PLAN/HAVUNCLUB-SCOPE.md` | Wat HavunClub zelf nog bouwt, en de openstaande business-keuzes. |

Verwant: `HAVUNCLUB-KOPPELING.md` (de JT-implementatie zelf) en
`HavunCore/docs/kb/contracts/havunclub-koppelingen.md` (het contract tussen beide apps).
