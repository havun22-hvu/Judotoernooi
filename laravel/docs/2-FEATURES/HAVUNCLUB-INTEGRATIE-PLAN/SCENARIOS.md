---
title: HavunClub ↔ JudoToernooi — koppelscenario's
type: docs
scope: havunclub
audience: JudoToernooi-team (judotournament.org)
last_updated: 2026-07-02
---

# Koppelscenario's — HCl en JT staan volledig los

Index: `../HAVUNCLUB-INTEGRATIE-PLAN.md`.

## HCl en JT staan volledig los — koppeling per uitnodiging

HavunClub en JudoToernooi zijn **onafhankelijke, wereldwijde tools**: JT kan door elke organisator gebruikt
worden voor een toernooi; HCl door elke judoschool voor de ledenadministratie. Er is **geen vaste 1-op-1
koppeling**. Twee scenario's waarin ze elkaar raken:

**Scenario 1 — eigen toernooi / puntencompetitie.** De judoschool ís zelf de organisator op JT (bv. een interne
puntencompetitie). Dan geldt het bestaande **`ClubApiToken`**-model (token = Organisator, zie `../HAVUNCLUB-KOPPELING.md`):
HavunClub synct de eigen judoka's naar de eigen JT.

**Scenario 2 — uitgenodigd bij andermans toernooi (judoschool-portals).** Een organisator (school A) organiseert
een toernooi op JT en gebruikt de **judoschool-portals** (een **optionele** JT-feature). Dan:
- A nodigt elke deelnemende judoschool uit → JT stuurt per school een **portal-link + pincode**.
- Een uitgenodigde judoschool B die HavunClub gebruikt, vult zijn **JT-portal vanuit HavunClub** met behulp van
  die **link + pincode** (in te voeren in HCl `/koppelingen` per toernooi). HCl pusht dan B's judoka's naar B's
  portal in A's toernooi.
- **De portal-link + pincode zijn de autorisatie** voor die push — niet het globale ClubApiToken. B kan alleen
  zijn eigen portal vullen.

**Open JT-detail (te bevestigen):** de **API-vorm** waarmee HCl een portal vult met link + pincode — welk endpoint,
hoe de pincode meegestuurd/geverifieerd wordt, en welke velden (incl. `havunclub_judoka_id` + gewicht). De
judoschool-portals zijn optioneel: gebruikt de organisator ze niet, dan is er geen HCl-push mogelijk voor dat toernooi.
