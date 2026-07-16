---
title: Freemium Model
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Freemium Model

> **Doel:** Organisatoren gratis laten uitproberen, betalen voor grotere toernooien.
> **Index-doc:** het business model, de gratis limieten en de betaalde staffels staan hieronder; de details staan in de deeldocs onder [`FREEMIUM/`](./FREEMIUM/).


---

## Business Model

JudoToernooi hanteert een freemium model:

| Aspect | Gratis | Betaald |
|--------|--------|---------|
| Judoka's | Max 50 | 51-500+ |
| Demo CSV import | Ja (30, 40 of 50 judoka's) | Niet nodig |
| Eigen CSV import | Ja, max 20 judoka's | Onbeperkt |
| Handmatig toevoegen | Max 20 (coach portal / handmatig) | Onbeperkt |
| Clubs | Max 2 actief | Onbeperkt |
| Presets | Max 1 | Onbeperkt |
| Eliminatie systeem | Nee (alleen poules/kruisfinale) | Volledig (dubbel eliminatie, IJF repechage) |
| Danpunten (JBN) | Nee | Volledig |
| Print/Noodplan | Nee | Volledig |
| Prijs | Gratis | Vanaf €20 |

---

## Gratis Tier Limieten

| Limiet | Waarde | Enforcement |
|--------|--------|-------------|
| Judoka's totaal | 50 | `Toernooi::canAddMoreJudokas()` |
| Eigen CSV import | Max 20 judoka's | `ImportController` |
| Handmatig toevoegen | Max 20 | `JudokaController` / `CoachPortalController` |
| Actieve clubs | 2 | `ClubController` |
| Presets | 1 | `GewichtsklassenPresetController` |
| Eliminatie systeem | Nee | `Toernooi::canUseEliminatie()` — optie verborgen in UI |
| Print/Noodplan | Nee | `CheckFreemiumPrint` middleware |

### Waarom deze limieten?

- **50 judoka's** = genoeg om een volledig toernooi te testen
- **Demo CSV** = direct werkende poules/schema's zonder zelf data in te voeren
- **Eigen CSV max 20** = testen of eigen Excel/CSV werkt, maar niet genoeg voor een echt toernooi
- **Handmatig max 20** = coach portal / handmatig invoeren testen
- **2 clubs** = Eigen club + 1 gastclub
- **1 preset** = Basis gewichtsklassen
- **Geen eliminatie** = geavanceerd systeem, stimuleert upgrade
- **Geen print** = stimuleert upgrade voor echte wedstrijddag

---

## Betaalde Staffels

| Staffel | Max Judoka's | Prijs |
|---------|--------------|-------|
| 51-100 | 100 | €20 |
| 101-150 | 150 | €30 |
| 151-200 | 200 | €40 |
| 201-250 | 250 | €50 |
| 251-300 | 300 | €60 |
| 301-350 | 350 | €70 |
| 351-400 | 400 | €80 |
| 401-500 | 500 | €100 |
| 501-600 | 600 | €120 |

**Prijsregel:** `max judoka's × €0,20`. Elke trede volgt deze regel — ook 401-500 en 501-600,
die 100 judoka's breed zijn in plaats van 50.

De staffels zijn **geen formule maar een lookup**: `FreemiumService::STAFFELS` is de enige bron
(`app/Services/FreemiumService.php`). `Toernooi::getStaffelPrijs()` leest diezelfde const —
niet opnieuw een lijst hardcoden, dat liep eerder uit elkaar.

Nieuwe trede toevoegen = één regel in `STAFFELS`. De upgrade-UI vult zich dynamisch en er is
geen DB-migratie nodig (`tier` is een vrije string). Staging rekent automatisch de helft via
`STAGING_KORTING`.

---

## Waar staat wat

| Deeldoc | Wanneer je het nodig hebt |
|---------|---------------------------|
| [DEMO-CSV-EN-TOERNOOI-TYPE.md](./FREEMIUM/DEMO-CSV-EN-TOERNOOI-TYPE.md) | Je werkt aan de demo-CSV import van het free tier, de kenmerken van demo-judoka's, of aan welke toernooi-typen zichtbaar zijn in de UI. |
| [UPGRADE-FLOW-EN-UI.md](./FREEMIUM/UPGRADE-FLOW-EN-UI.md) | Je volgt of wijzigt de upgrade-flow van gratis naar betaald, of raakt de freemium-banner, upgrade-pagina of print-blokkade. |
| [WIMPEL-EN-DATABASE.md](./FREEMIUM/WIMPEL-EN-DATABASE.md) | Je moet weten hoe het wimpel-abonnement wordt geactiveerd of verloopt, of welke kolommen in `toernooien` / `toernooi_betalingen` staan. |
| [IMPLEMENTATIE-EN-ROUTES.md](./FREEMIUM/IMPLEMENTATIE-EN-ROUTES.md) | Je zoekt `FreemiumService`- of `Toernooi`-methods, waar een limiet wordt afgedwongen, welke route wat doet, of hoe de upgrade-webhook werkt. |
| [TEST-ORGANISATOR-EN-TESTEN.md](./FREEMIUM/TEST-ORGANISATOR-EN-TESTEN.md) | Je wilt freemium lokaal of op staging testen, of de test-organisator gebruiken/aanmaken om limieten te omzeilen. |

## Relatie met BETALINGEN.md

Dit document gaat over **toernooi upgrades** (organisator betaalt aan JudoToernooi).

`BETALINGEN.md` gaat over **inschrijfgeld** (coach betaalt aan organisator).

| Aspect | Freemium (dit doc) | Inschrijfgeld |
|--------|-------------------|---------------|
| Wie betaalt | Organisator | Coach/judoschool |
| Aan wie | JudoToernooi | Organisator |
| Waarvoor | Meer capaciteit | Deelname judoka's |
| Mollie mode | Platform (altijd) | Connect of Platform |
| Webhook | `/mollie/webhook/toernooi` | `/mollie/webhook` |
