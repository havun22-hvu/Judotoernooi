---
title: Gegevens en privacy — wat mag publiek
type: reference
scope: judotoernooi
last_check: 2026-07-20
---

# Gegevens en privacy — wat mag publiek

**Kern:** wedstrijdgegevens van een judoka zijn in het judodomein openbaar op de wedstrijddag.
Ze hoeven in de UI of op een Reverb-kanaal **niet** afgeschermd te worden. Contactgegevens wél.

## Publiek — geen afscherming nodig

| Veld | Waarom publiek |
|------|----------------|
| `naam` / `voornaam` / `achternaam` | Staat op het poulebriefje, wordt omgeroepen, staat op de uitslag |
| `gewicht` / `gewicht_gewogen` | Bepaalt de indeling; hangt bij de weging zichtbaar uit |
| `geboortejaar` → leeftijd | Bepaalt de leeftijdsklasse, staat op elk schema |
| `geslacht` (M/V) | Bepaalt de poule-indeling |
| `band`, `club`, poule/mat/blok, WP/JP, uitslag | Kern van de wedstrijdweergave |

Beslissing Henk, 20-07-2026. Deze velden mogen in publieke payloads, in broadcast-events en op
de publieke live-pagina. Dit is de reden dat de Reverb-kanalen publiek mogen zijn
(zie `INTERFACES/PUBLIEK.md`).

## Niet publiek — nooit in een publieke payload

- `telefoon` (`Judoka.php:49`)
- `jbn_lidnummer` (`Judoka.php:30`)
- Alles rond betaling (`betaling_id`, `betaald_op`) en inlog/toegang
- Organisator- en clubcontactgegevens

## Geslacht ≠ gender

Er is één veld: `geslacht`, een `char(1)` met M of V (`create_judokas_table.php:21`,
`stam_judokas`-tabel idem). Dat is **sekse voor de wedstrijdindeling** — het JBN-reglement kent
jongens- en meisjescategorieën.

**Gender-identiteit registreren we niet** en er is geen non-binaire waarde. Vraag daar geen
functionaliteit omheen te bouwen zonder expliciet overleg; het veld bestaat alleen om poules te
kunnen indelen. Sinds de portal-migratie is het nullable
(`2026_01_23_204738_make_judoka_fields_nullable_for_portal.php`), dus code moet met `null` omgaan.

## Wat "publiek" níét betekent

Openbaar op de wedstrijddag maakt het geen niet-persoonsgegeven. Het gaat vaak om minderjarigen.
Blijft dus gelden, los van bovenstaande vrijgave:

- Een verwijder- of inzageverzoek moet uitvoerbaar zijn.
- Toernooidata bewaren we niet langer dan nodig; geen doorverkoop, geen gebruik buiten het toernooi.
- Geen judoka-gegevens naar derden zonder grondslag.

Kort gezegd: **niet afschermen in de zaal, wel netjes beheren in de database.**
